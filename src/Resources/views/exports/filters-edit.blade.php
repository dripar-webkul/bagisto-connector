@php
    $bagistoFilterNames = ['credentials', 'channel', 'locale', 'family', 'type', 'code'];

    $bagistoExporterConfig = $exporterConfig ?? config('exporters');

    // Neither $export nor $exporterConfig is in scope inside a hooked template,
    // so resolve the job instance from the route instead.
    $bagistoExport = $export
        ?? \Webkul\DataTransfer\Models\JobInstances::find(request()->route('id'));

    $bagistoEntityType = $bagistoExport->entity_type ?? null;

    $bagistoValues = $exportFilters ?? ($bagistoExport->filters ?? []);

    $bagistoHasFilters = $bagistoEntityType
        && collect($bagistoExporterConfig[$bagistoEntityType]['filters']['fields'] ?? [])
            ->pluck('name')
            ->intersect($bagistoFilterNames)
            ->isNotEmpty();
@endphp

@if ($bagistoHasFilters)
    <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
        <p class="text-base text-gray-800 dark:text-white font-semibold mb-4">
            @lang('bagisto::app.exporters.bagisto.filters')
        </p>

        <x-admin::data-transfer.filter-fields
            :entity-type="$bagistoEntityType"
            :values="$bagistoValues"
            :exporter-config="$bagistoExporterConfig"
            only="credentials,channel,locale,family,type,code"
            grid-class="grid grid-cols-1"
        />
    </div>
@endif
