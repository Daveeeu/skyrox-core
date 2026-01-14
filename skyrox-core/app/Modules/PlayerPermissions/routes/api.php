<?php

use App\Modules\PlayerPermissions\Http\Controllers\Api\PlayerPermissionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/player-permissions')->group(function () {
    Route::post('/login', [PlayerPermissionController::class, 'login']);
    Route::post('/logout', [PlayerPermissionController::class, 'logout']);
    Route::get('/permission/check', [PlayerPermissionController::class, 'checkPermission']);
    Route::get('/online', [PlayerPermissionController::class, 'getOnlinePlayers']);
    Route::get('/{hytaleUuid}/details', [PlayerPermissionController::class, 'getPlayerDetails']);
    Route::post('/cache/invalidate', [PlayerPermissionController::class, 'invalidateCache']);
});
