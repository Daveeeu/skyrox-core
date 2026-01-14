<?php

namespace App\Modules\PlayerPermissions\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\PlayerPermissions\Http\Requests\Player\CheckPermissionRequest;
use App\Modules\PlayerPermissions\Http\Requests\Player\PlayerLoginRequest;
use App\Modules\PlayerPermissions\Http\Requests\Player\PlayerLogoutRequest;
use App\Modules\PlayerPermissions\Services\PlayerPermissionService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;


#[OA\Server(
    url: '/api/v1/player-permissions',
    description: 'API v1 szerver'
)]
class PlayerPermissionController extends Controller
{
    public function __construct(
        private PlayerPermissionService $playerPermissionService
    )
    {
    }

    /**
     * Játékos bejelentkezés UUID alapon
     */
    #[OA\Post(
        path: '/player/login',
        summary: 'Játékos bejelentkezés',
        description: 'Fellépti a játékost a szerverre Hytale UUID alapján',
        tags: ['Player Management']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['hytale_uuid'],
            properties: [
                new OA\Property(
                    property: 'hytale_uuid',
                    type: 'string',
                    description: 'Hytale UUID',
                    example: 'abc123-def456-789ghi'
                ),
                new OA\Property(
                    property: 'player_name',
                    type: 'string',
                    description: 'Játékos neve (opcionális)',
                    example: 'CoolPlayer'
                ),
                new OA\Property(
                    property: 'server_name',
                    type: 'string',
                    description: 'Szerver neve (opcionális)',
                    example: 'main-server'
                ),
                new OA\Property(
                    property: 'ip_address',
                    type: 'string',
                    description: 'Játékos IP címe (opcionális)',
                    example: '192.168.1.1'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Sikeres bejelentkezés',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Játékos sikeresen bejelentkezett'),
                new OA\Property(
                    property: 'permissions',
                    type: 'array',
                    items: new OA\Items(type: 'string')
                )
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Hibás kérés',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Hibás UUID formátum')
            ]
        )
    )]
    public function login(PlayerLoginRequest $request): JsonResponse
    {
        $result = $this->playerPermissionService->playerLogin(
            $request->validated('hytale_uuid'),
            $request->validated('player_name'),
            $request->validated('server_name'),
            $request->validated('ip_address')
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }


    /**
     * Játékos kijelentkezés UUID alapon
     */
    #[OA\Post(
        path: '/player/logout',
        summary: 'Játékos kijelentkezés',
        description: 'Kilépteti a játékost a szerverről UUID alapján',
        tags: ['Player Management']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['hytale_uuid'],
            properties: [
                new OA\Property(
                    property: 'hytale_uuid',
                    type: 'string',
                    description: 'Hytale UUID',
                    example: 'abc123-def456-789ghi'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Sikeres kijelentkezés',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Játékos sikeresen kijelentkezett')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Hibás kérés',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Hibás UUID formátum')
            ]
        )
    )]
    public function logout(PlayerLogoutRequest $request): JsonResponse
    {
        $result = $this->playerPermissionService->playerLogout(
            $request->validated('hytale_uuid')
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Permission ellenőrzés UUID alapon
     */
    #[OA\Get(
        path: '/player/permission/check',
        summary: 'Jogosultság ellenőrzése',
        description: 'Ellenőrzi, hogy az adott Hytale UUID-val rendelkező játékosnak van-e jogosultsága',
        tags: ['Permission Management']
    )]
    #[OA\Parameter(
        name: 'hytale_uuid',
        in: 'query',
        required: true,
        description: 'Hytale UUID',
        schema: new OA\Schema(type: 'string', example: 'abc123-def456-789ghi')
    )]
    #[OA\Parameter(
        name: 'permission',
        in: 'query',
        required: true,
        description: 'Jogosultság neve',
        schema: new OA\Schema(type: 'string', example: 'game.build')
    )]
    #[OA\Response(
        response: 200,
        description: 'Jogosultság ellenőrzése sikeres',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Jogosultság megvan'),
                new OA\Property(property: 'has_permission', type: 'boolean', example: true)
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Hibás kérés',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Hibás paraméterek')
            ]
        )
    )]
    public function checkPermission(CheckPermissionRequest $request): JsonResponse
    {
        $result = $this->playerPermissionService->checkPermission(
            $request->validated('hytale_uuid'),
            $request->validated('permission')
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Online játékosok listája
     */
    #[OA\Get(
        path: '/player/online',
        summary: 'Online játékosok listája',
        description: 'Visszaadja az összes jelenleg online játékost',
        tags: ['Player Management']
    )]
    #[OA\Response(
        response: 200,
        description: 'Sikeres lekérdezés',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Online játékosok betöltve'),
                new OA\Property(
                    property: 'players',
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'hytale_uuid', type: 'string'),
                            new OA\Property(property: 'player_name', type: 'string'),
                            new OA\Property(property: 'server_name', type: 'string'),
                            new OA\Property(property: 'ip_address', type: 'string')
                        ]
                    )
                )
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Hibás kérés',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Hiba történt')
            ]
        )
    )]
    public function getOnlinePlayers(): JsonResponse
    {
        $result = $this->playerPermissionService->getOnlinePlayers();
        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Játékos részletes adatai UUID alapon
     */
    #[OA\Get(
        path: '/player/{hytaleUuid}/details',
        summary: 'Játékos részletes adatai',
        description: 'Visszaadja az adott UUID-val rendelkező játékos összes adatát',
        tags: ['Player Management']
    )]
    #[OA\Parameter(
        name: 'hytaleUuid',
        in: 'path',
        required: true,
        description: 'Hytale UUID',
        schema: new OA\Schema(type: 'string', example: 'abc123-def456-789ghi')
    )]
    #[OA\Response(
        response: 200,
        description: 'Sikeres lekérdezés',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Játékos adatok betöltve'),
                new OA\Property(property: 'hytale_uuid', type: 'string', example: 'abc123-def456-789ghi'),
                new OA\Property(property: 'player_name', type: 'string', example: 'CoolPlayer'),
                new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string'))
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Játékos nem található',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Játékos nem található')
            ]
        )
    )]
    public function getPlayerDetails(string $hytaleUuid): JsonResponse
    {
        $result = $this->playerPermissionService->getPlayerDetails($hytaleUuid);
        return response()->json($result, $result['success'] ? 200 : 404);
    }

    /**
     * Cache invalidálás
     */
    #[OA\Post(
        path: '/player/cache/invalidate',
        summary: 'Cache invalidálás',
        description: 'Invalidálja a Redis cache-t egy adott UUID-ra vagy az összes játékosra',
        tags: ['Cache Management']
    )]
    #[OA\RequestBody(
        required: false,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'hytale_uuid',
                    type: 'string',
                    description: 'Specifikus UUID (opcionális)',
                    example: 'abc123-def456-789ghi'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Cache sikeresen invalidálva',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Összes player cache invalidálva')
            ]
        )
    )]
    public function invalidateCache(): JsonResponse
    {
        $hytaleUuid = request('hytale_uuid');
        $this->playerPermissionService->invalidateCache($hytaleUuid);

        return response()->json([
            'success' => true,
            'message' => $hytaleUuid
                ? "Cache invalidálva a {$hytaleUuid} UUID-ra"
                : 'Összes player cache invalidálva'
        ]);
    }
}
