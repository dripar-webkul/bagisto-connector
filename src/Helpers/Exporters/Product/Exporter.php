<?php

namespace Webkul\Bagisto\Helpers\Exporters\Product;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Webkul\Attribute\Repositories\AttributeOptionRepository;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Rules\AttributeTypes;
use Webkul\Bagisto\Enums\Export\CacheType;
use Webkul\Bagisto\Enums\Services\MethodType;
use Webkul\Bagisto\Repositories\AttributeMappingRepository;
use Webkul\Bagisto\Repositories\BagistoDataMapping;
use Webkul\Bagisto\Repositories\CredentialRepository;
use Webkul\Bagisto\Traits\ApiRequest as ApiRequestTrait;
use Webkul\Bagisto\Traits\Credential as CredentialTrait;
use Webkul\Bagisto\Traits\ExportSummary as ExportSummaryTrait;
use Webkul\Bagisto\Traits\Mapping as MappingTrait;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Category\Validator\FieldValidator;
use Webkul\Core\Repositories\ChannelRepository;
use Webkul\DataTransfer\Contracts\JobTrackBatch as JobTrackBatchContract;
use Webkul\DataTransfer\Helpers\Export;
use Webkul\DataTransfer\Helpers\Exporters\Product\Exporter as AbstractExporter;
use Webkul\DataTransfer\Helpers\Sources\Export\ProductSource;
use Webkul\DataTransfer\Jobs\Export\File\FlatItemBuffer as FileExportFileBuffer;
use Webkul\DataTransfer\Repositories\JobTrackBatchRepository;
use Webkul\Product\Repositories\ProductRepository;

class Exporter extends AbstractExporter
{
    use ApiRequestTrait;
    use CredentialTrait;
    use ExportSummaryTrait;
    use MappingTrait;

    protected const ENTITY_TYPE = 'bulk_product';

    protected const VARIANT = 'variant';

    public const BATCH_SIZE = 100;

    /*
     * For check initialization
     */
    protected bool $initialized = false;

    /*
     * For exporting file
     */
    protected bool $exportsFile = false;

    /*
     * For mappingAttributes
     */
    protected array $mappingAttributes = [];

    /**
     * Current crenetial.
     *
     * @var array
     */
    protected $credential = [];

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * @var array
     */
    protected $jobFilters = [];

    /**
     * @var array
     */
    protected $urlKey = [];

    /**
     * Create a new instance of the exporter.
     */
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

    /**
     * Initializes the data for the export process.
     */
    public function initialize(): void
    {
        $this->initializeCredential($this->getFilters());
        $this->initializeMappingAttributes();
        $this->initializeJobFilters();
    }

    /**
     * Initializes mappingAttribute for the export process.
     *
     * @return void
     */
    public function initializeMappingAttributes()
    {
        $this->mappingAttributes = Cache::get(CacheType::ATTRIBUTE_MAPPING->value, []);
        if (empty($this->mappingAttributes)) {
            $this->mappingAttributes = [
                'standard_attribute' => $this->attributeMappingRepository->findByField('section', 'standard_attribute')->first(),
                'image_attribute'    => $this->attributeMappingRepository->findByField('section', 'image_attribute')->first(),
            ];

            Cache::put(CacheType::ATTRIBUTE_MAPPING->value, $this->mappingAttributes, Env('SESSION_LIFETIME'));
        }
    }

    /**
     * Initializes Job Filters for the export process.
     *
     * @return void
     */
    public function initializeJobFilters()
    {
        $this->jobFilters = Cache::get(CacheType::PRODUCT_JOB_FILTERS->value, []);

        if (empty($this->jobFilters)) {
            $filters = $this->getFilters();

            $filtersChannels = ! empty($filters['channel']) ? explode(',', $filters['channel']) : [];
            $filtersLocales = ! empty($filters['locale']) ? explode(',', $filters['locale']) : [];

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
                'withMedia' => $filters['with_media'],
                'channel'   => $mappedBagistoChannels,
                'locales'   => $exportBagistoLocales,
            ];

            Cache::put(CacheType::PRODUCT_JOB_FILTERS->value, $this->jobFilters, env('SESSION_LIFETIME'));
        }
    }

    /**
     * Start the export process
     */
    public function exportBatch(JobTrackBatchContract $batch, $filePath): bool
    {
        if (! $this->initialized) {
            $this->initialize();
            $this->initialized = true;
        }

        $preparedData = $this->prepareBagistoProducts($batch, $filePath);

        $this->write($preparedData, $batch->id);

        /**
         * Update export batch process state summary
         */
        $this->updateBatchState($batch->id, Export::STATE_PROCESSED);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function getResults(): CollectionCursor
    {
        $filters = $this->getFilters();

        $query = $this->source->with(['attribute_family', 'parent', 'super_attributes', 'variants']);

        if (! empty($filters['sku'])) {
            $query->whereIn('sku', $this->convertCommaSeparatedToArray($filters['sku']));
        }

        if (! empty($filters['type'])) {
            $query->whereIn('type', $this->convertCommaSeparatedToArray($filters['type']));
        }

        if (! empty($filters['family'])) {
            $familyIds = \DB::table('attribute_families')
                ->whereIn('code', $this->convertCommaSeparatedToArray($filters['family']))
                ->pluck('id')
                ->toArray();

            if (! empty($familyIds)) {
                $query->whereIn('attribute_family_id', $familyIds);
            }
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $status = ($filters['status'] == 't') ? 1 : 0;
            $query->where('status', $status);
        }

        $products = $query->get(['id', 'sku', 'type']);

        $configurableIds = $products->where('type', 'configurable')->pluck('id')->filter()->all();

        if (! empty($configurableIds)) {
            $variants = $this->productRepository->whereIn('parent_id', $configurableIds)->get(['id', 'sku', 'type']);

            $products = $products->concat($variants)->unique('sku')->values();
        }

        return new CollectionCursor($products->toArray());
    }

    public function write(array $items, int $batchId): void
    {
        try {
            $this->setApiRequest(MethodType::POST->value, self::ENTITY_TYPE, $items, []);

            $skusAttempted = array_values(array_unique(array_column($items, 'sku')));

            if (! empty($this->lastApiErrors)) {
                $this->skippedItemsCount += count($skusAttempted);

                $this->jobLogger?->warning(
                    'Bulk product batch skipped ['.implode(', ', $skusAttempted).']: '.json_encode($this->lastApiErrors)
                );

                return;
            }

            $products = $this->productRepository->whereIn('sku', $skusAttempted)->get(['id', 'sku']);

            foreach ($products as $product) {
                if ($this->getMapping($this->credential['id'], $product->id, null, null, null, self::ENTITY_TYPE)) {
                    $this->updatedItemsCount++;
                } else {
                    $this->createdItemsCount++;
                    $this->setMapping($this->credential['id'], $product->id, 0, $batchId, null, self::ENTITY_TYPE);
                }
            }
        } catch (\Exception $e) {
            $this->jobLogger->warning($e);
        }
    }

    public function prepareBagistoProducts(JobTrackBatchContract $batch, $filePath): array
    {
        $products = [];
        $skus = array_column($batch->data, 'sku');
        $allProducts = $this->productRepository
            ->with(['attribute_family', 'parent', 'super_attributes', 'variants'])
            ->whereIn('sku', $skus)
            ->get();

        foreach ($allProducts as $productModel) {
            $rowData = $productModel->toArray();
            $rowData['values'] = $productModel->values ?? [];

            $builtForRow = 0;

            foreach ($this->jobFilters['channel'] as $bagistoChannel => $unoPimChannel) {
                if (! isset($this->jobFilters['locales'][$bagistoChannel])) {
                    continue;
                }

                foreach ($this->jobFilters['locales'][$bagistoChannel] as $bagistoLocale => $unoPimLocale) {
                    $products[] = $this->processProductRow($rowData, $unoPimLocale, $bagistoLocale, $unoPimChannel, $bagistoChannel);
                    $builtForRow++;
                }
            }

            if ($builtForRow === 0) {
                $this->skippedItemsCount++;
                $this->jobLogger?->warning("Product {$rowData['sku']} not exported: no Bagisto channel/locale mapping matched the selected channel and locale filters.");
            }
        }
        usort($products, function ($a, $b) {
            $baseSkuA = $a['parent_sku'] ?? $a['sku'];
            $baseSkuB = $b['parent_sku'] ?? $b['sku'];

            if ($baseSkuA === $baseSkuB) {
                if ($a['type'] === $b['type']) {
                    return 0;
                }

                return ($a['type'] === 'simple') ? -1 : 1;
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
            $this->getFormatedProductData($rowData, $unoPimLocale, $bagistoLocale, $unoPimChannel, $bagistoChannel, $this->jobFilters['withMedia']),
            $simple ?? $config ?? $variants
        );
    }

    private function isSimpleProductWithoutParent(array $rowData): bool
    {
        return $rowData['type'] === 'simple' && empty($rowData['parent']);
    }

    private function isConfigurableProduct(array $rowData): bool
    {
        return $rowData['type'] === 'configurable' && ! empty($rowData['super_attributes']);
    }

    private function isSimpleProductWithParent(array $rowData): bool
    {
        return $rowData['type'] === 'simple' && ! empty($rowData['parent']);
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

        $this->handleAttributeType($mergedFields, $withMedia, $channel);

        return $mergedFields;
    }

    private function applyFixedValues(array &$mergedFields, $parent): void
    {
        $fixedValue = $this->mappingAttributes['standard_attribute']->fixed_value ?? [];

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

        if (! isset($mergedFields['visible_individually']) || $mergedFields['visible_individually'] === '') {
            $mergedFields['visible_individually'] = ! empty($parent) ? '0' : '1';
        }
    }

    private function mapAttributesToBagisto(array &$mergedFields): void
    {
        $mapAttributes = $this->mappingAttributes['standard_attribute']->mapped_value ?? [];
        $mapAttributeValues = [];
        foreach ($mapAttributes as $bagistoAttribute => $unpoimAttribute) {
            if (isset($mergedFields[$unpoimAttribute])) {
                $mapAttributeValues[$bagistoAttribute] = $bagistoAttribute === 'inventories'
                    ? 'default='.$mergedFields[$unpoimAttribute]
                    : $mergedFields[$unpoimAttribute];
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
        $this->getAssociationsData($item, $mergedFields);
        $this->getCategoryFormatData($item, $mergedFields);
    }

    protected function createSimpleProductDataFormat(array $item): array
    {
        return [
            'id'                    => $item['id'],
            'sku'                   => $item['sku'],
            'type'                  => $item['type'],
            'attribute_family_code' => $item['attribute_family']['code'],
        ];
    }

    protected function createConfigurableProductDataFormat(array $item): array
    {
        $formatData = $this->createSimpleProductDataFormat($item);

        $formatData['configurable_variants'] = $this->getSuperAttributes($item);

        return $formatData;
    }

    protected function createConfigurableVariantProductDataFormat($item)
    {
        $formatData = $this->createSimpleProductDataFormat($item);

        $formatData['parent_sku'] = $item['parent']['sku'];

        return $formatData;
    }

    public function getSuperAttributes($item): ?string
    {
        $newFormatData = [];
        $superAttributeCodes = [];

        foreach ($item['super_attributes'] as $superAttribute) {
            $superAttributeCodes[] = $superAttribute['code'];
        }

        foreach ($item['variants'] as $variant) {
            $formatData = [];
            $formatData[] = "sku={$variant['sku']}";
            $commonFields = $this->getCommonFields($variant);
            foreach ($superAttributeCodes as $key => $attribute) {
                $formatData[] = "{$attribute}={$commonFields[$attribute]}";
            }
            $newFormatData[] = implode(',', $formatData);
        }

        return implode('|', $newFormatData);
    }

    protected function handleAttributeType(array &$mergedFields, bool $withMedia, string $channel): void
    {
        foreach ($mergedFields as $attributeCode => $attributeValue) {
            $attribute = $this->attributeRepository->where('code', $attributeCode)->first();
            if (! $attribute) {
                continue;
            }
            switch ($attribute->type) {
                case AttributeTypes::GALLERY_ATTRIBUTE_TYPE:
                    if ($withMedia) {
                        $mergedFields[$attributeCode] = array_map(fn ($path) => $this->getExistingFilePath($path), (array) $attributeValue);
                        $mergedFields[$attributeCode] = implode(',', $mergedFields[$attributeCode]);
                    } else {
                        unset($mergedFields[$attributeCode]);
                    }
                    break;
                case AttributeTypes::IMAGE_ATTRIBUTE_TYPE:
                case AttributeTypes::FILE_ATTRIBUTE_TYPE:
                    if ($withMedia) {
                        $mergedFields[$attributeCode] = is_array($attributeValue) ? $this->getExistingFilePath($attributeValue[0]) : $this->getExistingFilePath($attributeValue);
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

                default:
                    if (in_array($attribute->type, ['multiselect', 'checkbox', 'select'])) {
                        $mergedFields[$attributeCode] = is_array($attributeValue) ? implode(',', $attributeValue) : $attributeValue;
                    }
                    break;
            }
        }
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
        $associations = [];
        $newAssociations = null;
        if ($association = $this->getAssociations($item, $type)) {
            $products = explode(',', $association);
            foreach ($products as $sku) {
                $productData = $this->productRepository->where('sku', $sku)->first();
                if ($productData) {
                    $associations[] = $productData->sku;
                }
            }
            $newAssociations = implode(',', $associations);
        }

        return $newAssociations;
    }

    protected function getExistingFilePath(string $mediaPath): ?string
    {
        return Storage::exists($mediaPath) ? Storage::url($mediaPath) : null;
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
