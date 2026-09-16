<?php

namespace Webkul\Bagisto\Tests\Unit\Repositories;

use Tests\TestCase;
use Webkul\Bagisto\Enums\Export\CacheType;
use Webkul\Bagisto\Enums\Export\MappingSection;
use Webkul\Bagisto\Models\AttributeMapping;
use Webkul\Bagisto\Models\CategoryFieldMapping;
use Webkul\Bagisto\Models\Credential;
use Webkul\Bagisto\Repositories\AttributeMappingRepository;
use Webkul\Bagisto\Repositories\CategoryFieldMappingRepository;

class CredentialMappingScopeTest extends TestCase
{
    private function credential(string $suffix): Credential
    {
        return Credential::create([
            'shop_url' => 'https://store-'.$suffix.'.test',
            'email'    => $suffix.'@example.com',
            'password' => 'secret',
        ]);
    }

    public function test_it_resolves_the_mapping_of_the_credential_it_was_asked_for()
    {
        $first = $this->credential('one');
        $second = $this->credential('two');

        AttributeMapping::create([
            'credential_id' => $first->id,
            'section'       => MappingSection::STANDARD_ATTRIBUTE->value,
            'mapped_value'  => ['sku' => 'sku'],
        ]);

        AttributeMapping::create([
            'credential_id' => $second->id,
            'section'       => MappingSection::STANDARD_ATTRIBUTE->value,
            'mapped_value'  => ['sku' => 'product_number'],
        ]);

        $repository = resolve(AttributeMappingRepository::class);

        $this->assertSame(
            ['sku' => 'sku'],
            $repository->forCredential($first->id, MappingSection::STANDARD_ATTRIBUTE)->mapped_value
        );
        $this->assertSame(
            ['sku' => 'product_number'],
            $repository->forCredential($second->id, MappingSection::STANDARD_ATTRIBUTE)->mapped_value
        );
    }

    public function test_it_returns_nothing_when_the_credential_has_no_mapping_for_that_section()
    {
        $credential = $this->credential('empty');

        $this->assertNull(
            resolve(AttributeMappingRepository::class)->forCredential($credential->id, MappingSection::IMAGE_ATTRIBUTE)
        );
    }

    public function test_it_returns_nothing_rather_than_a_stray_row_when_no_credential_is_given()
    {
        $credential = $this->credential('anon');

        AttributeMapping::create([
            'credential_id' => $credential->id,
            'section'       => MappingSection::STANDARD_ATTRIBUTE->value,
            'mapped_value'  => ['sku' => 'sku'],
        ]);

        $this->assertNull(
            resolve(AttributeMappingRepository::class)->forCredential(null, MappingSection::STANDARD_ATTRIBUTE)
        );
    }

    public function test_it_creates_a_section_on_first_save_and_updates_it_afterwards()
    {
        $credential = $this->credential('save');
        $repository = resolve(CategoryFieldMappingRepository::class);

        $repository->saveSection($credential->id, MappingSection::STANDARD_FIELD, ['mapped_value' => ['name' => 'name']]);
        $repository->saveSection($credential->id, MappingSection::STANDARD_FIELD, ['mapped_value' => ['name' => 'slug']]);

        $this->assertSame(1, CategoryFieldMapping::where('credential_id', $credential->id)->count());
        $this->assertSame(
            ['name' => 'slug'],
            $repository->forCredential($credential->id, MappingSection::STANDARD_FIELD)->mapped_value
        );
    }

    public function test_it_drops_a_credential_mappings_along_with_the_credential()
    {
        $credential = $this->credential('cascade');

        AttributeMapping::create([
            'credential_id' => $credential->id,
            'section'       => MappingSection::STANDARD_ATTRIBUTE->value,
            'mapped_value'  => ['sku' => 'sku'],
        ]);

        CategoryFieldMapping::create([
            'credential_id' => $credential->id,
            'section'       => MappingSection::STANDARD_FIELD->value,
            'mapped_value'  => ['name' => 'name'],
        ]);

        $credentialId = $credential->id;
        $credential->delete();

        $this->assertSame(0, AttributeMapping::where('credential_id', $credentialId)->count());
        $this->assertSame(0, CategoryFieldMapping::where('credential_id', $credentialId)->count());
    }

    public function test_it_keeps_one_credential_cached_data_away_from_another()
    {
        $this->assertNotSame(
            CacheType::ATTRIBUTE_MAPPING->forCredential(1),
            CacheType::ATTRIBUTE_MAPPING->forCredential(2)
        );
        $this->assertNotSame(
            CacheType::BAGISTO_API_HTTP->forCredential(1),
            CacheType::BAGISTO_API_HTTP->forCredential(2)
        );
    }

    public function test_it_records_mapping_history_against_the_owning_credential()
    {
        $credential = $this->credential('history');

        $mapping = AttributeMapping::create([
            'credential_id' => $credential->id,
            'section'       => MappingSection::STANDARD_ATTRIBUTE->value,
            'mapped_value'  => ['sku' => 'sku'],
        ]);

        $this->assertSame($credential->id, $mapping->getPrimaryModelIdForHistory());
        $this->assertSame(['bagitsto_credentials'], $mapping->generateTags());
    }
}
