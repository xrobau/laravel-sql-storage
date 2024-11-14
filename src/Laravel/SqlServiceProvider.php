<?php

namespace xrobau\LaravelSqlStorage\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Contracts\Foundation\Application;
use xrobau\LaravelSqlStorage\SqlStorageDriver;

class SqlServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config/default-db-storage.php', 'db-storage');
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/config/default-db-storage.php' => config_path('db-storage.php'),
        ], 'config');
        $this->loadMigrationsFrom(__DIR__ . "/database/migrations/");
        $this->app->afterResolving(FilesystemManager::class, function ($manager, $app) {
            foreach ($app['config']->get('db-storage.disks') as $i => $d) {
                $app['config']["filesystems.disks.$i"] = $d;
            }
            $manager->extend('sqlstore', function (Application $app, array $config) {
                return new SqlStorageDriver($app, $config);
            });
            return $manager;
        });
    }
}
