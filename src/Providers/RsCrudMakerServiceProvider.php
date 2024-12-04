<?php

namespace RsCrud\Providers;

use Illuminate\Support\ServiceProvider;
use RsCrud\Console\RsCrudMaker;

class RsCrudMakerServiceProvider extends ServiceProvider
{
    /**
     * Register the application's services.
     */
    public function register()
    {
        // Register the command
        $this->commands([
            RsCrudMaker::class,
        ]);
    }

    /**
     * Bootstrap the application's services.
     */
    public function boot()
    {
        // Bootstrapping logic if needed
    }
}
