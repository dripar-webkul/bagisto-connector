{{--
    UnoPim 3.0's export screens only render a filter field when its name appears
    in one of the core sections' `only` allow-lists, and there is no catch-all
    section. None of the connector's filter names are in those lists, so without
    this block they never reach the form and the export saves without a
    credential. Mirrors the markup of core's own scope card.
--}}
<div
    v-if="filterFields.some(field => ['credentials', 'channel', 'locale', 'family', 'type', 'code'].includes(field.name))"
    class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow"
>
    <p class="text-base text-gray-800 dark:text-white font-semibold mb-4">
        @lang('bagisto::app.exporters.bagisto.filters')
    </p>

    <x-admin::data-transfer.filter-fields
        ::entity-type="entityType"
        :exporter-config="$exporterConfig ?? config('exporters')"
        only="credentials,channel,locale,family,type,code"
        grid-class="grid grid-cols-1"
    />
</div>
