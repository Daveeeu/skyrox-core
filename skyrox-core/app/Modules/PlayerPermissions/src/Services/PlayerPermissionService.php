<?php

namespace App\Modules\PlayerPermissions\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PlayerPermissionService
{
    public function __construct(
        private PlayerPermissionRedisService $redisService
    ) {}

    /**
     * Játékos bejelentkezés Hytale UUID alapon
     */
    public function playerLogin(string $hytaleUuid, ?string $playerName = null, ?string $serverName = null, ?string $ipAddress = null): array
    {
        try {
            DB::beginTransaction();

            // User keresése UUID alapján vagy létrehozása
            $user = User::where('hytale_uuid', $hytaleUuid)->first();
            $isFirstLogin = false;

            if (!$user) {
                // First login - új Hytale játékos létrehozása
                $user = User::create([
                    'name' => $playerName ?? "HytalePlayer_" . substr($hytaleUuid, 0, 8),
                    'email' => "hytale_" . substr($hytaleUuid, 0, 8) . "@hytale.local",
                    'password' => bcrypt(Str::random(32)),
                    'hytale_uuid' => $hytaleUuid,
                    'first_login_at' => now(),
                ]);

                // Alapértelmezett 'player' role
                $defaultRole = \App\Modules\PlayerPermissions\Models\Role::where('name', 'player')->first();
                if ($defaultRole) {
                    $user->assignRole($defaultRole);
                }

                $isFirstLogin = true;

                Log::info('Hytale player first login - user created', [
                    'hytale_uuid' => $hytaleUuid,
                    'player_name' => $playerName,
                ]);
            }

            // Session ID generálása
            $sessionId = 'hytale_' . Str::uuid()->toString();

            // Bejelentkezés
            $session = $user->login($sessionId, $serverName, $ipAddress);

            // Redis cache frissítése
            $this->redisService->cachePlayerData($user);

            DB::commit();

            // Role és permissions
            $primaryRole = $user->roles()->where('is_active', true)->first();
            $allPermissions = $user->getAllPermissions();

            return [
                'success' => true,
                'hytale_uuid' => $hytaleUuid,
                'role_name' => $primaryRole?->name ?? 'guest',
                'message' => $isFirstLogin
                    ? "Első bejelentkezés! Üdvözöljük a Hytale világában, {$user->name}!"
                    : "Sikeres bejelentkezés! Üdvözöljük vissza, {$user->name}!",
                'session_id' => $sessionId,
                'is_first_login' => $isFirstLogin,
                'permissions' => [
                    'list' => $allPermissions,
                    'string' => implode(',', $allPermissions),
                    'count' => count($allPermissions),
                ],
                'hytale_specific' => [
                    'server_name' => $serverName,
                    'login_timestamp' => now()->toISOString(),
                    'session_type' => 'hytale_game_session',
                ],
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Hytale player login failed', [
                'hytale_uuid' => $hytaleUuid,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'role_name' => 'guest',
                'message' => 'Hytale bejelentkezési hiba: ' . $e->getMessage(),
                'permissions' => [
                    'list' => [],
                    'string' => '',
                    'count' => 0,
                ],
            ];
        }
    }

    /**
     * Játékos kijelentkezés UUID alapon
     */
    public function playerLogout(string $hytaleUuid): array
    {
        try {
            DB::beginTransaction();

            $user = User::where('hytale_uuid', $hytaleUuid)->first();

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Játékos nem található a megadott UUID-val.',
                ];
            }

            $user->logout();
            $this->redisService->cachePlayerData($user);

            DB::commit();

            return [
                'success' => true,
                'message' => "Sikeres kijelentkezés! Viszlát, {$user->name}!",
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'Kijelentkezési hiba: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Permission ellenőrzés UUID alapon
     */
    public function checkPermission(string $hytaleUuid, string $permission): array
    {
        try {
            // Redis cache-ből próbálunk
            if ($this->redisService->hasPermissionByUuid($hytaleUuid, $permission)) {
                return [
                    'success' => true,
                    'has_permission' => true,
                    'message' => 'Jogosultság megvan.',
                    'source' => 'cache',
                ];
            }

            // Adatbázisból
            $user = User::where('hytale_uuid', $hytaleUuid)->first();

            if (!$user) {
                return [
                    'success' => false,
                    'has_permission' => false,
                    'message' => 'Játékos nem található.',
                ];
            }

            $hasPermission = $user->hasPermission($permission);
            $this->redisService->cachePlayerData($user);

            return [
                'success' => true,
                'has_permission' => $hasPermission,
                'message' => $hasPermission ? 'Jogosultság megvan.' : 'Nincs jogosultsága.',
                'source' => 'database',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'has_permission' => false,
                'message' => 'Jogosultság ellenőrzési hiba: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Online játékosok listája
     */
    public function getOnlinePlayers(): array
    {
        try {
            $onlinePlayers = $this->redisService->getOnlinePlayersList();

            return [
                'success' => true,
                'players' => $onlinePlayers,
                'count' => count($onlinePlayers),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'players' => [],
                'count' => 0,
                'message' => 'Online játékosok lekérési hiba: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Játékos részletes adatai UUID alapon
     */
    public function getPlayerDetails(string $hytaleUuid): array
    {
        try {
            $user = User::where('hytale_uuid', $hytaleUuid)
                ->with(['roles.permissions', 'playerSessions'])
                ->first();

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Hytale játékos nem található a megadott UUID-val.',
                ];
            }

            $allPermissions = $user->getAllPermissions();

            return [
                'success' => true,
                'player' => [
                    'id' => $user->id,
                    'username' => $user->name,
                    'email' => $user->email,
                    'hytale_uuid' => $user->hytale_uuid,
                    'roles' => $user->getRoleNames(),
                    'permissions' => [
                        'list' => $allPermissions,
                        'string' => implode(',', $allPermissions),
                        'count' => count($allPermissions),
                    ],
                    'is_online' => $user->isOnline(),
                    'active_session' => $user->activeSession(),
                    'last_sessions' => $user->playerSessions()->latest()->take(5)->get(),
                    'hytale_specific' => [
                        'first_login_at' => $user->first_login_at,
                        'total_playtime' => $user->total_playtime ?? 0,
                        'last_server' => $user->activeSession()?->server_name,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                    ],
                ],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Hytale játékos adatok lekérési hiba: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Cache invalidálás
     */
    public function invalidateCache(?string $hytaleUuid = null): void
    {
        if ($hytaleUuid) {
            $user = User::where('hytale_uuid', $hytaleUuid)->first();
            if ($user) {
                $this->redisService->cachePlayerData($user);
            }
        } else {
            $this->redisService->invalidateAllPlayerData();
        }
    }
}
