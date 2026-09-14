<?php

namespace Webkul\Bagisto\Services;

use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Bagisto\Enums\Export\MappingSection;
use Webkul\Bagisto\Repositories\AttributeMappingRepository;
use Webkul\Bagisto\Repositories\CategoryFieldMappingRepository;
use Webkul\Category\Repositories\CategoryFieldRepository;

class MappingSeeder
{
    public function __construct(
        protected AttributeRepository $attributeRepository,
        protected CategoryFieldRepository $categoryFieldRepository,
        protected AttributeMappingRepository $attributeMappingRepository,
        protected CategoryFieldMappingRepository $categoryFieldMappingRepository,
    ) {}

    public function seed(int|string $credentialId): void
    {
        $this->attributeMappingRepository->saveSection(
            $credentialId,
            MappingSection::STANDARD_ATTRIBUTE,
            $this->attributeDefaults()
        );

        $this->categoryFieldMappingRepository->saveSection(
            $credentialId,
            MappingSection::STANDARD_FIELD,
            $this->categoryFieldDefaults()
        );
    }

    /**
     * @return array{mapped_value: array<string, string|array<int, string>>, fixed_value: array<string, string>}
     */
    public function attributeDefaults(): array
    {
        return $this->defaultsFor(
            config('bagisto-attributes', []),
            $this->attributeRepository->all()
        );
    }

    /**
     * @return array{mapped_value: array<string, string|array<int, string>>, fixed_value: array<string, string>}
     */
    public function categoryFieldDefaults(): array
    {
        return $this->defaultsFor(
            config('bagisto-category-fields', []),
            $this->categoryFieldRepository->all()
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $bagistoFields
     * @param  iterable<object>  $unoPimFields
     * @return array{mapped_value: array<string, string|array<int, string>>, fixed_value: array<string, string>}
     */
    protected function defaultsFor(array $bagistoFields, iterable $unoPimFields): array
    {
        $byCode = [];

        foreach ($unoPimFields as $field) {
            $byCode[$field->code] = $field;
        }

        $mapped = [];
        $fixed = [];

        foreach ($bagistoFields as $bagistoField) {
            $code = $bagistoField['code'] ?? null;

            if ($code === null) {
                continue;
            }

            if (isset($bagistoField['fixedValue'])) {
                $fixed[$code] = $bagistoField['fixedValue'];
            }

            $candidate = $byCode[$code] ?? null;

            if (! $candidate || ! $this->isCompatible($bagistoField, $candidate)) {
                continue;
            }

            $mapped[$code] = empty($bagistoField['multiple']) ? $candidate->code : [$candidate->code];
        }

        return [
            'mapped_value' => $mapped,
            'fixed_value'  => $fixed,
        ];
    }

    /**
     * @param  array<string, mixed>  $bagistoField
     */
    protected function isCompatible(array $bagistoField, object $candidate): bool
    {
        $allowedTypes = array_map('trim', explode(',', (string) ($bagistoField['type'] ?? '')));

        if (! in_array($candidate->type, $allowedTypes, true)) {
            return false;
        }

        return empty($bagistoField['unique']) || ! empty($candidate->is_unique);
    }
}
