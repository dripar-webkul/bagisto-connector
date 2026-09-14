<?php

namespace Webkul\Bagisto\Presenters;

use Webkul\Bagisto\Models\Credential;
use Webkul\HistoryControl\Interfaces\HistoryPresenterInterface;

class CredentialPresenter implements HistoryPresenterInterface
{
    public const FILTERABLE_ATTRIBUTES_KEY = 'filterableAttribtes';

    public const FILTERABLE_ATTRIBUTE_LABELS_KEY = 'filterableAttribteLabels';

    public static function representValueForHistory(mixed $oldValues, mixed $newValues, string $fieldName): array
    {
        $rawOld = static::extractRawValue($oldValues, $fieldName);
        $rawNew = static::extractRawValue($newValues, $fieldName);

        if ($rawOld === $rawNew) {
            return [];
        }

        $historyKey = $fieldName === 'additional_info' ? static::FILTERABLE_ATTRIBUTES_KEY : $fieldName;

        return [
            $historyKey => [
                'name' => $historyKey,
                'old'  => static::normalizeValue($oldValues, $fieldName),
                'new'  => static::normalizeValue($newValues, $fieldName),
            ],
        ];
    }

    protected static function extractRawValue(mixed $value, string $fieldName): mixed
    {
        if ($fieldName !== 'additional_info') {
            return $value;
        }

        $decoded = static::decode($value);

        return $decoded[0][static::FILTERABLE_ATTRIBUTES_KEY] ?? $decoded;
    }

    protected static function normalizeValue(mixed $value, string $fieldName): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($fieldName === 'password') {
            return Credential::MASKED_PASSWORD;
        }

        if ($fieldName === 'additional_info') {
            return static::representFilterableAttributes(static::decode($value));
        }

        return is_array($value) ? json_encode($value) : (string) $value;
    }

    protected static function representFilterableAttributes(array $decoded): string
    {
        foreach ($decoded as $entry) {
            if (! is_array($entry) || ! isset($entry[static::FILTERABLE_ATTRIBUTES_KEY])) {
                continue;
            }

            $ids = array_filter(array_map('trim', explode(',', (string) $entry[static::FILTERABLE_ATTRIBUTES_KEY])), 'strlen');

            if ($ids === []) {
                return '';
            }

            $labels = $entry[static::FILTERABLE_ATTRIBUTE_LABELS_KEY] ?? [];

            return implode(', ', array_map(
                fn (string $id): string => $labels[$id] ?? $id,
                $ids
            ));
        }

        return '';
    }

    protected static function decode(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $decoded : [];
    }
}
