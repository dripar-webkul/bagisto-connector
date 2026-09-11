<?php

namespace Webkul\Bagisto\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\Bagisto\Console\Commands\BagistoInstaller;
use Webkul\Bagisto\Console\Commands\InstallSampleData;
use Webkul\DataTransfer\Helpers\Export;

class BagistoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__.'/../Routes/bagisto-routes.php');

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'bagisto');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'bagisto');

        Event::listen('unopim.admin.layout.head', function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('bagisto::style');
        });

        foreach ([
            'unopim.admin.settings.data_transfer.exports.create.card.scope.after' => 'bagisto::exports.filters',
            'unopim.admin.settings.data_transfer.exports.edit.card.general.after' => 'bagisto::exports.filters-edit',
        ] as $exportFilterHook => $template) {
            Event::listen($exportFilterHook, function ($viewRenderEventManager) use ($template) {
                $viewRenderEventManager->addTemplate($template);
            });
        }

        $this->publishes([
            __DIR__.'/../../publishable' => public_path('themes'),
        ], 'unopim-bagisto-connector');

        $this->app->register(ModuleServiceProvider::class);

        $this->app->register(EventServiceProvider::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                BagistoInstaller::class,
                InstallSampleData::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->registerConfig();

        $this->app->bind(
            Export::class,
            \Webkul\Bagisto\Helpers\Export::class
        );
    }

    public function registerConfig(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/api-end-point.php', 'bagisto-api-end-point');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/bagisto-attributes.php', 'bagisto-attributes');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/bagisto-category-fields.php', 'bagisto-category-fields');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/exporters.php', 'exporters');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/unopim-vite.php', 'unopim-vite.viters');
    }
}
