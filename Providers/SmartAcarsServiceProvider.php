<?php

namespace Modules\SmartAcars\Providers;

use App\Services\ModuleService;
use Illuminate\Support\ServiceProvider;
use Route;

class SmartAcarsServiceProvider extends ServiceProvider
{
    protected $moduleSvc;

    /**
     * Boot the application events.
     */
    public function boot()
    {
        $this->moduleSvc = app(ModuleService::class);

        $this->registerRoutes();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();

        $this->registerLinks();

        $this->loadMigrationsFrom(__DIR__.'/../Database/migrations');
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        //
    }

    /**
     * Add module links here
     */
    public function registerLinks()
    {
        // Show this link if logged in
        // $this->moduleSvc->addFrontendLink('Sample', '/sample', '', $logged_in=true);

        // Admin links:
        $this->moduleSvc->addAdminLink('SmartAcars', '/admin/smartacars');
    }

    /**
     * Register the routes
     */
    protected function registerRoutes()
    {
        /*
         * Routes for the frontend
         */
        Route::group([
            'as'     => 'smartacars.',
            'prefix' => 'smartacars',
            // If you want a RESTful module, change this to 'api'
            'middleware' => ['web'],
            'namespace'  => 'Modules\SmartAcars\Http\Controllers',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../Http/Routes/web.php');
        });

        /*
         * Routes for the admin
         */
        Route::group([
            'as'     => 'smartacars.',
            'prefix' => 'admin/smartacars',
            // If you want a RESTful module, change this to 'api'
            'middleware' => ['web', 'role:admin'],
            'namespace'  => 'Modules\SmartAcars\Http\Controllers\Admin',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../Http/Routes/admin.php');
        });

        /*
         * Routes for an API
         */
        Route::group([
            'as'     => 'smartacars.',
            'prefix' => 'api/smartacars',
            // If you want a RESTful module, change this to 'api'
            'middleware' => ['api'],
            'namespace'  => 'Modules\SmartAcars\Http\Controllers\Api',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../Http/Routes/api.php');
        });
    }

    /**
     * Register config.
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('smartacars.php'),
        ], 'config');

        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'smartacars'
        );
    }

    /**
     * Register views.
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/smartacars');
        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $paths = array_map(
            function ($path) {
                return $path.'/modules/smartacars';
            },
            \Config::get('view.paths')
        );

        $paths[] = $sourcePath;
        $this->loadViewsFrom($paths, 'smartacars');
    }

    /**
     * Register translations.
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/smartacars');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'smartacars');
        } else {
            $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'smartacars');
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }
}
