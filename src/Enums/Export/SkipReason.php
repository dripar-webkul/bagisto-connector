<?php

namespace Webkul\Bagisto\Enums\Export;

enum SkipReason: string
{
    case MISSING_REQUIRED_FIELDS = 'missing_required_fields';

    case UNSUPPORTED_TYPE = 'unsupported_type';

    case NO_SCOPE_MATCH = 'no_scope_match';

    case REJECTED_BY_BAGISTO = 'rejected_by_bagisto';

    case REQUEST_FAILED = 'request_failed';

    case UNSUPPORTED_MEDIA_TYPE = 'unsupported_media_type';

    case UNSUPPORTED_IMAGE_FORMAT = 'unsupported_image_format';

    case MEDIA_NOT_FOUND = 'media_not_found';

    /**
     * @param  array<int, string>  $details
     */
    public function describe(string $identifier, array $details = []): string
    {
        $list = implode(', ', $details);

        return match ($this) {
            self::MISSING_REQUIRED_FIELDS => trans('bagisto::app.bagisto.export.skipped.missing-required-fields', [
                'identifier' => $identifier,
                'fields'     => $list,
            ]),
            self::UNSUPPORTED_TYPE => trans('bagisto::app.bagisto.export.skipped.unsupported-type', [
                'identifier' => $identifier,
                'type'       => $list,
            ]),
            self::NO_SCOPE_MATCH => trans('bagisto::app.bagisto.export.skipped.no-scope-match', [
                'identifier' => $identifier,
            ]),
            self::REJECTED_BY_BAGISTO => trans('bagisto::app.bagisto.export.skipped.rejected-by-bagisto', [
                'identifier' => $identifier,
                'errors'     => $list,
            ]),
            self::REQUEST_FAILED => trans('bagisto::app.bagisto.export.skipped.request-failed', [
                'identifier' => $identifier,
                'errors'     => $list,
            ]),
            self::UNSUPPORTED_MEDIA_TYPE => trans('bagisto::app.bagisto.export.skipped.unsupported-media-type', [
                'identifier' => $identifier,
                'file'       => $list,
            ]),
            self::UNSUPPORTED_IMAGE_FORMAT => trans('bagisto::app.bagisto.export.skipped.unsupported-image-format', [
                'identifier' => $identifier,
                'file'       => $list,
            ]),
            self::MEDIA_NOT_FOUND => trans('bagisto::app.bagisto.export.skipped.media-not-found', [
                'identifier' => $identifier,
                'file'       => $list,
            ]),
        };
    }
}
