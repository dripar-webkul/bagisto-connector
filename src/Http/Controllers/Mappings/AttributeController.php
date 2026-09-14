<?php

namespace Webkul\Bagisto\Http\Controllers\Mappings;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Attribute\Repositories\AttributeFamilyRepository;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Bagisto\Enums\Export\CacheType;
use Webkul\Bagisto\Enums\Export\MappingSection;
use Webkul\Bagisto\Http\Requests\StandardAttributeRequest;
use Webkul\Bagisto\Repositories\AttributeMappingRepository;
use Webkul\Bagisto\Repositories\CredentialRepository;

class AttributeController extends Controller
{
    public function __construct(
        protected AttributeRepository $attributeRepository,
        protected AttributeFamilyRepository $attributeFamilyRepository,
        protected AttributeMappingRepository $attributeMappingRepository,
        protected CredentialRepository $credentialRepository,
    ) {}

    public function index(int $credentialId): View
    {
        $credential = $this->credentialRepository->findOrFail($credentialId);

        $bagistoAttributes = $this->translate(config('bagisto-attributes'));

        $attributes = $this->attributeRepository->all();

        $standardAttributes = $this->attributeMappingRepository->forCredential($credential->id, MappingSection::STANDARD_ATTRIBUTE);

        $additionalAttributes = $this->attributeMappingRepository->forCredential($credential->id, MappingSection::ADDITIONAL_ATTRIBUTE)?->mapped_value ?? [];

        $configurableAttributes = json_encode($this->getConfigurableAttributes());
        $configurableAttributesDb = $standardAttributes->additional_info ?? [];
        $configurableSelectedAttributes = explode(',', ! empty($configurableAttributesDb['configurable_attribute']) ? $configurableAttributesDb['configurable_attribute'] : null);

        return view('bagisto::credentials.attribute-mapping', compact(
            'credential',
            'bagistoAttributes',
            'attributes',
            'standardAttributes',
            'additionalAttributes',
            'configurableAttributes',
            'configurableSelectedAttributes'
        ));
    }

    public function storeOrUpdate(StandardAttributeRequest $request, int $credentialId): JsonResponse
    {
        try {
            $credential = $this->credentialRepository->findOrFail($credentialId);

            $formatedData = $this->setFormatForMapping(request()->all());

            if (! empty($formatedData['standard_attribute']['fixed_value'])) {
                $formatedData['standard_attribute']['fixed_value'] = $this->sortJsonKeysCustom($formatedData['standard_attribute']['fixed_value']);
            }

            $this->attributeMappingRepository->saveSection(
                $credential->id,
                MappingSection::STANDARD_ATTRIBUTE,
                $formatedData['standard_attribute']
            );

            Cache::forget(CacheType::ATTRIBUTE_MAPPING->forCredential($credential->id));

            return new JsonResponse([
                'message' => trans('bagisto::app.bagisto.bagisto-attributes.success-message'),
            ], 201);
        } catch (\Exception $e) {
            return new JsonResponse([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    private function setFormatForMapping(array $data): array
    {
        $formatedData = [];

        $standardAttributes = json_decode($data['standard_attributes'], true) ?? [];

        $standardAttributesDefault = $data['standard_attributes_default'] ?? [];

        $fixedValue = [];

        $fixedValue = array_filter($standardAttributesDefault, function ($value) {
            return $value !== '';
        });

        $configurableAttribute['configurable_attribute'] = $data['configurable_attribute'] ?? null;

        $formatedData['standard_attribute'] = [
            'mapped_value'    => $standardAttributes,
            'fixed_value'     => $fixedValue,
            'additional_info' => $configurableAttribute,
        ];

        return $formatedData;
    }

    public function addAdditionalAttributes(Request $request, int $credentialId): JsonResponse
    {
        $credential = $this->credentialRepository->findOrFail($credentialId);

        $data = $request->validate([
            'code' => 'required|string',
            'type' => 'required|string',
        ]);

        if (in_array($data['code'], array_column(config('bagisto-attributes'), 'code'))) {
            return new JsonResponse([
                'message' => trans('bagisto::app.bagisto.export.mapping.attributes.duplicate'),
            ], 400);
        }

        $existing = $this->attributeMappingRepository->forCredential($credential->id, MappingSection::ADDITIONAL_ATTRIBUTE);

        $additional = is_array($existing?->mapped_value) ? $existing->mapped_value : [];

        if (in_array($data['code'], array_column($additional, 'code'), true)) {
            return new JsonResponse([
                'message' => trans('bagisto::app.bagisto.export.mapping.attributes.duplicate'),
            ], 400);
        }

        $additional[] = [
            'code' => $data['code'],
            'name' => ucfirst($data['code']),
            'type' => $data['type'],
        ];

        $this->attributeMappingRepository->saveSection(
            $credential->id,
            MappingSection::ADDITIONAL_ATTRIBUTE,
            ['mapped_value' => $additional]
        );

        Cache::forget(CacheType::ATTRIBUTE_MAPPING->forCredential($credential->id));

        return new JsonResponse([
            'message' => trans('bagisto::app.bagisto.export.mapping.attributes.added'),
        ]);
    }

    public function removeAdditionalAttributes(Request $request, int $credentialId): void
    {
        $credential = $this->credentialRepository->findOrFail($credentialId);

        $code = $request->code;

        $additionalObj = $this->attributeMappingRepository->forCredential($credential->id, MappingSection::ADDITIONAL_ATTRIBUTE);

        if ($additionalObj && is_array($additionalObj->mapped_value)) {
            $additionalObj->update([
                'mapped_value' => array_values(array_filter(
                    $additionalObj->mapped_value,
                    fn ($attribute) => ($attribute['code'] ?? null) !== $code
                )),
            ]);
        }

        $standardObj = $this->attributeMappingRepository->forCredential($credential->id, MappingSection::STANDARD_ATTRIBUTE);

        if ($standardObj) {
            $standardObj->update([
                'mapped_value' => Arr::except((array) $standardObj->mapped_value, [$code]),
                'fixed_value'  => Arr::except((array) $standardObj->fixed_value, [$code]),
            ]);
        }

        Cache::forget(CacheType::ATTRIBUTE_MAPPING->forCredential($credential->id));
    }

    public function translate(array $arrayData): array
    {
        foreach ($arrayData as $key => $value) {
            $arrayData[$key]['name'] = trans($value['name']);
            $arrayData[$key]['title'] = trans($value['title']);
        }

        return $arrayData;
    }

    protected function sortJsonKeysCustom(array $data): array
    {
        uksort($data, function ($a, $b) {
            $lenA = strlen($a);
            $lenB = strlen($b);

            if ($lenA !== $lenB) {
                return $lenA <=> $lenB;
            }

            return strcmp($a, $b);
        });

        return $data;
    }

    protected function getConfigurableAttributes(): array
    {
        $configurableFamily = $this->attributeFamilyRepository->all();

        $configurableAttributes = [];
        foreach ($configurableFamily as $family) {
            foreach ($family->getConfigurableAttributes() as $attribute) {
                $data = [
                    'id'   => $attribute->id,
                    'code' => $attribute->code,
                    'name' => ! empty($attribute->name) ? $attribute->name : $attribute->code,
                ];
                if (! in_array($data, $configurableAttributes)) {
                    $configurableAttributes[] = $data;
                }
            }
        }

        return $configurableAttributes;
    }
}
