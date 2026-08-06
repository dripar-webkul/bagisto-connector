{{--
    UnoPim 3.0's export screens only render filter fields whose name appears in
    one of the core sections' `only` allow-lists, and there is no catch-all
    section. The connector's own filter names are not in any of those lists, so
    without this block none of them reach the form and the export saves without
    a credential.
--}}
<x-admin::data-transfer.filter-fields
    :values="old('filters') ?? ($export->filters ?? [])"
    :exporter-config="$exporterConfig ?? config('exporters')"
    ::entity-type="parseValue(entityType)?.id ?? entityType"
    only="credentials,channel,locale,family,type,code"
    grid-class="grid-cols-2"
/>
