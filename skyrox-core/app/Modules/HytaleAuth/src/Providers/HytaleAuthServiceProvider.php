<?php

namespace App\Modules\HytaleAuth\Providers;

use App\Modules\HytaleAuth\Http\Middleware\HytaleAuthMiddleware;
use App\Modules\HytaleAuth\Services\HytaleAuthService;
use App\Modules\HytaleAuth\Services\Auth0Service;
use App\Modules\HytaleAuth\Services\TokenService;
use Illuminate\Support\ServiceProvider;
use App\Modules\HytaleAuth\Console\Commands\HytaleAuthCommand;
use Illuminate\Support\Facades\Route;

class HytaleAuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge the module configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../config/hytale_auth.php',
            'hytale-auth'
        );

        // Bind services
        $this->app->singleton(HytaleOAuth2Service::class);
        $this->app->singleton(TokenService::class);
        $this->app->singleton(HytaleAuthService::class);

        // Register middleware
        $this->app->singleton('hytale.auth', HytaleAuthMiddleware::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Load routes with API middleware
        Route::middleware('api')
            ->group(__DIR__.'/../../routes/api.php');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                HytaleAuthCommand::class,
            ]);
        }

        // Register middleware
        $router = $this->app['router'];
        $router->aliasMiddleware('hytale.auth', HytaleAuthMiddleware::class);

        // Publish config if needed
        $this->publishes([
            __DIR__.'/../../config/hytale_auth.php' => config_path('hytale-auth.php'),
        ], 'hytale-auth-config');

        // Publish migrations if needed
        $this->publishes([
            __DIR__.'/../../database/migrations/' => database_path('migrations'),
        ], 'hytale-auth-migrations');
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            HytaleAuthService::class,
            HytaleOAuth2Service::class,
            TokenService::class,
            'hytale.auth',
        ];
    }
}
