@php
    use Webkul\Bagisto\Enums\Export\SkipReason;
    use Webkul\Bagisto\Enums\Export\SkipScope;

    $bagistoTrackId = request()->route('batch_id') ?? request()->route('id');

    $bagistoJobTrack = \Webkul\DataTransfer\Models\JobTrackProxy::find($bagistoTrackId);

    $bagistoEntries = collect($bagistoJobTrack?->errors ?? [])
        ->filter(fn ($entry) => is_array($entry) && isset($entry['identifier'], $entry['reason']))
        ->groupBy(fn ($entry) => SkipScope::of($entry['scope'] ?? null)->value);

    $bagistoSkipped = $bagistoEntries->get(SkipScope::PRODUCT->value, collect());
    $bagistoExcluded = $bagistoEntries->get(SkipScope::MEDIA->value, collect());
@endphp

@foreach ([
    ['rows' => $bagistoSkipped, 'key' => 'skipped', 'tone' => 'red', 'count' => $bagistoSkipped->pluck('identifier')->unique()->count()],
    ['rows' => $bagistoExcluded, 'key' => 'excluded', 'tone' => 'yellow', 'count' => $bagistoExcluded->count()],
] as $bagistoPanel)
    @continue($bagistoPanel['rows']->isEmpty())

    @php($bagistoTone = $bagistoPanel['tone'])

    <div @class([
        'rounded-lg border p-4 mb-4',
        'bg-red-50 dark:bg-red-900/20 border-red-300 dark:border-red-800' => $bagistoTone === 'red',
        'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-300 dark:border-yellow-800' => $bagistoTone === 'yellow',
    ])>
        <div class="flex items-start gap-3">
            <span @class([
                'icon-information text-2xl shrink-0',
                'text-red-600' => $bagistoTone === 'red',
                'text-yellow-600' => $bagistoTone === 'yellow',
            ])></span>

            <div class="min-w-0 flex-1">
                <p class="font-semibold text-gray-800 dark:text-gray-100">
                    @lang('bagisto::app.bagisto.export.skipped.'.$bagistoPanel['key'].'-heading', ['count' => $bagistoPanel['count']])
                </p>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    @lang('bagisto::app.bagisto.export.skipped.'.$bagistoPanel['key'].'-description')
                </p>

                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr @class([
                                'text-left text-gray-700 dark:text-gray-200 border-b',
                                'border-red-300 dark:border-red-800' => $bagistoTone === 'red',
                                'border-yellow-300 dark:border-yellow-800' => $bagistoTone === 'yellow',
                            ])>
                                <th class="py-2 pr-4 font-semibold">@lang('bagisto::app.bagisto.export.skipped.product')</th>
                                <th class="py-2 font-semibold">@lang('bagisto::app.bagisto.export.skipped.'.$bagistoPanel['key'].'-reason')</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($bagistoPanel['rows'] as $bagistoRow)
                                @php($bagistoReason = SkipReason::tryFrom($bagistoRow['reason']))

                                <tr @class([
                                    'border-b last:border-0',
                                    'border-red-200 dark:border-red-900' => $bagistoTone === 'red',
                                    'border-yellow-200 dark:border-yellow-900' => $bagistoTone === 'yellow',
                                ])>
                                    <td class="py-2 pr-4 align-top font-medium text-gray-800 dark:text-gray-100 whitespace-nowrap">
                                        {{ $bagistoRow['identifier'] }}
                                    </td>

                                    <td class="py-2 align-top text-gray-600 dark:text-gray-300">
                                        {{ $bagistoReason?->describe($bagistoRow['identifier'], $bagistoRow['details'] ?? []) ?? $bagistoRow['reason'] }}
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
