<?php

namespace App\Modules\PlayerPermissions\Providers;

use App\Modules\PlayerPermissions\Middleware\CheckPlayerPermission;
use App\Modules\PlayerPermissions\Services\PlayerPermissionService;
use Illuminate\Support\ServiceProvider;
use App\Modules\PlayerPermissions\Console\Commands\PlayerPermissionCommand;
use App\Modules\PlayerPermissions\Services\PlayerPermissionRedisService;
use Illuminate\Support\Facades\Route;

class PlayerPermissionsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge the module configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../config/player_permission_config.php',
            'player-permissions'
        );

        // Bind services
        $this->app->singleton(PlayerPermissionRedisService::class);
        $this->app->singleton(PlayerPermissionService::class);

        // Register middleware
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Load routes with API middleware group
        $this->app['router']
            ->middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../../routes/api.php');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                PlayerPermissionCommand::class,
            ]);
        }

        // Register middleware
        $router = $this->app['router'];

        // Publish config if needed
        $this->publishes([
            __DIR__.'/../../config/player_permission_config.php' => config_path('player-permissions.php'),
        ], 'player-permissions-config');

        // Publish migrations if needed
        $this->publishes([
            __DIR__.'/../../database/migrations/' => database_path('migrations'),
        ], 'player-permissions-migrations');
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            PlayerPermissionService::class,
            PlayerPermissionRedisService::class,
            'player.check.permission',
        ];
    }
}
