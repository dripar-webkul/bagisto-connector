<?php

namespace Webkul\Bagisto\Http\Controllers\Mappings;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Bagisto\Enums\Export\CacheType;
use Webkul\Bagisto\Enums\Export\MappingSection;
use Webkul\Bagisto\Http\Requests\StandardFieldRequest;
use Webkul\Bagisto\Repositories\CategoryFieldMappingRepository;
use Webkul\Bagisto\Repositories\CredentialRepository;
use Webkul\Category\Repositories\CategoryFieldRepository;

class CategoryFieldController extends Controller
{
    public function __construct(
        protected CategoryFieldRepository $categoryFieldRepository,
        protected CategoryFieldMappingRepository $categoryFieldMappingRepository,
        protected CredentialRepository $credentialRepository,
    ) {}

    public function index(int $credentialId): View
    {
        $credential = $this->credentialRepository->findOrFail($credentialId);

        $bagistoCategoryFields = $this->translate(config('bagisto-category-fields'));

        $categoryFields = $this->categoryFieldRepository->all();

        $mappedCategoryFields = $this->categoryFieldMappingRepository->forCredential($credential->id, MappingSection::STANDARD_FIELD);

        return view('bagisto::credentials.category-mapping', compact('credential', 'bagistoCategoryFields', 'categoryFields', 'mappedCategoryFields'));
    }

    public function storeOrUpdate(StandardFieldRequest $request, int $credentialId): JsonResponse
    {
        try {
            $credential = $this->credentialRepository->findOrFail($credentialId);

            $formatedData = $this->setFormatForMapping(request()->all());

            if (! empty($formatedData['standard_field']['fixed_value'])) {
                $formatedData['standard_field']['fixed_value'] = $this->sortJsonKeysCustom($formatedData['standard_field']['fixed_value']);
            }

            $this->categoryFieldMappingRepository->saveSection(
                $credential->id,
                MappingSection::STANDARD_FIELD,
                $formatedData['standard_field']
            );

            Cache::forget(CacheType::CATEGORY_FIELD_MAPPING->forCredential($credential->id));

            return new JsonResponse([
                'message' => trans('bagisto::app.bagisto.bagisto-category-fields.success-message'),
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

        $standardCategoryFields = json_decode($data['standard_category_fields'], true) ?? [];

        $standardCategoryFieldsDefault = $data['standard_category_fields_default'] ?? [];
        $fixedValue = [];

        $fixedValue = array_filter($standardCategoryFieldsDefault, function ($value) {
            return $value !== '';
        });

        $formatedData['standard_field'] = [
            'mapped_value' => $standardCategoryFields,
            'fixed_value'  => $fixedValue,
        ];

        return $formatedData;
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
}
