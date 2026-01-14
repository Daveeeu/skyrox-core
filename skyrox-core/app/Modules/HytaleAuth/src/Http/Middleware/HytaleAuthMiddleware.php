<?php

namespace App\Modules\HytaleAuth\Http\Middleware;

use App\Modules\HytaleAuth\Services\HytaleAuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as ResponseInterface;

class HytaleAuthMiddleware
{
    public function __construct(
        private HytaleAuthService $hytaleAuthService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$scopes): ResponseInterface
    {
        // Bearer token kinyerése
        $accessToken = $request->bearerToken();
        
        if (!$accessToken) {
            return $this->unauthorizedResponse('Access token required');
        }

        // Token validálása
        $validation = $this->hytaleAuthService->validateTokenAndGetUser($accessToken);
        
        if (!$validation['success']) {
            return $this->unauthorizedResponse($validation['error'] ?? 'Invalid token');
        }

        // Scope ellenőrzés (ha van megadva)
        if (!empty($scopes)) {
            $tokenScopes = explode(' ', $validation['token_data']['scope'] ?? '');
            
            $hasRequiredScope = false;
            foreach ($scopes as $requiredScope) {
                if (in_array($requiredScope, $tokenScopes)) {
                    $hasRequiredScope = true;
                    break;
                }
            }

            if (!$hasRequiredScope) {
                return $this->forbiddenResponse('Insufficient scope permissions');
            }
        }

        // User és token adatok hozzáadása a request-hez
        $request->attributes->add([
            'hytale_user' => $validation['user'],
            'hytale_token' => $validation['token_data'],
            'hytale_session' => $validation['session'] ?? null,
        ]);

        // User hozzáadása a request-hez (Laravel kompatibilitás)
        $request->setUserResolver(function () use ($validation) {
            return $validation['user'];
        });

        return $next($request);
    }

    /**
     * Unauthorized response
     */
    private function unauthorizedResponse(string $message): ResponseInterface
    {
        return response()->json([
            'success' => false,
            'error' => $message,
            'code' => 'UNAUTHORIZED'
        ], 401);
    }

    /**
     * Forbidden response
     */
    private function forbiddenResponse(string $message): ResponseInterface
    {
        return response()->json([
            'success' => false,
            'error' => $message,
            'code' => 'FORBIDDEN'
        ], 403);
    }
}
