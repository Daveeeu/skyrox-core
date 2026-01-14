<?php

namespace App\Modules\PlayerPermissions\Middleware;

use App\Modules\PlayerPermissions\Services\PlayerPermissionRedisService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlayerPermission
{
    public function __construct(
        private PlayerPermissionRedisService $redisService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $userId = $this->extractUserId($request);

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Felhasználó azonosító hiányzik.'
            ], 401);
        }

        if (!$this->hasPermission($userId, $permission)) {
            return response()->json([
                'success' => false,
                'message' => "Nincs jogosultsága ehhez a művelethez: {$permission}"
            ], 403);
        }

        return $next($request);
    }

    /**
     * Felhasználó ID kinyerése a requestből
     */
    private function extractUserId(Request $request): ?int
    {
        // Query paraméterből
        if ($request->has('user_id')) {
            return (int) $request->get('user_id');
        }

        // JSON body-ból
        if ($request->json('user_id')) {
            return (int) $request->json('user_id');
        }

        // Route paraméterből
        if ($request->route('userId')) {
            return (int) $request->route('userId');
        }

        // Header-ből (pl. X-User-ID)
        if ($request->header('X-User-ID')) {
            return (int) $request->header('X-User-ID');
        }

        return null;
    }

    /**
     * Jogosultság ellenőrzése
     */
    private function hasPermission(int $userId, string $permission): bool
    {
        return $this->redisService->hasPermission($userId, $permission);
    }
}
