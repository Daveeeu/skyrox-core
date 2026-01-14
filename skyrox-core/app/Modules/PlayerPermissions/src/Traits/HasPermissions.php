<?php

namespace App\Modules\PlayerPermissions\Traits;


use App\Modules\PlayerPermissions\Models\PlayerSession;
use App\Modules\PlayerPermissions\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasPermissions
{
    /**
     * A felhasználóhoz tartozó role-ok
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
                    ->withPivot(['assigned_at', 'expires_at'])
                    ->withTimestamps();
    }

    /**
     * A felhasználóhoz tartozó aktív session-ök
     */
    public function playerSessions(): HasMany
    {
        return $this->hasMany(PlayerSession::class);
    }

    /**
     * Aktív session lekérése
     */
    public function activeSession()
    {
        return $this->playerSessions()->where('is_active', true)->first();
    }

    /**
     * Ellenőrzi, hogy a felhasználónak van-e adott jogosultsága
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->roles()
                    ->whereHas('permissions', function ($query) use ($permissionName) {
                        $query->where('name', $permissionName)
                              ->where('is_active', true);
                    })
                    ->where('is_active', true)
                    ->exists();
    }

    /**
     * Ellenőrzi, hogy a felhasználónak van-e adott role-ja
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()
                    ->where('name', $roleName)
                    ->where('is_active', true)
                    ->exists();
    }

    /**
     * Role hozzáadása a felhasználóhoz
     */
    public function assignRole(string|Role $role): void
    {
        $roleModel = is_string($role) ? Role::where('name', $role)->first() : $role;

        if ($roleModel && !$this->hasRole($roleModel->name)) {
            $this->roles()->attach($roleModel->id, [
                'assigned_at' => now(),
            ]);
        }
    }

    /**
     * Role eltávolítása a felhasználótól
     */
    public function removeRole(string|Role $role): void
    {
        $roleModel = is_string($role) ? Role::where('name', $role)->first() : $role;

        if ($roleModel) {
            $this->roles()->detach($roleModel->id);
        }
    }

    /**
     * Összes role lekérése
     */
    public function getRoleNames(): array
    {
        return $this->roles()->pluck('name')->toArray();
    }

    /**
     * Összes jogosultság lekérése
     */
    public function getAllPermissions(): array
    {
        return $this->roles()
                    ->with('permissions')
                    ->get()
                    ->flatMap(function ($role) {
                        return $role->permissions->pluck('name');
                    })
                    ->unique()
                    ->values()
                    ->toArray();
    }

    /**
     * Ellenőrzi, hogy a felhasználó jelenleg online van-e
     */
    public function isOnline(): bool
    {
        return $this->activeSession() !== null;
    }

    /**
     * Felhasználó bejelentkeztetése
     */
    public function login(string $sessionId, ?string $serverName = null, ?string $ipAddress = null): PlayerSession
    {
        // Korábbi aktív session-ök lezárása
        $this->playerSessions()->where('is_active', true)->each(function ($session) {
            $session->logout();
        });

        // Új session létrehozása
        return $this->playerSessions()->create([
            'session_id' => $sessionId,
            'server_name' => $serverName,
            'ip_address' => $ipAddress,
            'logged_in_at' => now(),
            'is_active' => true,
        ]);
    }

    /**
     * Felhasználó kijelentkeztetése
     */
    public function logout(): void
    {
        $activeSession = $this->activeSession();
        if ($activeSession) {
            $activeSession->logout();
        }
    }
}
