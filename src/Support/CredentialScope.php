<?php

namespace Webkul\Bagisto\Support;

class CredentialScope
{
    public static function decode(mixed $storeInfo): array
    {
        $decoded = [];

        foreach ((array) ($storeInfo ?? []) as $entry) {
            $data = is_string($entry) ? json_decode($entry, true) : $entry;

            if (is_object($data)) {
                $data = json_decode((string) json_encode($data), true);
            }

            if (is_array($data) && $data !== []) {
                $decoded[] = $data;
            }
        }

        return $decoded;
    }

    public static function channelMap(mixed $storeInfo): array
    {
        $channels = [];

        foreach (self::decode($storeInfo) as $data) {
            if (empty($data['channel']) || ! is_array($data['channel'])) {
                continue;
            }

            $bagistoChannel = array_key_first($data['channel']);
            $unopimChannel = $data['channel'][$bagistoChannel];

            if (is_string($bagistoChannel) && is_string($unopimChannel) && $unopimChannel !== '') {
                $channels[$bagistoChannel] = $unopimChannel;
            }
        }

        return $channels;
    }

    public static function localeMap(mixed $storeInfo): array
    {
        $locales = [];

        foreach (self::decode($storeInfo) as $data) {
            if (empty($data['channel']) || ! is_array($data['channel']) || ! isset($data['locales'])) {
                continue;
            }

            $bagistoChannel = array_key_first($data['channel']);

            if (! is_string($bagistoChannel)) {
                continue;
            }

            $locales[$bagistoChannel] = array_filter(
                (array) $data['locales'],
                fn (mixed $unopimLocale, mixed $bagistoLocale): bool => is_string($bagistoLocale)
                    && is_string($unopimLocale)
                    && $unopimLocale !== '',
                ARRAY_FILTER_USE_BOTH
            );
        }

        return $locales;
    }

    public static function unopimChannelCodes(mixed $storeInfo): array
    {
        return array_values(array_unique(array_values(self::channelMap($storeInfo))));
    }

    public static function unopimLocaleCodes(mixed $storeInfo, array $unopimChannelCodes = []): array
    {
        $channelMap = self::channelMap($storeInfo);
        $localeMap = self::localeMap($storeInfo);

        $locales = [];

        foreach ($localeMap as $bagistoChannel => $mappedLocales) {
            if (
                $unopimChannelCodes !== []
                && ! in_array($channelMap[$bagistoChannel] ?? null, $unopimChannelCodes, true)
            ) {
                continue;
            }

            $locales = array_merge($locales, array_values($mappedLocales));
        }

        return array_values(array_unique($locales));
    }
}
