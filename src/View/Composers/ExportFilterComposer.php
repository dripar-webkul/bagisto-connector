<?php

namespace Webkul\Bagisto\View\Composers;

use Illuminate\View\View;
use Webkul\Bagisto\Enums\Export\ProductFilter as BagistoProductFilter;
use Webkul\DataTransfer\Models\JobInstances;

class ExportFilterComposer
{
    protected const VIEWS = [
        'bagisto::exports.filters'              => ['credentials', false],
        'bagisto::exports.filters-edit'         => ['credentials', true],
        'bagisto::exports.filters-scope'        => ['scope', false],
        'bagisto::exports.filters-scope-edit'   => ['scope', true],
        'bagisto::exports.bagisto-filters'      => ['filters', false],
        'bagisto::exports.bagisto-filters-edit' => ['filters', true],
    ];

    protected const SECTIONS = [
        'credentials' => [
            'title'      => 'bagisto::app.exporters.bagisto.credentials',
            'grid_class' => 'grid grid-cols-1',
        ],

        'scope' => [
            'title'      => 'admin::app.settings.data-transfer.exports.create.scope-filters',
            'grid_class' => 'grid grid-cols-1',
        ],

        'filters' => [
            'title'      => 'bagisto::app.exporters.bagisto.filters',
            'grid_class' => 'grid grid-cols-2 max-sm:grid-cols-1 gap-x-5',
        ],
    ];

    public function compose(View $view): void
    {
        [$section, $isEdit] = self::VIEWS[$view->getName()] ?? ['scope', false];

        $fields = match ($section) {
            'credentials' => BagistoProductFilter::credentialFields(),
            'scope'       => BagistoProductFilter::scopeFields(),
            default       => BagistoProductFilter::filterFields(),
        };

        $exporterConfig = config('exporters', []);

        $view->with([
            'bagistoFields'          => $fields,
            'bagistoOnly'            => implode(',', $fields),
            'bagistoTitle'           => trans(self::SECTIONS[$section]['title']),
            'bagistoGridClass'       => self::SECTIONS[$section]['grid_class'],
            'bagistoExporterConfig'  => $exporterConfig,
            'bagistoCredentialField' => BagistoProductFilter::CREDENTIALS->value,
        ]);

        if (! $isEdit) {
            return;
        }

        $export = $this->export();
        $entityType = $export->entity_type ?? null;

        $view->with([
            'bagistoEntityType' => $entityType,
            'bagistoValues'     => (array) ($export->filters ?? []),
            'bagistoVisible'    => $this->hasFields($exporterConfig, $entityType, $fields),
        ]);
    }

    protected function export(): ?JobInstances
    {
        $id = request()->route('id');

        return $id ? JobInstances::find($id) : null;
    }

    protected function hasFields(array $exporterConfig, ?string $entityType, array $fields): bool
    {
        if ($entityType === null || ! $this->isConnectorExport($exporterConfig, $entityType)) {
            return false;
        }

        $declared = array_column($exporterConfig[$entityType]['filters']['fields'] ?? [], 'name');

        return array_intersect($declared, $fields) !== [];
    }

    protected function isConnectorExport(array $exporterConfig, string $entityType): bool
    {
        return str_starts_with(
            (string) ($exporterConfig[$entityType]['exporter'] ?? ''),
            'Webkul\\Bagisto\\'
        );
    }
}
