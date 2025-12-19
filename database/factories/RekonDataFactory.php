<?php

namespace Database\Factories\Providers;

use Illuminate\Support\ServiceProvider;

class FactoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->app->singleton(RekonDataFactory::class);
        $this->app->singleton(SchoolFactory::class);
    }
}