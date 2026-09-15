<div
    v-if="filterFields.some(field => @js($bagistoFields).includes(field.name))"
    class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow"
>
    <p class="text-base text-gray-800 dark:text-white font-semibold mb-4">
        {{ $bagistoTitle }}
    </p>

    <x-admin::data-transfer.filter-fields
        ::entity-type="entityType"
        :exporter-config="$bagistoExporterConfig"
        :only="$bagistoOnly"
        :grid-class="$bagistoGridClass"
    />
</div>

@include('bagisto::exports.partials.credential-scope-bridge')
