<?php

namespace App\Modules\HytaleAuth\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\HytaleAuth\Services\HytaleAuthService;
use App\Modules\HytaleAuth\Http\Requests\DeviceCodeRequest;
use App\Modules\HytaleAuth\Http\Requests\RefreshTokenRequest;
use App\Modules\HytaleAuth\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Server(
    url: '/api/v1',
    description: 'API v1 szerver'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
class HytaleAuthController extends Controller
{
    public function __construct(
        private HytaleAuthService $hytaleAuthService
    ) {}

    /**
     * Hytale Device Code Flow indítása
     */
    #[OA\Post(
        path: '/auth/login',
        summary: 'Device Code Flow indítása',
        description: 'Hytale OAuth2 Device Code Flow indítása - visszaadja a device code-ot és user code-ot',
        tags: ['Authentication']
    )]
    #[OA\Response(
        response: 200,
        description: 'Device Code Flow sikeresen indítva',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'device_code', type: 'string', description: 'Device code (belső használatra)'),
                new OA\Property(property: 'user_code', type: 'string', example: 'ABCD-1234', description: 'User code (felhasználó által beírandó)'),
                new OA\Property(property: 'verification_uri', type: 'string', example: 'https://accounts.hytale.com/device'),
                new OA\Property(property: 'verification_uri_complete', type: 'string', example: 'https://accounts.hytale.com/device?user_code=ABCD-1234'),
                new OA\Property(property: 'expires_in', type: 'integer', example: 900, description: 'Lejárat másodpercben'),
                new OA\Property(property: 'interval', type: 'integer', example: 5, description: 'Polling intervallum másodpercben'),
                new OA\Property(
                    property: 'instructions',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'step1', type: 'string'),
                        new OA\Property(property: 'step2', type: 'string'),
                        new OA\Property(property: 'or', type: 'string')
                    ]
                )
            ]
        )
    )]
    public function initiateLogin(Request $request): JsonResponse
    {
        $options = $request->only(['scope']);
        $result = $this->hytaleAuthService->initiateLogin($options);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Device Code polling (token várakozás)
     */
    #[OA\Post(
        path: '/auth/poll',
        summary: 'Device Code polling',
        description: 'Device Code státusz ellenőrzése és token megszerzése',
        tags: ['Authentication']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['device_code', 'user_code'],
            properties: [
                new OA\Property(
                    property: 'device_code',
                    type: 'string',
                    description: 'Device code (az initiateLogin-ből)'
                ),
                new OA\Property(
                    property: 'user_code',
                    type: 'string',
                    description: 'User code (ABCD-1234 formátum)',
                    example: 'ABCD-1234'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Sikeres autentikáció',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'user',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'hytale_uuid', type: 'string'),
                        new OA\Property(property: 'hytale_player_id', type: 'string'),
                        new OA\Property(property: 'username', type: 'string'),
                        new OA\Property(property: 'display_name', type: 'string')
                    ]
                ),
                new OA\Property(
                    property: 'tokens',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'access_token', type: 'string'),
                        new OA\Property(property: 'refresh_token', type: 'string'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                        new OA\Property(property: 'expires_in', type: 'integer')
                    ]
                ),
                new OA\Property(
                    property: 'hytale',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'profiles', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'selected_profile', type: 'object'),
                        new OA\Property(property: 'owner_uuid', type: 'string')
                    ]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 202,
        description: 'Várakozás a felhasználói engedélyezésre',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'status', type: 'string', example: 'pending'),
                new OA\Property(property: 'message', type: 'string', example: 'Waiting for user authorization...')
            ]
        )
    )]
    public function pollDeviceToken(DeviceCodeRequest $request): JsonResponse
    {
        // validated() NEM létezik FormRequest-ben - ez a hiba!
        $deviceCode = $request->input('device_code');
        $userCode = $request->input('user_code');

        // Validáció kézi ellenőrzése
        if (!$request->validated()) {
            return response()->json(['error' => 'Validation failed'], 422);
        }

        $result = $this->hytaleAuthService->pollDeviceToken($deviceCode, $userCode);

        if (!$result['success']) {
            $statusCode = match($result['status'] ?? 'error') {
                'pending' => 202,
                'slow_down' => 429,
                'expired' => 410,
                'denied' => 403,
                default => 400,
            };

            return response()->json($result, $statusCode);
        }

        return response()->json($result, 200);
    }


    /**
     * OAuth2 callback kezelése (DEPRECATED)
     */
    #[OA\Post(
        path: '/auth/callback',
        summary: 'OAuth2 callback kezelése (DEPRECATED)',
        description: 'Ez a metódus elavult. Használd a Device Code Flow-t (/auth/login és /auth/poll).',
        deprecated: true,
        tags: ['Authentication']
    )]
    public function handleCallback(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'This endpoint is deprecated. Use Device Code Flow instead.',
            'migration' => [
                'step1' => 'POST /api/v1/auth/login (Device Code Flow indítása)',
                'step2' => 'POST /api/v1/auth/poll (polling device_code és user_code-dal)',
                'documentation' => 'https://support.hytale.com/hc/en-us/articles/45328341414043-Server-Provider-Authentication-Guide',
            ],
        ], 410);
    }

    /**
     * Token refresh
     */
    #[OA\Post(
        path: '/auth/refresh',
        summary: 'Access token frissítése',
        description: 'Refresh token használatával új access token generálása',
        tags: ['Authentication']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['refresh_token'],
            properties: [
                new OA\Property(
                    property: 'refresh_token',
                    type: 'string',
                    description: 'Refresh token',
                    example: 'hyr_...'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Token sikeresen frissítve',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'access_token', type: 'string'),
                new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                new OA\Property(property: 'expires_in', type: 'integer'),
                new OA\Property(property: 'scope', type: 'string')
            ]
        )
    )]
    public function refreshToken(RefreshTokenRequest $request): JsonResponse
    {
        $result = $this->hytaleAuthService->refreshToken(
            $request->validated('refresh_token')
        );

        return response()->json($result, $result['success'] ? 200 : 401);
    }

    /**
     * Logout
     */
    #[OA\Post(
        path: '/auth/logout',
        summary: 'Kijelentkezés',
        description: 'Tokenek visszavonása és session lezárása',
        security: [['bearerAuth' => []]],
        tags: ['Authentication']
    )]
    #[OA\Response(
        response: 200,
        description: 'Sikeres kijelentkezés',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Logout successful'),
                new OA\Property(property: 'logout_url', type: 'string', example: 'https://hytale.auth0.com/v2/logout?...')
            ]
        )
    )]
    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->bearerToken();
        $result = $this->hytaleAuthService->logout($accessToken);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Aktuális user lekérése
     */
    #[OA\Get(
        path: '/auth/me',
        summary: 'Aktuális user adatok',
        description: 'Bejelentkezett user adatainak lekérése',
        security: [['bearerAuth' => []]],
        tags: ['User Management']
    )]
    #[OA\Response(
        response: 200,
        description: 'User adatok',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'user',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'hytale_uuid', type: 'string'),
                        new OA\Property(property: 'username', type: 'string'),
                        new OA\Property(property: 'email', type: 'string'),
                        new OA\Property(property: 'display_name', type: 'string'),
                        new OA\Property(property: 'avatar_url', type: 'string'),
                        new OA\Property(property: 'locale', type: 'string'),
                        new OA\Property(property: 'is_verified', type: 'boolean'),
                        new OA\Property(property: 'profile_completeness', type: 'integer'),
                        new OA\Property(property: 'is_online', type: 'boolean'),
                        new OA\Property(property: 'last_login_at', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'login_count', type: 'integer')
                    ]
                )
            ]
        )
    )]
    public function me(Request $request): JsonResponse
    {
        $accessToken = $request->bearerToken();

        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'error' => 'Access token required'
            ], 401);
        }

        $result = $this->hytaleAuthService->validateTokenAndGetUser($accessToken);

        return response()->json($result, $result['success'] ? 200 : 401);
    }

    /**
     * User profil frissítése
     */
    #[OA\Put(
        path: '/auth/profile',
        summary: 'Profil frissítése',
        description: 'Bejelentkezett user profiljának frissítése',
        security: [['bearerAuth' => []]],
        tags: ['User Management']
    )]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'display_name', type: 'string'),
                new OA\Property(property: 'avatar_url', type: 'string'),
                new OA\Property(property: 'locale', type: 'string'),
                new OA\Property(property: 'timezone', type: 'string'),
                new OA\Property(
                    property: 'preferences',
                    type: 'object',
                    description: 'User preferences (key-value pairs)'
                )
            ]
        )
    )]
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $accessToken = $request->bearerToken();

        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'error' => 'Access token required'
            ], 401);
        }

        $validation = $this->hytaleAuthService->validateTokenAndGetUser($accessToken);

        if (!$validation['success']) {
            return response()->json($validation, 401);
        }

        $result = $this->hytaleAuthService->updateUserProfile(
            $validation['user'],
            $request->validated()
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * User session-jeinek lekérése
     */
    #[OA\Get(
        path: '/auth/sessions',
        summary: 'User session-jei',
        description: 'Bejelentkezett user aktív és korábbi session-jeinek listája',
        security: [['bearerAuth' => []]],
        tags: ['Session Management']
    )]
    public function getSessions(Request $request): JsonResponse
    {
        $accessToken = $request->bearerToken();

        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'error' => 'Access token required'
            ], 401);
        }

        $validation = $this->hytaleAuthService->validateTokenAndGetUser($accessToken);

        if (!$validation['success']) {
            return response()->json($validation, 401);
        }

        $result = $this->hytaleAuthService->getUserSessions($validation['user']);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Token validáció (middleware használatra)
     */
    #[OA\Get(
        path: '/auth/validate',
        summary: 'Token validáció',
        description: 'Access token érvényességének ellenőrzése',
        security: [['bearerAuth' => []]],
        tags: ['Authentication']
    )]
    public function validateToken(Request $request): JsonResponse
    {
        $accessToken = $request->bearerToken();

        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'error' => 'Access token required',
                'valid' => false
            ], 401);
        }

        $result = $this->hytaleAuthService->validateTokenAndGetUser($accessToken);

        $response = [
            'success' => $result['success'],
            'valid' => $result['success'],
        ];

        if ($result['success']) {
            $response['user_id'] = $result['user']['id'];
            $response['hytale_uuid'] = $result['user']['hytale_uuid'];
            $response['token_expires_at'] = $result['token_data']['expires_at'] ?? null;
        } else {
            $response['error'] = $result['error'] ?? 'Token validation failed';
        }

        return response()->json($response, $result['success'] ? 200 : 401);
    }

    /**
     * Health check
     */
    #[OA\Get(
        path: '/auth/health',
        summary: 'Health check',
        description: 'Autentikációs rendszer állapotának ellenőrzése',
        tags: ['System']
    )]
    public function health(): JsonResponse
    {
        try {
            $status = [
                'service' => 'Hytale Authentication',
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'version' => '1.0.0',
                'features' => [
                    'oauth2' => true,
                    'auth0_integration' => true,
                    'token_refresh' => true,
                    'session_management' => true,
                    'cache' => config('hytale-auth.cache.enabled', false),
                    'webhooks' => config('hytale-auth.webhooks.enabled', false),
                ],
            ];

            // Database kapcsolat tesztelése
            try {
                \DB::connection()->getPdo();
                $status['database'] = 'connected';
            } catch (\Exception $e) {
                $status['database'] = 'disconnected';
                $status['status'] = 'degraded';
            }

            // Cache tesztelése
            if (config('hytale-auth.cache.enabled')) {
                try {
                    \Cache::put('health_check', 'ok', 10);
                    $cacheValue = \Cache::get('health_check');
                    $status['cache_status'] = $cacheValue === 'ok' ? 'working' : 'error';
                    \Cache::forget('health_check');
                } catch (\Exception $e) {
                    $status['cache_status'] = 'error';
                    $status['status'] = 'degraded';
                }
            }

            return response()->json($status, 200);

        } catch (\Exception $e) {
            return response()->json([
                'service' => 'Hytale Authentication',
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }
}
