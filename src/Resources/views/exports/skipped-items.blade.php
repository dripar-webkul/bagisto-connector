@foreach ($bagistoPanels as $bagistoPanel)
    <div @class([
        'rounded-lg border p-4 mb-4',
        'bg-red-50 dark:bg-red-900/20 border-red-300 dark:border-red-800' => $bagistoPanel['tone'] === 'red',
        'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-300 dark:border-yellow-800' => $bagistoPanel['tone'] === 'yellow',
    ])>
        <div class="flex items-start gap-3">
            <span @class([
                'icon-information text-2xl shrink-0',
                'text-red-600' => $bagistoPanel['tone'] === 'red',
                'text-yellow-600' => $bagistoPanel['tone'] === 'yellow',
            ])></span>

            <div class="min-w-0 flex-1">
                <p class="font-semibold text-gray-800 dark:text-gray-100">
                    {{ $bagistoPanel['heading'] }}
                </p>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    {{ $bagistoPanel['description'] }}
                </p>

                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr @class([
                                'text-left text-gray-700 dark:text-gray-200 border-b',
                                'border-red-300 dark:border-red-800' => $bagistoPanel['tone'] === 'red',
                                'border-yellow-300 dark:border-yellow-800' => $bagistoPanel['tone'] === 'yellow',
                            ])>
                                <th class="py-2 pr-4 font-semibold">@lang('bagisto::app.bagisto.export.skipped.product')</th>
                                <th class="py-2 font-semibold">{{ $bagistoPanel['reason'] }}</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($bagistoPanel['rows'] as $bagistoRow)
                                <tr @class([
                                    'border-b last:border-0',
                                    'border-red-200 dark:border-red-900' => $bagistoPanel['tone'] === 'red',
                                    'border-yellow-200 dark:border-yellow-900' => $bagistoPanel['tone'] === 'yellow',
                                ])>
                                    <td class="py-2 pr-4 align-top font-medium text-gray-800 dark:text-gray-100 whitespace-nowrap">
                                        {{ $bagistoRow['identifier'] }}
                                    </td>

                                    <td class="py-2 align-top text-gray-600 dark:text-gray-300">
                                        {{ $bagistoRow['reason'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endforeach
