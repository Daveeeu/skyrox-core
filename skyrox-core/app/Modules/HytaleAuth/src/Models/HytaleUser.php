<?php

namespace App\Modules\HytaleAuth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class HytaleUser extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hytale_uuid',
        'hytale_player_id',
        'username',
        'email',
        'display_name',
        'avatar_url',
        'auth0_user_id',
        'locale',
        'timezone',
        'last_login_at',
        'last_login_ip',
        'login_count',
        'is_active',
        'is_verified',
        'profile_data',
        'preferences',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'login_count' => 'integer',
        'profile_data' => 'json',
        'preferences' => 'json',
    ];

    /**
     * Hytale sessions kapcsolat
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(HytaleSession::class);
    }

    /**
     * Hytale tokens kapcsolat
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(HytaleToken::class);
    }

    /**
     * Aktív session lekérése
     */
    public function activeSession(): ?HytaleSession
    {
        return $this->sessions()
                    ->where('is_active', true)
                    ->where('expires_at', '>', now())
                    ->latest('created_at')
                    ->first();
    }

    /**
     * Érvényes access token lekérése
     */
    public function validAccessToken(): ?HytaleToken
    {
        return $this->tokens()
                    ->where('type', 'access_token')
                    ->where('expires_at', '>', now())
                    ->where('is_revoked', false)
                    ->latest('created_at')
                    ->first();
    }

    /**
     * Érvényes refresh token lekérése
     */
    public function validRefreshToken(): ?HytaleToken
    {
        return $this->tokens()
                    ->where('type', 'refresh_token')
                    ->where('expires_at', '>', now())
                    ->where('is_revoked', false)
                    ->latest('created_at')
                    ->first();
    }

    /**
     * Bejelentkezés rögzítése
     */
    public function recordLogin(string $ipAddress = null): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
            'login_count' => $this->login_count + 1,
        ]);
    }

    /**
     * Összes aktív session lezárása
     */
    public function terminateAllSessions(): void
    {
        $this->sessions()
             ->where('is_active', true)
             ->update([
                 'is_active' => false,
                 'terminated_at' => now(),
             ]);
    }

    /**
     * Összes token visszavonása
     */
    public function revokeAllTokens(): void
    {
        $this->tokens()
             ->where('is_revoked', false)
             ->update([
                 'is_revoked' => true,
                 'revoked_at' => now(),
             ]);
    }

    /**
     * Online-e a felhasználó
     */
    public function isOnline(): bool
    {
        return $this->activeSession() !== null;
    }

    /**
     * Utolsó aktivitás
     */
    public function lastActivity(): ?Carbon
    {
        $session = $this->activeSession();
        return $session?->last_activity_at;
    }

    /**
     * Profil teljesség százalékban
     */
    public function profileCompleteness(): int
    {
        $fields = ['username', 'email', 'display_name', 'avatar_url'];
        $completed = 0;
        
        foreach ($fields as $field) {
            if (!empty($this->{$field})) {
                $completed++;
            }
        }
        
        return (int) (($completed / count($fields)) * 100);
    }

    /**
     * Scope ellenőrzése
     */
    public function hasScope(string $scope): bool
    {
        $token = $this->validAccessToken();
        if (!$token) {
            return false;
        }
        
        $scopes = explode(' ', $token->scope ?? '');
        return in_array($scope, $scopes);
    }

    /**
     * Keresés Hytale UUID alapján
     */
    public static function findByHytaleUuid(string $hytaleUuid): ?self
    {
        return static::where('hytale_uuid', $hytaleUuid)
                     ->where('is_active', true)
                     ->first();
    }

    /**
     * Keresés Auth0 User ID alapján
     */
    public static function findByAuth0Id(string $auth0UserId): ?self
    {
        return static::where('auth0_user_id', $auth0UserId)
                     ->where('is_active', true)
                     ->first();
    }

    /**
     * User aktiválása
     */
    public function activate(): void
    {
        $this->update([
            'is_active' => true,
            'is_verified' => true,
        ]);
    }

    /**
     * User deaktiválása
     */
    public function deactivate(): void
    {
        $this->terminateAllSessions();
        $this->revokeAllTokens();
        
        $this->update([
            'is_active' => false,
        ]);
    }
}
