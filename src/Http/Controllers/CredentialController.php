<?php

namespace Webkul\Bagisto\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Bagisto\DataGrids\CredentialDataGrid;
use Webkul\Bagisto\Enums\Export\CacheType;
use Webkul\Bagisto\Enums\Services\EndPointType;
use Webkul\Bagisto\Enums\Services\MethodType;
use Webkul\Bagisto\Http\Client\HttpClientFactory;
use Webkul\Bagisto\Http\Requests\CredentialCreateRequest;
use Webkul\Bagisto\Http\Requests\CredentialUpdateRequest;
use Webkul\Bagisto\Models\Credential;
use Webkul\Bagisto\Presenters\CredentialPresenter;
use Webkul\Bagisto\Repositories\CredentialRepository;
use Webkul\Bagisto\Services\ApiService;
use Webkul\Bagisto\Services\MappingSeeder;
use Webkul\Bagisto\Traits\EncryptableTrait;
use Webkul\Core\Repositories\ChannelRepository;

class CredentialController extends Controller
{
    use EncryptableTrait;

    public function __construct(
        protected ChannelRepository $channelRepository,
        protected CredentialRepository $credentialRepository,
        protected MappingSeeder $mappingSeeder,
    ) {}

    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return app(CredentialDataGrid::class)->toJson();
        }

        return view('bagisto::credentials.index');
    }

    public function store(CredentialCreateRequest $request): JsonResponse
    {
        $requestData = $request->only([
            'email',
            'password',
            'shop_url',
        ]);

        $httpClient = new HttpClientFactory;
        $requestData['shop_url'] = rtrim($requestData['shop_url'], '/');

        try {
            $httpClient = $httpClient->withBaseUri($requestData['shop_url'])
                ->withEmail($requestData['email'])
                ->withPassword($requestData['password'])
                ->make();

            $requestData['password'] = $this->encryptValue($requestData['password']);

            $responseData = $this->credentialRepository->create($requestData);

            $this->mappingSeeder->seed($responseData->id);

            return new JsonResponse([
                'message'      => trans('bagisto::app.bagisto.credentials.index.create-success'),
                'redirect_url' => route('admin.bagisto.credentials.edit', $responseData->id),
            ], 201);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'errors' => $e->validator->errors(),
            ], 422);
        } catch (\Exception $e) {
            return new JsonResponse([
                'errors' => ['shop_url' => [$e->getMessage()]],
            ], 422);
        }
    }

    public function edit(int $id): View
    {
        $credential = $this->credentialRepository->find($id);

        if (! $credential) {
            abort(404);
        }

        try {
            $httpClient = new HttpClientFactory;
            $httpClient = $httpClient->withBaseUri($credential->shop_url)
                ->withEmail($credential->email)
                ->withPassword($this->decryptValue($credential->password))
                ->make();

            if (in_array('toRequest', get_class_methods($httpClient))) {
                $storeChannels = $httpClient->toRequest(MethodType::GET->value, EndPointType::GET_CHANNELS->value);
                $storefilterableAttribtes = $httpClient->toRequest(MethodType::GET->value, EndPointType::GET_IS_FILTERABLE_ATTRIBUTES->value, ['is_filterable' => 1]);
            } else {
                $storeChannels = [];
                $storefilterableAttribtes = [];
            }
        } catch (\Exception $e) {
            $storeChannels = [];
            $storefilterableAttribtes = [];
        }

        $channels = $this->channelRepository->all();

        $credential->store_info = array_map(function ($channel) {
            return json_decode($channel);
        }, $credential->store_info ?? []);

        $unoPimChannels = [];

        foreach ($channels as $channel) {
            $unoPimChannels[] = [
                'id'         => $channel->id,
                'name'       => ! empty($channel->name) ? $channel->name : $channel->code,
                'code'       => $channel->code,
                'currencies' => $channel->currencies->toArray(),
                'locales'    => $channel->locales->toArray(),
            ];
        }

        return view('bagisto::credentials.edit', compact('unoPimChannels', 'storeChannels', 'storefilterableAttribtes', 'credential'));
    }

    public function update(CredentialUpdateRequest $request, $id): JsonResponse
    {
        $credential = $this->credentialRepository->findOrFail($id);

        [$password, $encryptedPassword] = $this->resolvePassword($request->password, $credential);

        $httpClient = new HttpClientFactory;
        $httpClient = $httpClient->withBaseUri($request->shop_url)
            ->withEmail($request->email)
            ->withPassword($password)
            ->make();

        $requestData = $request->only([
            'email',
            'store_info',
        ]);

        $requestData['password'] = $encryptedPassword;
        $requestData['store_info'] = $this->sanitizeStoreInfo($requestData['store_info'] ?? []);

        if ($request->filterableAttribtes) {
            $requestData['additional_info'] = [[
                CredentialPresenter::FILTERABLE_ATTRIBUTES_KEY       => $request->filterableAttribtes,
                CredentialPresenter::FILTERABLE_ATTRIBUTE_LABELS_KEY => $this->fetchFilterableAttributeLabels($httpClient, $request->filterableAttribtes),
            ]];
        }

        $this->credentialRepository->update($requestData, $id);

        $this->forgetCredentialCaches($id);

        return new JsonResponse([
            'message'      => trans('bagisto::app.bagisto.credentials.index.update-success'),
            'redirect_url' => route('admin.bagisto.credentials.edit', $id),
        ]);
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    protected function resolvePassword(?string $submitted, Credential $credential): array
    {
        if ($submitted === Credential::MASKED_PASSWORD) {
            return [$this->decryptValue($credential->password), $credential->password];
        }

        return [$submitted, $this->encryptValue((string) $submitted)];
    }

    /**
     * @return array<string, string>
     */
    protected function fetchFilterableAttributeLabels(ApiService $httpClient, string $ids): array
    {
        $selected = array_filter(array_map('trim', explode(',', $ids)), 'strlen');

        if ($selected === []) {
            return [];
        }

        try {
            $attributes = $httpClient->toRequest(
                MethodType::GET->value,
                EndPointType::GET_IS_FILTERABLE_ATTRIBUTES->value,
                ['is_filterable' => 1]
            );
        } catch (\Exception) {
            return [];
        }

        $labels = [];

        foreach ($attributes as $attribute) {
            $id = (string) ($attribute['id'] ?? '');

            if (in_array($id, $selected, true)) {
                $labels[$id] = $attribute['name'] ?? $attribute['code'] ?? $id;
            }
        }

        return $labels;
    }

    protected function forgetCredentialCaches(int|string $credentialId): void
    {
        foreach ([
            CacheType::CREDENTIAL,
            CacheType::PRODUCT_JOB_FILTERS,
            CacheType::CATEGORY_JOB_FILTERS,
            CacheType::ATTRIBUTE_MAPPING,
            CacheType::CATEGORY_FIELD_MAPPING,
        ] as $cacheType) {
            Cache::forget($cacheType->forCredential($credentialId));
        }
    }

    protected function sanitizeStoreInfo($storeInfo): array
    {
        $clean = [];

        foreach ((array) $storeInfo as $channelId => $mapping) {
            if (! is_string($mapping) || trim($mapping) === '') {
                continue;
            }

            $decoded = json_decode($mapping, true);

            if (! is_array($decoded) || $decoded === [] || empty($decoded['channel'])) {
                continue;
            }

            $clean[$channelId] = $mapping;
        }

        return $clean;
    }

    public function destroy($id): JsonResponse
    {
        $this->credentialRepository->delete($id);

        $this->forgetCredentialCaches($id);

        return new JsonResponse(['message' => trans('bagisto::app.bagisto.credentials.index.delete-success')]);
    }
}
