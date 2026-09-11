<?php

namespace Webkul\Bagisto\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Attribute\Repositories\AttributeFamilyRepository;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Bagisto\Repositories\CredentialRepository;
use Webkul\Bagisto\Traits\ApiRequest;
use Webkul\Core\Repositories\ChannelRepository;
use Webkul\Core\Repositories\CurrencyRepository;
use Webkul\Core\Repositories\LocaleRepository;

class OptionController extends Controller
{
    use ApiRequest;

    const PER_PAGE = 20;

    const DEFAULT_PAGE = 1;

    public function __construct(
        protected CredentialRepository $bagistoRepository,
        protected AttributeRepository $attributeRepository,
        protected ChannelRepository $channelRepository,
        protected CurrencyRepository $currencyRepository,
        protected LocaleRepository $localeRepository,
        protected AttributeFamilyRepository $attributeFamilyRepository,
    ) {}

    public function listBagistoCredential(): JsonResponse
    {
        $queryParams = request()->except(['page', 'query', 'entityName', 'attributeId']);
        $query = request()->get('query');

        $bagistoRepository = $this->applySearchIdentifiers($this->bagistoRepository, $queryParams, 'id');

        $bagistoRepository = $this->searchByCode($bagistoRepository, $query, 'shop_url');

        return $this->respondWithOptions($bagistoRepository->get()->toArray());
    }

    public function listChannel(): JsonResponse
    {
        $queryParams = request()->except(['page', 'query', 'entityName', 'attributeId']);
        $query = request()->get('query');

        $channelRepository = $this->applySearchIdentifiers($this->channelRepository, $queryParams, 'code');

        $channelRepository = $this->searchByCode($channelRepository, $query);

        $allActivateChannel = $channelRepository->get()->toArray();

        foreach ($allActivateChannel as $key => $channel) {
            $allActivateChannel[$key]['name'] = ! empty($channel['name']) ? $channel['name'] : $channel['code'];
        }

        return $this->respondWithOptions($allActivateChannel);
    }

    public function listCurrency(): JsonResponse
    {
        $queryParams = request()->except(['page', 'query', 'entityName', 'attributeId']);
        $query = request()->get('query');

        $currencyRepository = $this->applySearchIdentifiers($this->currencyRepository->where('status', 1), $queryParams, 'code');

        $currencyRepository = $this->searchByCode($currencyRepository, $query);

        return $this->respondWithOptions($currencyRepository->get()->toArray());
    }

    public function listLocale(): JsonResponse
    {
        $queryParams = request()->except(['page', 'query', 'entityName', 'attributeId']);
        $query = request()->get('query');

        $localeRepository = $this->applySearchIdentifiers($this->localeRepository->where('status', 1), $queryParams, 'code');

        $localeRepository = $this->searchByCode($localeRepository, $query);

        return $this->respondWithOptions($localeRepository->get()->toArray());
    }

    public function listFamily(): JsonResponse
    {
        $queryParams = request()->except(['page', 'query', 'entityName', 'attributeId']);
        $query = request()->get('query');

        $attributeFamilyRepository = $this->applySearchIdentifiers($this->attributeFamilyRepository, $queryParams, 'code');
        $attributeFamilyRepository = $this->searchByCode($attributeFamilyRepository, $query);

        $allActivateFamilies = $attributeFamilyRepository->get()->toArray();

        foreach ($allActivateFamilies as $key => $family) {
            $allActivateFamilies[$key]['name'] = ! empty($family['name']) ? $family['name'] : $family['code'];
        }

        return $this->respondWithOptions($allActivateFamilies);
    }

    public function listType(): JsonResponse
    {
        $queryParams = request()->except(['page', 'query', 'entityName', 'attributeId']);
        $query = request()->get('query');

        $supportedTypes = config('product_types');

        if (isset($queryParams['identifiers']['values'])) {
            $types = [];

            foreach ($supportedTypes as $id => $type) {
                $label = trans($type['name']);
                if (in_array($id, $queryParams['identifiers']['values'])) {
                    $types[] = [
                        'id'    => $id,
                        'label' => $label,
                    ];
                }
            }

            return $this->respondWithOptions($types);
        }

        $types = [];

        foreach ($supportedTypes as $id => $type) {
            $label = trans($type['name']);

            if (! $query || stripos($label, $query) !== false) {
                $types[] = [
                    'id'    => $id,
                    'label' => $label,
                ];
            }
        }

        return $this->respondWithOptions($types);
    }

    protected function respondWithOptions(array $options): JsonResponse
    {
        $options = array_values($options);

        if (! empty(request('identifiers.values'))) {
            return new JsonResponse([
                'options'  => $options,
                'page'     => self::DEFAULT_PAGE,
                'lastPage' => self::DEFAULT_PAGE,
            ]);
        }

        $page = max(self::DEFAULT_PAGE, (int) request('page', self::DEFAULT_PAGE));

        $lastPage = max(self::DEFAULT_PAGE, (int) ceil(count($options) / self::PER_PAGE));

        return new JsonResponse([
            'options'  => array_slice($options, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'page'     => $page,
            'lastPage' => $lastPage,
        ]);
    }

    protected function applySearchIdentifiers($repository, array $queryParams, ?string $code = null): mixed
    {
        $searchIdentifiers = $queryParams['identifiers']['columnName'] ?? null;

        if (! empty($searchIdentifiers)) {
            $values = $queryParams['identifiers']['values'] ?? [];
            $repository = $repository->whereIn($code ?? $searchIdentifiers, is_array($values) ? $values : [$values]);
        }

        return $repository;
    }

    protected function searchByCode($repository, $query, string $code = 'code'): mixed
    {
        if (! empty($query)) {
            $repository = $repository->where($code, 'LIKE', '%'.$query.'%');
        }

        return $repository;
    }

    public function fetchAttribute(): JsonResponse
    {
        $attribute = $this->attributeRepository->where('code', request()->query('code'))->first();

        if ($attribute) {
            return response()->json([
                'attribute' => [
                    'id'    => $attribute->id,
                    'name'  => ! empty($attribute->name) ? $attribute->name : $attribute->code,
                    'code'  => $attribute->code,
                    'type'  => $attribute->type,
                    'label' => trans('admin::app.catalog.attributes.create.'.$attribute->type),
                ],
                'data' => true,
            ]);
        }

        return response()->json(['data' => false, 'message' => 'Attribute not found'], 404);
    }
}
