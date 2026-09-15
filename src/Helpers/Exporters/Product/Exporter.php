<?php

namespace Webkul\Bagisto\Helpers\Exporters\Product;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Webkul\Attribute\Repositories\AttributeOptionRepository;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Rules\AttributeTypes;
use Webkul\Bagisto\Enums\Export\BagistoImageFormat;
use Webkul\Bagisto\Enums\Export\CacheType;
use Webkul\Bagisto\Enums\Export\DamFileType;
use Webkul\Bagisto\Enums\Export\JobFilter;
use Webkul\Bagisto\Enums\Export\MappingSection;
use Webkul\Bagisto\Enums\Export\ProductFilter as BagistoProductFilter;
use Webkul\Bagisto\Enums\Export\ProductType;
use Webkul\Bagisto\Enums\Export\SkipReason;
use Webkul\Bagisto\Enums\Services\MethodType;
use Webkul\Bagisto\Repositories\AttributeMappingRepository;
use Webkul\Bagisto\Repositories\BagistoDataMapping;
use Webkul\Bagisto\Repositories\CredentialRepository;
use Webkul\Bagisto\Support\ScopeFilters;
use Webkul\Bagisto\Traits\ApiRequest as ApiRequestTrait;
use Webkul\Bagisto\Traits\Credential as CredentialTrait;
use Webkul\Bagisto\Traits\ExportSummary as ExportSummaryTrait;
use Webkul\Bagisto\Traits\Mapping as MappingTrait;
use Webkul\Bagisto\Traits\SkippedItems as SkippedItemsTrait;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Category\Validator\FieldValidator;
use Webkul\Core\Repositories\ChannelRepository;
use Webkul\DataTransfer\Contracts\JobTrackBatch as JobTrackBatchContract;
use Webkul\DataTransfer\Enums\ProductFilter;
use Webkul\DataTransfer\Helpers\Export;
use Webkul\DataTransfer\Helpers\Exporters\Product\Exporter as AbstractExporter;
use Webkul\DataTransfer\Helpers\Sources\Export\ProductSource;
use Webkul\DataTransfer\Jobs\Export\File\FlatItemBuffer as FileExportFileBuffer;
use Webkul\DataTransfer\Repositories\JobTrackBatchRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Product\Services\VariantValueResolver;

class Exporter extends AbstractExporter
{
    use ApiRequestTrait;
    use CredentialTrait;
    use ExportSummaryTrait;
    use MappingTrait;
    use SkippedItemsTrait;

    protected const ENTITY_TYPE = 'bulk_product';

    protected const MEASUREMENT_ATTRIBUTE_TYPE = 'measurement';

    protected const DAM_ASSET_ATTRIBUTE_TYPE = 'asset';

    protected const DAM_ASSET_REPOSITORY = 'Webkul\\DAM\\Repositories\\AssetRepository';

    protected const NON_INHERITABLE_FIELDS = ['sku', 'url_key'];

    protected const UNOPIM_SKU_FIELD = 'sku';

    protected const CURSOR_COLUMNS = ['id', 'sku', 'type', 'parent_id'];

    protected const MAX_VARIANT_DEPTH = 2;

    public const BATCH_SIZE = 100;

    protected bool $initialized = false;

    protected bool $exportsFile = false;

    protected array $mappingAttributes = [];

    private ?array $requiredSourceCodeCache = null;

    protected array $credential = [];

    protected array $jobFilters = [];

    protected array $urlKey = [];

    protected array $knownSkus = [];

    protected ?object $assetRepository = null;

    public function __construct(
        protected JobTrackBatchRepository $exportBatchRepository,
        protected FileExportFileBuffer $exportFileBuffer,
        protected BagistoDataMapping $bagistoDataMappingRepository,
        protected AttributeRepository $attributeRepository,
        protected ProductRepository $productRepository,
        protected CategoryRepository $categoryRepository,
        protected AttributeOptionRepository $attributeOptionRepository,
        protected AttributeMappingRepository $attributeMappingRepository,
        protected ChannelRepository $channelRepository,
        protected CredentialRepository $credentialRepository,
        protected ProductSource $productSource
    ) {
        parent::__construct($exportBatchRepository, $exportFileBuffer, $channelRepository, $attributeRepository, $productSource);
    }

    public function initialize(): void
    {
        $this->initializeCredential($this->getFilters());
        $this->initializeMappingAttributes();
        $this->initializeJobFilters();
    }

    public function initializeMappingAttributes(): void
    {
        $cacheKey = CacheType::ATTRIBUTE_MAPPING->forCredential($this->credential['id'] ?? null);

        $this->mappingAttributes = Cache::get($cacheKey, []);

        if (empty($this->mappingAttributes)) {
            $credentialId = $this->credential['id'] ?? null;

            $this->mappingAttributes = [
                MappingSection::STANDARD_ATTRIBUTE->value => $this->attributeMappingRepository->forCredential($credentialId, MappingSection::STANDARD_ATTRIBUTE),
                MappingSection::IMAGE_ATTRIBUTE->value    => $this->attributeMappingRepository->forCredential($credentialId, MappingSection::IMAGE_ATTRIBUTE),
            ];

            Cache::put($cacheKey, $this->mappingAttributes, config('session.lifetime'));
        }
    }

    public function initializeJobFilters(): void
    {
        $filters = $this->getFilters();

        $filtersChannels = ScopeFilters::channelCodes($filters);
        $filtersLocales = ScopeFilters::localeCodes($filters);

        $this->applyAttributeScope(ScopeFilters::attributeCodes($filters));

        $bagistoChannels = $this->getMappedChannels();
        $bagistoLocales = $this->getMappedLocales();

        $mappedBagistoChannels = [];
        $exportBagistoLocales = [];

        foreach ($bagistoChannels as $bagistoChannel => $unopimChannel) {
            if (empty($filtersChannels) || in_array($unopimChannel, $filtersChannels, true)) {
                $mappedBagistoChannels[$bagistoChannel] = $unopimChannel;

                if (isset($bagistoLocales[$bagistoChannel])) {
                    foreach ($bagistoLocales[$bagistoChannel] as $bagistoLocal => $unopimLocal) {
                        if (empty($filtersLocales) || in_array($unopimLocal, $filtersLocales, true)) {
                            $exportBagistoLocales[$bagistoChannel][$bagistoLocal] = $unopimLocal;
                        }
                    }
                }
            }
        }

        $this->jobFilters = [
            JobFilter::WITH_MEDIA->value        => $filters[BagistoProductFilter::WITH_MEDIA->value] ?? false,
            JobFilter::WITH_ASSOCIATIONS->value => $filters[BagistoProductFilter::WITH_ASSOCIATIONS->value] ?? false,
            JobFilter::CHANNEL->value           => $mappedBagistoChannels,
            JobFilter::LOCALES->value           => $exportBagistoLocales,
        ];
    }

    public function exportBatch(JobTrackBatchContract $batch, $filePath): bool
    {
        if (! $this->initialized) {
            $this->initialize();
            $this->initialized = true;
        }

        $preparedData = $this->prepareBagistoProducts($batch, $filePath);

        $this->write($preparedData, $batch->id);

        $this->updateBatchState($batch->id, Export::STATE_PROCESSED);

        return true;
    }

    protected function getResults(): CollectionCursor
    {
        $query = $this->source->newQuery();

        resolve(ProductExportFilter::class)->applyToQuery($query, $this->prepareFilters());

        $products = $query->get(self::CURSOR_COLUMNS);

        $parentIds = $products->where('type', ProductType::CONFIGURABLE->value)->pluck('id')->filter()->all();
        $descendants = collect();
        $depth = 0;

        while (! empty($parentIds) && $depth++ < self::MAX_VARIANT_DEPTH) {
            $children = $this->productRepository
                ->whereIn('parent_id', $parentIds)
                ->get(self::CURSOR_COLUMNS);

            if ($children->isEmpty()) {
                break;
            }

            $descendants = $descendants->concat($children);
            $parentIds = $children->where('type', ProductType::VARIANT_GROUP->value)->pluck('id')->filter()->all();
        }

        if ($descendants->isNotEmpty()) {
            $products = $products->concat($descendants)->unique('sku')->values();
        }

        return new CollectionCursor($this->orderFamiliesContiguously($products)->toArray());
    }

    protected function prepareFilters(): array
    {
        $filters = $this->getFilters();

        $filters[ProductFilter::UPDATED_AFTER->value] = $this->resolveUpdatedAfter($filters);
        $filters[ProductFilter::UPDATED_BEFORE->value] = $this->resolveUpdatedBefore($filters);

        return $filters;
    }

    private function orderFamiliesContiguously(Collection $products): Collection
    {
        $byId = $products->keyBy('id');

        $rootOf = function ($product) use ($byId) {
            $current = $product;

            for ($hop = 0; $hop <= self::MAX_VARIANT_DEPTH; $hop++) {
                $parent = $current->parent_id ? $byId->get($current->parent_id) : null;

                if (! $parent) {
                    return $current->id;
                }

                $current = $parent;
            }

            return $current->id;
        };

        $families = [];

        foreach ($products as $product) {
            $families[$rootOf($product)][] = $product;
        }

        $ordered = collect();
        $flushed = [];

        foreach ($products as $product) {
            $root = $rootOf($product);

            if (isset($flushed[$root])) {
                continue;
            }

            $flushed[$root] = true;
            $ordered = $ordered->concat($families[$root]);
        }

        return $ordered->values();
    }

    public function write(array $items, int $batchId): void
    {
        try {
            $items = $this->rejectIncompleteItems($items);

            if (empty($items)) {
                return;
            }

            $response = $this->setApiRequest(MethodType::POST->value, self::ENTITY_TYPE, $items, []);

            $skusAttempted = array_values(array_unique(array_column($items, 'sku')));

            if (! empty($this->lastApiErrors)) {
                $errors = $this->flattenApiErrors($this->lastApiErrors);

                foreach ($skusAttempted as $sku) {
                    $this->recordSkipped($sku, SkipReason::REQUEST_FAILED, $errors);
                }

                return;
            }

            $queuedSkus = ! empty($response['queued'])
                ? array_values($response['queued'])
                : $skusAttempted;

            $rejectedSkus = array_values(array_diff($skusAttempted, $queuedSkus));

            if (! empty($rejectedSkus)) {
                $errors = $this->flattenApiErrors($response['errors'] ?? []);

                foreach ($rejectedSkus as $sku) {
                    $this->recordSkipped($sku, SkipReason::REJECTED_BY_BAGISTO, $errors);
                }
            }

            if (empty($queuedSkus)) {
                return;
            }

            foreach ($this->productIdsByBagistoSku($items, $queuedSkus) as $productId) {
                if ($this->getMapping($this->credential['id'], $productId, null, null, null, self::ENTITY_TYPE)) {
                    $this->updatedItemsCount++;
                } else {
                    $this->createdItemsCount++;
                    $this->setMapping($this->credential['id'], $productId, 0, $batchId, null, self::ENTITY_TYPE);
                }
            }
        } catch (\Exception $e) {
            foreach (array_values(array_unique(array_column($items, 'sku'))) as $sku) {
                $this->recordSkipped($sku, SkipReason::REQUEST_FAILED, [$e->getMessage()]);
            }
        }
    }

    protected function flattenApiErrors(array $errors): array
    {
        $flat = [];

        array_walk_recursive($errors, function ($value) use (&$flat): void {
            if (is_scalar($value) && (string) $value !== '') {
                $flat[] = (string) $value;
            }
        });

        return array_values(array_unique($flat));
    }

    private function rejectIncompleteItems(array $items): array
    {
        $required = $this->getRequiredBagistoFields();

        return array_values(array_filter($items, function ($item) use ($required) {
            $missing = array_values(array_filter(
                $required,
                fn ($code) => ! isset($item[$code]) || $item[$code] === '' || $item[$code] === null
            ));

            if ($missing === []) {
                return true;
            }

            $this->recordSkipped($item['sku'] ?? '(no sku)', SkipReason::MISSING_REQUIRED_FIELDS, $missing);

            return false;
        }));
    }

    public function prepareBagistoProducts(JobTrackBatchContract $batch, $filePath): array
    {
        $products = [];
        $skus = array_column($batch->data, 'sku');
        $allProducts = $this->productRepository
            ->with(['attribute_family', 'parent.parent', 'super_attributes', 'variants.variants'])
            ->whereIn('sku', $skus)
            ->get();

        $resolvedValues = resolve(VariantValueResolver::class)->resolveBatch($allProducts);

        foreach ($allProducts as $productModel) {
            $rowData = $productModel->toArray();
            $ownValues = $productModel->values ?? [];
            $rowData['values'] = $this->keepOwnIdentityFields(
                $resolvedValues[$productModel->id] ?? $ownValues,
                $ownValues
            );

            if (! $this->isExportableType($rowData)) {
                if ($rowData['type'] === ProductType::VARIANT_GROUP->value) {
                    $this->jobLogger?->info(trans('bagisto::app.bagisto.export.errors.variant-group-flattened', [
                        'identifier' => $rowData['sku'],
                    ]));
                } else {
                    $this->recordSkipped($rowData['sku'], SkipReason::UNSUPPORTED_TYPE, [$rowData['type']]);
                }

                continue;
            }

            $builtForRow = 0;

            foreach ($this->jobFilters[JobFilter::CHANNEL->value] as $bagistoChannel => $unoPimChannel) {
                if (! isset($this->jobFilters[JobFilter::LOCALES->value][$bagistoChannel])) {
                    continue;
                }

                foreach ($this->jobFilters[JobFilter::LOCALES->value][$bagistoChannel] as $bagistoLocale => $unoPimLocale) {
                    $products[] = $this->processProductRow($rowData, $unoPimLocale, $bagistoLocale, $unoPimChannel, $bagistoChannel);
                    $builtForRow++;
                }
            }

            if ($builtForRow === 0) {
                $this->recordSkipped($rowData['sku'], SkipReason::NO_SCOPE_MATCH);
            }
        }
        usort($products, function ($a, $b) {
            $baseSkuA = $a['parent_sku'] ?? $a['sku'];
            $baseSkuB = $b['parent_sku'] ?? $b['sku'];

            if ($baseSkuA === $baseSkuB) {
                if ($a['type'] === $b['type']) {
                    return 0;
                }

                return ($a['type'] === ProductType::SIMPLE->value) ? -1 : 1;
            }

            return strcmp($baseSkuA, $baseSkuB);
        });

        return $products;
    }

    private function processProductRow(array $rowData, string $unoPimLocale, string $bagistoLocale, string $unoPimChannel, string $bagistoChannel): array
    {
        $simple = $config = $variants = null;

        if ($this->isSimpleProductWithoutParent($rowData)) {
            $simple = $this->createSimpleProductDataFormat($rowData);
        } elseif ($this->isConfigurableProduct($rowData)) {
            $config = $this->createConfigurableProductDataFormat($rowData);
        } elseif ($this->isSimpleProductWithParent($rowData)) {
            $variants = $this->createConfigurableVariantProductDataFormat($rowData);
        }

        return array_merge(
            $this->getFormatedProductData($rowData, $unoPimLocale, $bagistoLocale, $unoPimChannel, $bagistoChannel, $this->jobFilters[JobFilter::WITH_MEDIA->value]),
            $simple ?? $config ?? $variants
        );
    }

    private function isSimpleProductWithoutParent(array $rowData): bool
    {
        return $rowData['type'] === ProductType::SIMPLE->value && empty($rowData['parent']);
    }

    private function isConfigurableProduct(array $rowData): bool
    {
        return $rowData['type'] === ProductType::CONFIGURABLE->value && ! empty($rowData['super_attributes']);
    }

    private function isSimpleProductWithParent(array $rowData): bool
    {
        return $rowData['type'] === ProductType::SIMPLE->value && ! empty($rowData['parent']);
    }

    private function isExportableType(array $rowData): bool
    {
        return $this->isSimpleProductWithoutParent($rowData)
            || $this->isConfigurableProduct($rowData)
            || $this->isSimpleProductWithParent($rowData);
    }

    protected function getFormatedProductData(array $item, string $locale, string $bagistoLocale, string $channel, string $bagistoChannel, bool $withMedia): array
    {
        $data = $this->initializeProductData($item, $bagistoLocale, $bagistoChannel, $withMedia);

        $mergedFields = $this->mergeAllFields($item, $locale, $channel, $withMedia);

        $this->mapAttributesToBagisto($mergedFields);

        $this->applyFixedValues($mergedFields, $item['parent'] ?? null);

        $this->generateUrlKey($mergedFields);

        $this->applyAssociationsAndCategories($item, $mergedFields);

        if (isset($mergedFields['weight']) && $mergedFields['weight'] !== '') {
            $mergedFields['weight'] = (string) $mergedFields['weight'];
        }

        return array_merge($data, $mergedFields);
    }

    private function initializeProductData(array $item, string $bagistoLocale, string $bagistoChannel, bool $withMedia): array
    {
        return [
            'id'                    => $item['id'],
            'with_media'            => $withMedia,
            'type'                  => $item['type'],
            'locale'                => $bagistoLocale,
            'channel'               => $bagistoChannel,
            'attribute_family_code' => $item['attribute_family']['code'],
        ];
    }

    private function mergeAllFields(array $item, string $locale, string $channel, bool $withMedia): array
    {
        $commonFields = $this->getCommonFields($item);
        $localeSpecificFields = $this->getLocaleSpecificFields($item, $locale);
        $channelSpecificFields = $this->getChannelSpecificFields($item, $channel);
        $channelLocaleSpecificFields = $this->getChannelLocaleSpecificFields($item, $channel, $locale);

        $mergedFields = array_merge($commonFields, $localeSpecificFields, $channelSpecificFields, $channelLocaleSpecificFields);

        $this->scopeToSelectedAttributes($mergedFields);

        $identifier = $item['sku'] ?? '(no sku)';

        $this->resolveDamAssetPaths($mergedFields, $identifier);

        $this->handleAttributeType($mergedFields, $withMedia, $channel, $identifier);

        return $mergedFields;
    }

    private function scopeToSelectedAttributes(array &$mergedFields): void
    {
        $required = $this->requiredSourceCodes();

        foreach (array_keys($mergedFields) as $code) {
            if (in_array((string) $code, $required, true) || $this->isAttributeValueExported((string) $code)) {
                continue;
            }

            unset($mergedFields[$code]);
        }
    }

    private function requiredSourceCodes(): array
    {
        if ($this->requiredSourceCodeCache !== null) {
            return $this->requiredSourceCodeCache;
        }

        $codes = [self::UNOPIM_SKU_FIELD];

        foreach ($this->getRequiredBagistoFields() as $bagistoCode) {
            $codes[] = $bagistoCode;

            foreach (['standard_attribute', 'image_attribute'] as $section) {
                foreach ((array) ($this->mappingAttributes[$section]->mapped_value[$bagistoCode] ?? []) as $sourceCode) {
                    $codes[] = (string) $sourceCode;
                }
            }
        }

        return $this->requiredSourceCodeCache = array_values(array_unique(array_filter($codes)));
    }

    private function applyFixedValues(array &$mergedFields, $parent): void
    {
        $fixedValueStandard = $this->mappingAttributes['standard_attribute']->fixed_value ?? [];
        $fixedValueImage = $this->mappingAttributes['image_attribute']->fixed_value ?? [];

        $fixedValue = array_merge($fixedValueStandard, $fixedValueImage);

        foreach ($fixedValue as $bagistoAttribute => $value) {
            if (isset($mergedFields[$bagistoAttribute]) && empty($mergedFields[$bagistoAttribute])) {
                $mergedFields[$bagistoAttribute] = $value;
            }
            if (! isset($mergedFields[$bagistoAttribute])) {
                $mergedFields[$bagistoAttribute] = $bagistoAttribute === 'inventories'
                    ? 'default='.$value
                    : $value;
            }
        }

        foreach (config('bagisto-attributes', []) as $bagistoAttribute) {
            if (empty($bagistoAttribute['required']) || ! isset($bagistoAttribute['fixedValue'])) {
                continue;
            }

            $code = $bagistoAttribute['code'];

            if ($code === 'visible_individually') {
                continue;
            }

            if (! isset($mergedFields[$code]) || $mergedFields[$code] === '' || $mergedFields[$code] === null) {
                $mergedFields[$code] = $bagistoAttribute['fixedValue'];
            }
        }

        if (! isset($mergedFields['visible_individually']) || $mergedFields['visible_individually'] === '') {
            $mergedFields['visible_individually'] = ! empty($parent) ? '0' : '1';
        }
    }

    private function keepOwnIdentityFields(array $resolved, array $own): array
    {
        foreach ($resolved as $key => $value) {
            if (is_array($value)) {
                $resolved[$key] = $this->keepOwnIdentityFields($value, is_array($own[$key] ?? null) ? $own[$key] : []);

                continue;
            }

            if (! in_array($key, self::NON_INHERITABLE_FIELDS, true)) {
                continue;
            }

            if (array_key_exists($key, $own)) {
                $resolved[$key] = $own[$key];
            } else {
                unset($resolved[$key]);
            }
        }

        return $resolved;
    }

    private function getRequiredBagistoFields(): array
    {
        return array_column(
            array_filter(config('bagisto-attributes', []), fn ($attribute) => ! empty($attribute['required'])),
            'code'
        );
    }

    private function resolveDamAssetPaths(array &$mergedFields, string $identifier = '(no sku)'): void
    {
        foreach ($mergedFields as $code => $value) {
            if (empty($value)) {
                continue;
            }

            $attribute = $this->attributeRepository->where('code', $code)->first();

            if (! $attribute) {
                continue;
            }

            if (($attribute->type ?? null) !== self::DAM_ASSET_ATTRIBUTE_TYPE) {
                continue;
            }

            $ids = is_array($value) ? $value : array_filter(array_map('trim', explode(',', (string) $value)), 'strlen');

            if (empty($ids)) {
                continue;
            }

            $assets = $this->damAssets($ids);

            $paths = [];

            foreach ($ids as $id) {
                $asset = $assets->get($id);

                if (! $asset) {
                    $this->recordExcludedMedia($identifier, SkipReason::MEDIA_NOT_FOUND, ['#'.$id]);

                    continue;
                }

                if (! DamFileType::isImage($asset->file_type)) {
                    $this->recordExcludedMedia($identifier, SkipReason::UNSUPPORTED_MEDIA_TYPE, [$asset->file_name ?? $asset->path]);

                    continue;
                }

                if (! BagistoImageFormat::accepts($asset->path)) {
                    $this->recordExcludedMedia($identifier, SkipReason::UNSUPPORTED_IMAGE_FORMAT, [$asset->file_name ?? $asset->path]);

                    continue;
                }

                if ($asset->path) {
                    $paths[] = $asset->path;
                }
            }

            if (empty($paths)) {
                unset($mergedFields[$code]);

                continue;
            }

            $mergedFields[$code] = count($paths) > 1 ? $paths : $paths[0];
        }
    }

    protected function damAssets(array $ids): Collection
    {
        $repository = $this->assetRepository ??= class_exists(self::DAM_ASSET_REPOSITORY)
            ? app(self::DAM_ASSET_REPOSITORY)
            : null;

        if (! $repository) {
            return new Collection;
        }

        return $repository->findWhereIn('id', $ids)->keyBy('id');
    }

    private function mapAttributesToBagisto(array &$mergedFields): void
    {
        $mapAttributesStandard = $this->mappingAttributes['standard_attribute']->mapped_value ?? [];
        $mapAttributesImage = $this->mappingAttributes['image_attribute']->mapped_value ?? [];

        $mapAttributes = array_merge($mapAttributesStandard, $mapAttributesImage);

        $mapAttributeValues = [];

        foreach ($mapAttributes as $bagistoAttribute => $unpoimAttribute) {
            if (is_array($unpoimAttribute)) {
                $combinedValues = [];

                foreach ($unpoimAttribute as $unpoimAttributeCode) {
                    if (
                        isset($mergedFields[$unpoimAttributeCode])
                        && $mergedFields[$unpoimAttributeCode] !== ''
                        && $mergedFields[$unpoimAttributeCode] !== null
                    ) {
                        $combinedValues[] = is_array($mergedFields[$unpoimAttributeCode])
                            ? implode(',', $mergedFields[$unpoimAttributeCode])
                            : $mergedFields[$unpoimAttributeCode];
                    }
                }

                if ($combinedValues !== []) {
                    $mapAttributeValues[$bagistoAttribute] = implode(',', $combinedValues);
                } elseif (isset($mergedFields[$bagistoAttribute]) && $mergedFields[$bagistoAttribute] !== null && $mergedFields[$bagistoAttribute] !== '') {
                    $mapAttributeValues[$bagistoAttribute] = $mergedFields[$bagistoAttribute];
                }

                continue;
            }

            if (isset($mergedFields[$unpoimAttribute])) {
                $mapAttributeValues[$bagistoAttribute] = $bagistoAttribute === 'inventories'
                    ? 'default='.$mergedFields[$unpoimAttribute]
                    : $mergedFields[$unpoimAttribute];

                continue;
            }

            if (is_string((string) $unpoimAttribute) && trim((string) $unpoimAttribute) !== '') {
                $attributeExists = (bool) $this->attributeRepository->where('code', $unpoimAttribute)->first();

                if (! $attributeExists) {
                    $mapAttributeValues[$bagistoAttribute] = $bagistoAttribute === 'inventories'
                        ? 'default='.$unpoimAttribute
                        : $unpoimAttribute;
                }
            }
        }

        $mergedFields = $mapAttributeValues;
    }

    private function generateUrlKey(array &$mergedFields): void
    {
        if (! empty($mergedFields['url_key'])) {
            $slug = $this->createSlug($mergedFields['url_key']);
            $slugCount = array_count_values($this->urlKey)[$slug] ?? 0;
            $mergedFields['url_key'] = $slugCount ? $slug.'-'.$slugCount : $slug;
            $this->urlKey[] = $slug;
        }
    }

    private function applyAssociationsAndCategories(array $item, array &$mergedFields): void
    {
        if (! empty($this->jobFilters[JobFilter::WITH_ASSOCIATIONS->value])) {
            $this->getAssociationsData($item, $mergedFields);
        }

        $this->getCategoryFormatData($item, $mergedFields);
    }

    protected function createSimpleProductDataFormat(array $item): array
    {
        return [
            'id'                    => $item['id'],
            'sku'                   => $this->bagistoSkuFor($item),
            'type'                  => $item['type'],
            'attribute_family_code' => $item['attribute_family']['code'],
        ];
    }

    protected function bagistoSkuFor(array $item): string
    {
        $code = $this->mappedSkuAttributeCode();

        if ($code === self::UNOPIM_SKU_FIELD) {
            return (string) ($item[self::UNOPIM_SKU_FIELD] ?? '');
        }

        $mapped = $this->getCommonFields($item)[$code] ?? null;

        return $mapped === null || $mapped === ''
            ? (string) ($item[self::UNOPIM_SKU_FIELD] ?? '')
            : (string) $mapped;
    }

    protected function mappedSkuAttributeCode(): string
    {
        $mapped = $this->mappingAttributes['standard_attribute']->mapped_value[self::UNOPIM_SKU_FIELD] ?? null;

        return is_string($mapped) && $mapped !== '' ? $mapped : self::UNOPIM_SKU_FIELD;
    }

    protected function productIdsByBagistoSku(array $items, array $skus): array
    {
        $idsBySku = array_column($items, 'id', 'sku');

        $resolved = [];

        foreach ($skus as $sku) {
            if (isset($idsBySku[$sku])) {
                $resolved[$sku] = $idsBySku[$sku];
            }
        }

        return $resolved;
    }

    protected function createConfigurableProductDataFormat(array $item): array
    {
        $formatData = $this->createSimpleProductDataFormat($item);

        $formatData['configurable_variants'] = $this->getSuperAttributes($item);

        return $formatData;
    }

    protected function createConfigurableVariantProductDataFormat($item): array
    {
        $formatData = $this->createSimpleProductDataFormat($item);

        $formatData['parent_sku'] = $this->bagistoSkuFor($item['parent']);

        return $formatData;
    }

    public function getSuperAttributes($item): ?string
    {
        $superAttributeCodes = array_column($item['super_attributes'] ?? [], 'code');

        if ($superAttributeCodes === []) {
            $this->jobLogger?->warning(
                'Product '.($item['sku'] ?? '(no sku)').' has no super attributes, so no variants can be linked.'
            );

            return '';
        }

        $newFormatData = [];

        foreach ($this->collectVariantLeaves($item, $superAttributeCodes) as $leaf) {
            $missingAxes = array_values(array_diff($superAttributeCodes, array_keys($leaf['axes'])));

            if ($missingAxes !== []) {
                $this->jobLogger?->warning(
                    'Variant '.$leaf['sku'].' not exported: no value for super attribute(s) '
                    .implode(', ', $missingAxes).'.'
                );

                continue;
            }

            $formatData = ["sku={$leaf['sku']}"];

            foreach ($superAttributeCodes as $attribute) {
                $formatData[] = "{$attribute}={$leaf['axes'][$attribute]}";
            }

            $newFormatData[] = implode(',', $formatData);
        }

        if ($newFormatData === []) {
            $this->jobLogger?->warning(
                'Product '.($item['sku'] ?? '(no sku)').' exported without variants: none of its variants carried a '
                .'complete set of '.implode(', ', $superAttributeCodes).'.'
            );
        }

        return implode('|', $newFormatData);
    }

    private function collectVariantLeaves(array $node, array $axisCodes, array $inherited = [], int $depth = 0): array
    {
        if ($depth >= self::MAX_VARIANT_DEPTH) {
            $this->jobLogger?->warning(
                'Variant tree under '.($node['sku'] ?? '(no sku)').' is deeper than '.self::MAX_VARIANT_DEPTH
                .' levels; the levels below were not exported.'
            );

            return [];
        }

        $leaves = [];

        foreach ($node['variants'] ?? [] as $child) {
            $axes = array_merge(
                $inherited,
                array_intersect_key($this->getCommonFields($child), array_flip($axisCodes))
            );

            if (($child['type'] ?? null) !== ProductType::VARIANT_GROUP->value) {
                $leaves[] = ['sku' => $this->bagistoSkuFor($child) ?: '(no sku)', 'axes' => $axes];

                continue;
            }

            if (empty($child['variants'])) {
                $this->jobLogger?->warning(
                    'Variant group '.($child['sku'] ?? '(no sku)').' has no variants of its own, so it contributes nothing.'
                );

                continue;
            }

            $leaves = array_merge($leaves, $this->collectVariantLeaves($child, $axisCodes, $axes, $depth + 1));
        }

        return $leaves;
    }

    protected function handleAttributeType(array &$mergedFields, bool $withMedia, string $channel, string $identifier = '(no sku)'): void
    {
        foreach ($mergedFields as $attributeCode => $attributeValue) {
            $attribute = $this->attributeRepository->where('code', $attributeCode)->first();
            if (! $attribute) {
                continue;
            }
            switch ($attribute->type) {
                case self::DAM_ASSET_ATTRIBUTE_TYPE:
                    if ($withMedia && $attributeValue !== '' && $attributeValue !== null) {
                        if (is_array($attributeValue)) {
                            $mergedFields[$attributeCode] = implode(',', array_map(fn ($path) => $this->makeDamPublicUrl((string) $path), $attributeValue));
                        } else {
                            $mergedFields[$attributeCode] = $this->makeDamPublicUrl((string) $attributeValue);
                        }
                    } else {
                        unset($mergedFields[$attributeCode]);
                    }
                    break;
                case AttributeTypes::GALLERY_ATTRIBUTE_TYPE:
                    if ($withMedia) {
                        $paths = is_array($attributeValue) ? $attributeValue : preg_split('/[\s,]+/', (string) $attributeValue);
                        $paths = array_values(array_filter(
                            array_map(
                                fn ($path) => $this->resolveMediaForExport((string) $path, $identifier),
                                (array) $paths
                            ),
                            'strlen'
                        ));

                        if ($paths === []) {
                            unset($mergedFields[$attributeCode]);
                        } else {
                            $mergedFields[$attributeCode] = implode(',', $paths);
                        }
                    } else {
                        unset($mergedFields[$attributeCode]);
                    }
                    break;
                case AttributeTypes::IMAGE_ATTRIBUTE_TYPE:
                case AttributeTypes::FILE_ATTRIBUTE_TYPE:
                    if ($withMedia) {
                        $path = is_array($attributeValue) ? ($attributeValue[0] ?? null) : $attributeValue;

                        $resolved = $path ? $this->resolveMediaForExport((string) $path, $identifier) : null;

                        if ($resolved) {
                            $mergedFields[$attributeCode] = $resolved;
                        } else {
                            unset($mergedFields[$attributeCode]);
                        }
                    } else {
                        unset($mergedFields[$attributeCode]);
                    }
                    break;

                case AttributeTypes::PRICE_ATTRIBUTE_TYPE:
                    $channelData = $this->channelRepository->where('code', $channel)->with(['locales', 'currencies'])->first()->toArray();
                    foreach ($channelData['currencies'] as $currency) {
                        if (! empty($attributeValue[$currency['code']])) {
                            $mergedFields[$attributeCode] = is_array($attributeValue) ? $attributeValue[$currency['code']] : $attributeValue;
                        }
                    }

                    break;

                case FieldValidator::BOOLEAN_FIELD_TYPE:
                    $mergedFields[$attributeCode] = $this->checkBooleanConversion($attributeValue) ? 1 : 0;
                    break;

                case self::MEASUREMENT_ATTRIBUTE_TYPE:
                    $mergedFields[$attributeCode] = is_array($attributeValue)
                        ? ($attributeValue['base_data'] ?? '')
                        : $attributeValue;
                    break;

                default:
                    if (in_array($attribute->type, ['multiselect', 'checkbox', 'select'])) {
                        $mergedFields[$attributeCode] = is_array($attributeValue) ? implode(',', $attributeValue) : $attributeValue;
                    }
                    break;
            }
        }

        $bagistoConfig = config('bagisto-attributes', []);

        $multiTypeMap = [];
        foreach ($bagistoConfig as $cfg) {
            if (empty($cfg['multiple']) || empty($cfg['type'])) {
                continue;
            }
            $types = array_map('trim', explode(',', $cfg['type']));
            $multiTypeMap[$cfg['code']] = $types;
        }

        foreach ($multiTypeMap as $bagistoCode => $types) {
            $combined = [];

            foreach ($this->sourceCodesInMappedOrder($bagistoCode, $mergedFields) as $code) {
                $val = $mergedFields[$code];

                if ($val === null || $val === '' || $val === []) {
                    continue;
                }

                $attr = $this->attributeRepository->where('code', $code)->first();

                if ($attr && in_array($attr->type, $types, true)) {
                    $combined[] = is_array($val) ? implode(',', $val) : $val;
                    unset($mergedFields[$code]);
                }
            }

            if ($combined !== []) {
                $mergedFields[$bagistoCode] = implode(',', $combined);
            }
        }
    }

    private function sourceCodesInMappedOrder(string $bagistoCode, array $mergedFields): array
    {
        $mapped = (array) (
            $this->mappingAttributes['image_attribute']->mapped_value[$bagistoCode]
            ?? $this->mappingAttributes['standard_attribute']->mapped_value[$bagistoCode]
            ?? []
        );

        $codes = array_keys($mergedFields);

        $ordered = array_values(array_intersect($mapped, $codes));

        return array_merge($ordered, array_values(array_diff($codes, $ordered)));
    }

    protected function makeDamPublicUrl(string $filePath): string
    {
        if (config('filesystems.default') === 's3') {
            return $this->resolveMediaUrl($filePath);
        }

        return URL::temporarySignedRoute(
            'bagisto.asset.fetch',
            now()->addMinutes($this->temporaryUrlTtl()),
            ['path' => $filePath]
        );
    }

    protected function getCategoryFormatData(array $item, &$mergedFields): void
    {
        if (! empty($item['values']['categories']) && is_array($item['values']['categories'])) {
            $categoryData = [];
            foreach ($item['values']['categories'] as $code) {
                $category = $this->categoryRepository->where('code', $code)->first();
                if (! $category) {
                    continue;
                }

                $externalId = $this->getMapping($this->credential['id'], $category->id, null, null, null, 'category')->external_id ?? null;

                if ($externalId) {
                    $categoryData[] = $externalId;
                }
            }

            $mergedFields['categories'] = implode('/', $categoryData);
        }
    }

    protected function getAssociationsData(array $item, array &$mergedFields): void
    {
        if ($upSells = $this->getAssociationsFormat($item, 'up_sells')) {
            $mergedFields['up_sell_skus'] = $upSells;
        }
        if ($crossSells = $this->getAssociationsFormat($item, 'cross_sells')) {
            $mergedFields['cross_sell_skus'] = $crossSells;
        }
        if ($relatedProducts = $this->getAssociationsFormat($item, 'related_products')) {
            $mergedFields['related_skus'] = $relatedProducts;
        }
    }

    protected function getAssociationsFormat(array $item, string $type): ?string
    {
        $association = $this->getAssociations($item, $type);

        if (! $association) {
            return null;
        }

        return implode(',', $this->resolveExistingSkus($this->parseIdentifiers($association)));
    }

    protected function resolveExistingSkus(array $skus): array
    {
        $unresolved = array_values(array_diff($skus, array_keys($this->knownSkus)));

        if ($unresolved !== []) {
            $isDefaultMapping = $this->mappedSkuAttributeCode() === self::UNOPIM_SKU_FIELD;

            $columns = $isDefaultMapping ? ['id', 'sku'] : ['id', 'sku', 'values'];

            $found = [];

            foreach ($this->productRepository->whereIn('sku', $unresolved)->get($columns) as $product) {
                $found[$product->sku] = $isDefaultMapping
                    ? $product->sku
                    : $this->bagistoSkuFor($product->toArray());
            }

            foreach ($unresolved as $sku) {
                $this->knownSkus[$sku] = $found[$sku] ?? null;
            }
        }

        return array_values(array_filter(array_map(
            fn (string $sku): ?string => $this->knownSkus[$sku],
            $skus
        )));
    }

    protected function resolveMediaForExport(string $mediaPath, string $identifier): ?string
    {
        if (! BagistoImageFormat::accepts($mediaPath)) {
            $this->recordExcludedMedia($identifier, SkipReason::UNSUPPORTED_IMAGE_FORMAT, [basename($mediaPath)]);

            return null;
        }

        $resolved = $this->getExistingFilePath($mediaPath);

        if (! $resolved) {
            $this->recordExcludedMedia($identifier, SkipReason::MEDIA_NOT_FOUND, [basename($mediaPath)]);
        }

        return $resolved;
    }

    protected function getExistingFilePath(string $mediaPath): ?string
    {
        if (config('filesystems.default') === 's3') {
            $disk = Storage::disk('s3');

            if (! $disk->exists($mediaPath)) {
                return null;
            }

            return $this->resolveMediaUrl($mediaPath);
        }

        if (! Storage::exists($mediaPath)) {
            return null;
        }

        return Storage::url($this->encodeMediaPath($mediaPath));
    }

    protected function temporaryUrlTtl(): int
    {
        $ttl = (int) config('bagisto-media.temporary_url_ttl', 60);

        return $ttl > 0 ? $ttl : 60;
    }

    protected function resolveMediaUrl(string $mediaPath): string
    {
        $visibility = config('filesystems.disks.s3.visibility') ?? 'public';

        if ($visibility === 'private') {
            return Storage::disk('s3')->temporaryUrl($mediaPath, now()->addMinutes($this->temporaryUrlTtl()));
        }

        $bucketUrl = config('filesystems.disks.s3.url');

        if (! empty($bucketUrl)) {
            return rtrim($bucketUrl, '/').'/'.$this->encodeMediaPath($mediaPath);
        }

        return Storage::disk('s3')->url(ltrim($mediaPath, '/'));
    }

    protected function encodeMediaPath(string $mediaPath): string
    {
        return implode('/', array_map('rawurlencode', explode('/', ltrim($mediaPath, '/'))));
    }

    protected function createSlug(string $name): string
    {
        return trim(preg_replace('/[^a-z0-9]+/i', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $name))), '-');
    }

    protected function checkBooleanConversion(mixed $value): bool
    {
        return ($value === 'false' || (bool) $value === false) ? false : true;
    }
}
