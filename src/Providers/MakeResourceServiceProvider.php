<?php

namespace MrSoneri\MakeResource\Providers;

use Illuminate\Support\ServiceProvider;
use MrSoneri\MakeResource\Commands\MakeResourceFiles;

class MakeResourceServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../Config/make-resource.php', 'make-resource'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeResourceFiles::class,
            ]);

            $this->publishes([
                __DIR__.'/../Config/make-resource.php' => config_path('make-resource.php'),
            ], 'config');
        }
    }
}
