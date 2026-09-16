<?php

namespace Webkul\Bagisto\Tests\Unit\Services;

use Illuminate\Support\Facades\Config;
use Webkul\Attribute\Models\Attribute;
use Webkul\Attribute\Tests\AttributeTestCase;
use Webkul\Bagisto\Services\MappingSeeder;

class MappingSeederTest extends AttributeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('bagisto-attributes', [
            ['code' => 'sku', 'type' => 'text', 'unique' => true, 'required' => true],
            ['code' => 'description', 'type' => 'textarea', 'unique' => true],
            ['code' => 'name', 'type' => 'text', 'required' => true],
            ['code' => 'status', 'type' => 'boolean', 'required' => true, 'fixedValue' => '1'],
            ['code' => 'images', 'type' => 'image,gallery,asset', 'multiple' => true],
            ['code' => 'nothing_matches_this', 'type' => 'text'],
        ]);

        Config::set('bagisto-category-fields', [
            ['code' => 'name', 'type' => 'text', 'required' => true],
            ['code' => 'position', 'type' => 'text', 'fixedValue' => '1'],
        ]);
    }

    private function attributeDefaults(): array
    {
        return resolve(MappingSeeder::class)->attributeDefaults();
    }

    public function test_it_maps_bagisto_fields_to_the_unopim_attribute_sharing_its_code()
    {
        $mapping = $this->attributeDefaults();

        $this->assertArrayHasKey('name', $mapping['mapped_value']);
        $this->assertSame('name', $mapping['mapped_value']['name']);
    }

    public function test_it_leaves_a_bagisto_field_unmapped_when_no_attribute_shares_its_code()
    {
        $this->assertArrayNotHasKey('nothing_matches_this', $this->attributeDefaults()['mapped_value']);
    }

    public function test_it_does_not_map_an_attribute_whose_type_the_bagisto_field_rejects()
    {
        Attribute::factory()->create(['code' => 'status', 'type' => 'text']);

        $this->assertArrayNotHasKey('status', $this->attributeDefaults()['mapped_value']);
    }

    public function test_it_accepts_any_of_the_types_a_multi_type_field_allows()
    {
        Attribute::factory()->create(['code' => 'images', 'type' => 'gallery']);

        $this->assertSame(['images'], $this->attributeDefaults()['mapped_value']['images']);
    }

    public function test_it_only_maps_a_unique_bagisto_field_to_a_unique_attribute()
    {
        $mapping = $this->attributeDefaults();

        $this->assertArrayHasKey('sku', $mapping['mapped_value']);
        $this->assertArrayNotHasKey('description', $mapping['mapped_value']);
    }

    public function test_it_carries_the_configured_fixed_values_across()
    {
        $this->assertSame('1', $this->attributeDefaults()['fixed_value']['status']);
    }

    public function test_it_seeds_category_field_defaults_the_same_way()
    {
        $mapping = resolve(MappingSeeder::class)->categoryFieldDefaults();

        $this->assertSame('1', $mapping['fixed_value']['position']);
        $this->assertArrayHasKey('name', $mapping['mapped_value']);
    }
}
