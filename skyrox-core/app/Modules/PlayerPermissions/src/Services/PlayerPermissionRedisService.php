<?php

namespace App\Modules\PlayerPermissions\Services;

use App\Models\User;
use Illuminate\Support\Facades\Redis;

class PlayerPermissionRedisService
{
    private const CACHE_TTL = 3600;
    private const PREFIX = 'hytale:player:';

    /**
     * Játékos cache UUID alapon
     */
    public function cachePlayerData(User $user): void
    {
        $key = $this->getUuidKey($user->hytale_uuid);
        $allPermissions = $user->getAllPermissions();

        $data = [
            'user_id' => $user->id,
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
            'last_updated' => now()->toISOString(),
            'hytale_specific' => [
                'first_login_at' => $user->first_login_at,
                'total_playtime' => $user->total_playtime ?? 0,
                'last_server' => $user->activeSession()?->server_name,
                'session_type' => 'hytale_game_session',
            ],
        ];

        Redis::setex($key, self::CACHE_TTL, json_encode($data));

        // Online players list
        if ($user->isOnline()) {
            $this->addToOnlinePlayersList($user->hytale_uuid);
        } else {
            $this->removeFromOnlinePlayersList($user->hytale_uuid);
        }
    }

    /**
     * Játékos adatok UUID alapon
     */
    public function getPlayerByUuid(string $hytaleUuid): ?array
    {
        $key = $this->getUuidKey($hytaleUuid);
        $data = Redis::get($key);
        return $data ? json_decode($data, true) : null;
    }

    /**
     * Permission ellenőrzés UUID alapon
     */
    public function hasPermissionByUuid(string $hytaleUuid, string $permission): bool
    {
        $playerData = $this->getPlayerByUuid($hytaleUuid);

        if (!$playerData) {
            return false;
        }

        if (isset($playerData['permissions']['list'])) {
            return in_array($permission, $playerData['permissions']['list']);
        }

        return in_array($permission, $playerData['permissions'] ?? []);
    }

    /**
     * Online játékosok listája
     */
    public function getOnlinePlayersList(): array
    {
        $onlinePlayersKey = $this->getOnlinePlayersKey();
        $playerUuids = Redis::smembers($onlinePlayersKey);

        $onlinePlayers = [];
        foreach ($playerUuids as $uuid) {
            $playerData = $this->getPlayerByUuid($uuid);
            if ($playerData && $playerData['is_online']) {
                $onlinePlayers[] = $playerData;
            } else {
                $this->removeFromOnlinePlayersList($uuid);
            }
        }

        return $onlinePlayers;
    }

    /**
     * Cache invalidálás
     */
    public function invalidateAllPlayerData(): void
    {
        $pattern = self::PREFIX . '*';
        $keys = Redis::keys($pattern);

        if (!empty($keys)) {
            Redis::del($keys);
        }
    }

    /**
     * Online játékos hozzáadása (UUID alapon)
     */
    private function addToOnlinePlayersList(string $hytaleUuid): void
    {
        $key = $this->getOnlinePlayersKey();
        Redis::sadd($key, $hytaleUuid);
        Redis::expire($key, self::CACHE_TTL);
    }

    /**
     * Online játékos eltávolítása (UUID alapon)
     */
    private function removeFromOnlinePlayersList(string $hytaleUuid): void
    {
        $key = $this->getOnlinePlayersKey();
        Redis::srem($key, $hytaleUuid);
    }

    /**
     * UUID alapú kulcs
     */
    private function getUuidKey(string $uuid): string
    {
        return self::PREFIX . "uuid:{$uuid}";
    }

    /**
     * Online players kulcs
     */
    private function getOnlinePlayersKey(): string
    {
        return self::PREFIX . 'online_players';
    }
}
