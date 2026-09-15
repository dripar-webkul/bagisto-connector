<?php

namespace Webkul\Bagisto\Presenters;

use Webkul\HistoryControl\Interfaces\HistoryPresenterInterface;
use Webkul\HistoryControl\Presenters\JsonDataPresenter as JsonDataPresenters;

class JsonDataPresenter extends JsonDataPresenters implements HistoryPresenterInterface
{
    public static function representValueForHistory(mixed $oldValues, mixed $newValues, string $fieldName): array
    {
        $oldArray = static::flatten($oldValues);
        $newArray = static::flatten($newValues);

        if ($oldArray === [] && $newArray === []) {
            return [];
        }

        $normalizedData = [];

        static::normalizeValues(static::calculateDifference($oldArray, $newArray), 'old', $normalizedData);
        static::normalizeValues(static::calculateDifference($newArray, $oldArray), 'new', $normalizedData);

        return $normalizedData;
    }

    protected static function flatten(mixed $values): array
    {
        $decoded = is_string($values) ? json_decode($values, true) : $values;

        if (! is_array($decoded)) {
            return [];
        }

        $flat = [];

        foreach ($decoded as $key => $value) {
            if (is_array($value) && isset($value['code']) && is_scalar($value['code'])) {
                $flat[(string) $value['code']] = static::stringify(
                    array_diff_key($value, ['code' => null])
                );

                continue;
            }

            $flat[(string) $key] = static::stringify($value);
        }

        return $flat;
    }

    protected static function stringify(mixed $value): string
    {
        if ($value === null || $value === []) {
            return '';
        }

        if (! is_array($value)) {
            return is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        }

        $parts = [];

        foreach ($value as $key => $item) {
            $text = static::stringify($item);

            $parts[] = is_int($key) ? $text : $key.': '.$text;
        }

        return implode(', ', array_filter($parts, 'strlen'));
    }
}
