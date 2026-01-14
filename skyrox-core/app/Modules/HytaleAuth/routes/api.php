<?php

use Illuminate\Support\Facades\Route;
use App\Modules\HytaleAuth\Http\Controllers\Api\HytaleAuthController;

/*
|--------------------------------------------------------------------------
| Hytale Authentication API Routes
|--------------------------------------------------------------------------
|
| OAuth2/Auth0 alapú autentikációs útvonalak Hytale szerverekhez.
| Minden endpoint /api/v1/auth prefixszel érhető el.
|
*/

Route::prefix('api/v1/auth')->group(function () {

    // Public endpoints (Device Code Flow)
    Route::post('/login', [HytaleAuthController::class, 'initiateLogin'])
        ->name('hytale.auth.login');

    Route::post('/poll', [HytaleAuthController::class, 'pollDeviceToken'])
        ->name('hytale.auth.poll');

    Route::post('/callback', [HytaleAuthController::class, 'handleCallback'])
        ->name('hytale.auth.callback'); // DEPRECATED

    Route::post('/refresh', [HytaleAuthController::class, 'refreshToken'])
        ->name('hytale.auth.refresh');

    Route::get('/health', [HytaleAuthController::class, 'health'])
        ->name('hytale.auth.health');

    // Protected endpoints (require valid access token)
    Route::middleware(['hytale.auth'])->group(function () {

        // User management
        Route::get('/me', [HytaleAuthController::class, 'me'])
            ->name('hytale.auth.me');

        Route::put('/profile', [HytaleAuthController::class, 'updateProfile'])
            ->name('hytale.auth.profile.update');

        // Session management
        Route::get('/sessions', [HytaleAuthController::class, 'getSessions'])
            ->name('hytale.auth.sessions');

        // Token validation (for middleware use)
        Route::get('/validate', [HytaleAuthController::class, 'validateToken'])
            ->name('hytale.auth.validate');

        // Logout
        Route::post('/logout', [HytaleAuthController::class, 'logout'])
            ->name('hytale.auth.logout');
    });

    // Scoped endpoints (require specific permissions)
    Route::middleware(['hytale.auth:hytale:admin'])->group(function () {

        // Admin endpoints (for future expansion)
        Route::prefix('admin')->group(function () {
            // TODO: Admin functionality
        });
    });
});

/*
|--------------------------------------------------------------------------
| Web Routes (Optional)
|--------------------------------------------------------------------------
|
| Web-based OAuth2 callback handling (ha szükséges)
|
*/

Route::middleware('web')->group(function () {

    // Web-based callback (redirect to app after auth)
    Route::get('/auth/hytale/callback', function () {
        return view('hytale-auth::callback');
    })->name('hytale.auth.web.callback');

});
