<?php

namespace KyleArch\SqlGenerator\Providers;

use Illuminate\Support\ServiceProvider;
use KyleArch\SqlGenerator\Commands\SqlGeneratorCommand;

class SqlGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     * @return void
     */
    public function register()
    {
    }

    /**
     * Bootstrap the application events.
     * @return void
     */
    public function boot()
    {
        $this->commands([
            SqlGeneratorCommand::class
        ]);
    }

    /**
     * Get the services provided by the provider.
     * @return array
     */
    public function provides()
    {
        return [];
    }
}