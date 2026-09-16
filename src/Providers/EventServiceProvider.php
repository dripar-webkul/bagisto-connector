<?php

namespace Webkul\Bagisto\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        'data_transfer.exports.update.after' => [
            'Webkul\Bagisto\Listeners\Export@afterUpdate',
        ],
        'data_transfer.export.completed' => [
            'Webkul\Bagisto\Listeners\Export@afterCompleted',
        ],
        'catalog.category_field.create.after' => [
            'Webkul\Bagisto\Listeners\CategoryField@afterCreateOrUpdate',
        ],

        'catalog.category_field.update.after' => [
            'Webkul\Bagisto\Listeners\CategoryField@afterCreateOrUpdate',
        ],
    ];
}
