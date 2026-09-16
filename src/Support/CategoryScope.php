<?php

namespace Webkul\Bagisto\Support;

use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Core\Repositories\ChannelRepository;

class CategoryScope
{
    public static function rootIds(array $channelCodes): array
    {
        $repository = app(ChannelRepository::class);

        $channels = $channelCodes === []
            ? $repository->all()
            : $repository->whereIn('code', $channelCodes)->get();

        return collect($channels)
            ->pluck('root_category_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function codesWithin(array $codes, array $rootIds): array
    {
        if ($codes === [] || $rootIds === []) {
            return $codes;
        }

        return app(CategoryRepository::class)
            ->whereIn('code', $codes)
            ->where(fn ($builder) => self::withinRoots($builder, $rootIds))
            ->pluck('code')
            ->all();
    }

    public static function withinRoots($builder, array $rootIds)
    {
        foreach ($rootIds as $rootId) {
            $builder->whereDescendantOrSelf($rootId, 'or');
        }

        return $builder;
    }
}
