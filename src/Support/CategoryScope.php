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

    public static function scopedCodes(array $codes, array $channelCodes): array
    {
        if ($codes === []) {
            return $codes;
        }

        $rootIds = self::rootIds($channelCodes);

        if ($rootIds !== []) {
            return self::codesWithin($codes, $rootIds);
        }

        return $channelCodes === [] ? $codes : [];
    }

    public static function scopeQuery(mixed $source, array $channelCodes): mixed
    {
        $rootIds = self::rootIds($channelCodes);

        if ($rootIds !== []) {
            return $source->where(fn ($builder) => self::withinRoots($builder, $rootIds));
        }

        return $channelCodes === [] ? $source : $source->whereRaw('1 = 0');
    }

    protected static function codesWithin(array $codes, array $rootIds): array
    {
        return app(CategoryRepository::class)
            ->whereIn('code', $codes)
            ->where(fn ($builder) => self::withinRoots($builder, $rootIds))
            ->pluck('code')
            ->all();
    }

    protected static function withinRoots($builder, array $rootIds)
    {
        foreach ($rootIds as $rootId) {
            $builder->whereDescendantOrSelf($rootId, 'or');
        }

        return $builder;
    }
}
