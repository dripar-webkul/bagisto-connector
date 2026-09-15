<?php

namespace Webkul\Bagisto\Tests\Unit\Database;

use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;
use Webkul\Bagisto\Enums\Export\MappingSection;
use Webkul\Bagisto\Models\AttributeMapping;
use Webkul\Bagisto\Models\Credential;

class MappingRollbackGuardTest extends TestCase
{
    private const TABLE = 'wk_bagisto_attribute_config_mapping';

    protected function setUp(): void
    {
        parent::setUp();

        AttributeMapping::query()->delete();
    }

    private function guard(string $table): void
    {
        $migration = require __DIR__.'/../../../src/Database/Migrations/2026_09_14_090000_add_credential_id_to_bagisto_mapping_tables.php';

        $method = new ReflectionMethod($migration, 'guardAgainstLossyRollback');
        $method->setAccessible(true);
        $method->invoke($migration, $table);
    }

    private function mappingFor(string $suffix, array $mapped): void
    {
        $credential = Credential::create([
            'shop_url' => 'https://store-'.$suffix.'.test',
            'email'    => $suffix.'@example.com',
            'password' => 'secret',
        ]);

        AttributeMapping::create([
            'credential_id' => $credential->id,
            'section'       => MappingSection::STANDARD_ATTRIBUTE->value,
            'mapped_value'  => $mapped,
        ]);
    }

    public function test_it_allows_a_rollback_that_discards_nothing()
    {
        $this->mappingFor('only', ['sku' => 'sku']);

        $this->guard(self::TABLE);

        $this->assertSame(1, AttributeMapping::count());
    }

    public function test_it_allows_a_rollback_when_there_is_nothing_to_roll_back()
    {
        $this->guard(self::TABLE);

        $this->assertSame(0, AttributeMapping::count());
    }

    public function test_it_refuses_to_discard_another_credentials_mappings()
    {
        $this->mappingFor('one', ['sku' => 'sku']);
        $this->mappingFor('two', ['sku' => 'product_number']);

        $this->expectException(RuntimeException::class);

        $this->guard(self::TABLE);
    }

    public function test_it_leaves_every_mapping_in_place_when_it_refuses()
    {
        $this->mappingFor('one', ['sku' => 'sku']);
        $this->mappingFor('two', ['sku' => 'product_number']);

        try {
            $this->guard(self::TABLE);
        } catch (RuntimeException) {
        }

        $this->assertSame(2, AttributeMapping::count());
    }
}
