<?php

namespace Webkul\Bagisto\Tests\Unit\View;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Webkul\Bagisto\Enums\Export\ProductFilter;
use Webkul\DataTransfer\Models\JobInstances;

class ExportFilterComposerTest extends TestCase
{
    private const VIEW_DIR = __DIR__.'/../../../src/Resources/views';

    public function test_no_connector_blade_carries_php()
    {
        $offenders = [];

        foreach (File::allFiles(self::VIEW_DIR) as $file) {
            $contents = $file->getContents();

            if (preg_match('/@php\b|<\?php/', $contents)) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, 'Blade templates must not contain PHP blocks');
    }

    public function test_the_scope_card_renders_on_the_create_page()
    {
        $html = view('bagisto::exports.filters-scope')->render();

        $this->assertStringContainsString('only="channel,locale,attribute_codes"', $html);
        $this->assertStringContainsString(trans('admin::app.settings.data-transfer.exports.create.scope-filters'), $html);
        $this->assertFieldListSurvivesTheAttributeQuoting($html, ['channel', 'locale', 'attribute_codes']);
    }

    public function test_the_bagisto_filters_card_renders_on_the_create_page()
    {
        $html = view('bagisto::exports.bagisto-filters')->render();

        $this->assertStringContainsString('only="code,category_codes"', $html);
        $this->assertStringContainsString(trans('bagisto::app.exporters.bagisto.filters'), $html);
        $this->assertFieldListSurvivesTheAttributeQuoting($html, ['code', 'category_codes']);
    }

    public function test_the_credential_card_renders_on_the_create_page()
    {
        $html = view('bagisto::exports.filters')->render();

        $this->assertStringContainsString('only="credentials,type"', $html);
        $this->assertStringContainsString(trans('bagisto::app.exporters.bagisto.credentials'), $html);
    }

    public function test_product_type_sits_under_the_credential()
    {
        $this->assertSame(['credentials', 'type'], ProductFilter::credentialFields());

        $names = array_column(config('exporters.bagisto_product.filters.fields'), 'name');

        $this->assertLessThan(
            array_search('type', $names, true),
            array_search('credentials', $names, true),
            'the credential must be declared before the product type'
        );
    }

    public function test_the_scoped_card_ships_the_credential_bridge()
    {
        $html = $this->renderWithScripts('bagisto::exports.filters-scope');

        $this->assertStringContainsString('filter-value-changed', $html);
        $this->assertStringContainsString("app.component('v-field-set')", $html);
        $this->assertStringContainsString('"credentials"', $html);
    }

    public function test_the_bridge_is_emitted_once_however_many_cards_render()
    {
        $html = $this->renderWithScripts(
            'bagisto::exports.filters',
            'bagisto::exports.filters-scope',
            'bagisto::exports.bagisto-filters'
        );

        $this->assertSame(1, substr_count($html, 'listeners.set(this, mirror)'));
    }

    private function renderWithScripts(string ...$views): string
    {
        $includes = implode('', array_map(
            fn (string $view): string => '@include("'.$view.'")',
            $views
        ));

        return Blade::render($includes.'@stack("scripts")');
    }

    private function assertFieldListSurvivesTheAttributeQuoting(string $html, array $fields): void
    {
        preg_match('/v-if="([^"]*)"/', $html, $matches);

        $this->assertNotEmpty($matches, 'the v-if attribute is not quoted correctly');

        foreach ($fields as $field) {
            $this->assertStringContainsString($field, $matches[1]);
        }

        $this->assertStringContainsString('includes(field.name))', $matches[1]);
    }

    public function test_the_edit_card_stays_hidden_for_an_export_that_is_not_ours()
    {
        $this->assertSame('', trim($this->renderEditCardFor('products')));
    }

    public function test_the_edit_card_renders_for_a_connector_export()
    {
        $html = $this->renderEditCardFor('bagisto_product');

        $this->assertStringContainsString(trans('admin::app.settings.data-transfer.exports.create.scope-filters'), $html);
        $this->assertStringContainsString('only="channel,locale,attribute_codes"', $html);
    }

    public function test_the_edit_card_carries_the_values_the_profile_was_saved_with()
    {
        $html = $this->renderEditCardFor('bagisto_product', ['credentials' => '7']);

        $this->assertStringContainsString('{"credentials":"7"}', $html);
        $this->assertStringContainsString('entity-type="bagisto_product"', $html);
    }

    private function renderEditCardFor(string $entityType, array $filters = []): string
    {
        $export = JobInstances::create([
            'code'        => 'bagisto_test_'.uniqid(),
            'entity_type' => $entityType,
            'type'        => 'export',
            'filters'     => $filters,
        ]);

        Route::shouldReceive('current')->andReturnNull();

        request()->setRouteResolver(fn () => new class($export->id)
        {
            public function __construct(private int|string $id) {}

            public function parameter(string $name, $default = null)
            {
                return $name === 'id' ? $this->id : $default;
            }

            public function __call(string $method, array $arguments)
            {
                return $this->parameter($arguments[0] ?? '', $arguments[1] ?? null);
            }
        });

        return view('bagisto::exports.filters-scope-edit')->render();
    }
}
