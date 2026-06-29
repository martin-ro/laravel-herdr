<?php

namespace Herdr;

use Illuminate\Support\ServiceProvider;

class HerdrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/herdr.php', 'herdr');

        $this->app->singleton(ConnectionFactory::class, function ($app) {
            return new ConnectionFactory($app['config']->get('herdr', []));
        });

        $this->app->singleton('herdr', function ($app) {
            return new HerdrManager($app->make(ConnectionFactory::class));
        });

        $this->app->alias('herdr', HerdrManager::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/herdr.php' => $this->app->configPath('herdr.php'),
            ], 'herdr-config');
        }
    }
}
