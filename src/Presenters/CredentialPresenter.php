<?php

namespace Webkul\Bagisto\Presenters;

use Webkul\Attribute\Models\Attribute;
use Webkul\HistoryControl\Interfaces\HistoryPresenterInterface;

class CredentialPresenter implements HistoryPresenterInterface
{
    public static function representValueForHistory(mixed $oldValues, mixed $newValues, string $fieldName): array
    {
        $rawOld = static::extractRawValue($oldValues, $fieldName);
        $rawNew = static::extractRawValue($newValues, $fieldName);

        if ($rawOld === $rawNew) {
            return [];
        }

        $historyKey = $fieldName === 'additional_info' ? 'filterableAttribtes' : $fieldName;
        $oldValue = static::normalizeValue($oldValues, $fieldName);
        $newValue = static::normalizeValue($newValues, $fieldName);

        return [
            $historyKey => [
                'name' => $historyKey,
                'old'  => $oldValue,
                'new'  => $newValue,
            ],
        ];
    }

    protected static function extractRawValue(mixed $value, string $fieldName): mixed
    {
        if ($fieldName === 'additional_info') {
            $decoded = is_string($value) ? json_decode($value, true) : $value;

            if (is_array($decoded) && isset($decoded[0]['filterableAttribtes'])) {
                return $decoded[0]['filterableAttribtes'];
            }

            return $decoded;
        }

        return $value;
    }

    protected static function normalizeValue(mixed $value, string $fieldName): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($fieldName === 'password') {
            $text = is_string($value) ? $value : json_encode($value);

            return strlen($text) > 0 ? str_repeat('*', max(1, strlen($text))) : '';
        }

        if ($fieldName === 'additional_info') {
            $decoded = is_string($value) ? json_decode($value, true) : $value;
            $decoded = is_array($decoded) ? $decoded : [];

            foreach ($decoded as $index => $entry) {
                if (! is_array($entry) || ! isset($entry['filterableAttribtes'])) {
                    continue;
                }

                $ids = array_filter(array_map('trim', explode(',', (string) $entry['filterableAttribtes'])));
                if ($ids === []) {
                    return '';
                }

                $names = [];
                foreach ($ids as $id) {
                    $attribute = Attribute::query()->find($id);
                    if ($attribute) {
                        $names[] = $attribute->name ?: $attribute->code;
                    } else {
                        $names[] = $id;
                    }
                }

                return implode(', ', $names);
            }

            return '';
        }

        return is_array($value) ? json_encode($value) : (string) $value;
    }
}
