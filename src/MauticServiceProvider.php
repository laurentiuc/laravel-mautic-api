<?php

namespace Triibo\Mautic;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Foundation\Application;

class MauticServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return  void
     */
    public function boot()
    {
        // Publish Configuration File
        $this->publishes([
            __DIR__ . "/config/mautic.php" => config_path("mautic.php")
        ], 'mautic-config');

        // Publish Migrations
        $this->publishes([
            __DIR__ . "/migrations" => database_path("migrations")
        ], 'mautic-migrations');

        // Publish all assets with a general tag
        $this->publishes([
            __DIR__ . "/config/mautic.php" => config_path("mautic.php"),
            __DIR__ . "/migrations"        => database_path("migrations")
        ], 'mautic');
    }

    /**
     * Register the service provider.
     *
     * @return  void
     */
    public function register()
    {
        $this->registerFactory($this->app);
        $this->registerManager($this->app);
        $this->registerRoutes($this->app);
    }

    /**
     * Register the factory class.
     *
     * @param   Application     $app
     * @return  void
     */
    protected function registerFactory(Application $app)
    {
        $app->singleton("mautic.factory", function () {
            return new Factories\MauticFactory();
        });

        $app->alias("mautic.factory", "Triibo\Mautic\Factories\MauticFactory");
    }

    /**
     * Register the manager class.
     *
     * @param   Application     $app
     * @return  void
     */
    protected function registerManager(Application $app)
    {
        $app->singleton("mautic", function ($app) {
            $config  = $app["config"];
            $factory = $app["mautic.factory"];

            return new Mautic($config, $factory);
        });

        $app->alias("mautic", "Triibo\Mautic\Mautic");
    }

    /**
     * Get the routes services provided by the provider.
     *
     * @param   Application     $app
     * @return  void
     */
    protected function registerRoutes(Application $app)
    {
        $app["router"]->group(
            [
                "namespace" => "Triibo\Mautic\Http\Controllers",
                "prefix"    => "mautic"
            ],
            function () {
                require __DIR__ . "/Http/routes.php";
            }
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return  array
     */
    public function provides()
    {
        return [
            "mautic",
            "mautic.factory",
        ];
    }
}
