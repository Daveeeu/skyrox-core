<?php

namespace App\Modules\HytaleAuth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class HytaleToken extends Model
{
    use HasFactory;

    const TYPE_ACCESS_TOKEN = 'access_token';
    const TYPE_REFRESH_TOKEN = 'refresh_token';
    const TYPE_ID_TOKEN = 'id_token';

    protected $fillable = [
        'hytale_user_id',
        'type',
        'token_id',
        'token_hash',
        'encrypted_token',
        'scope',
        'audience',
        'issuer',
        'issued_at',
        'expires_at',
        'not_before_at',
        'is_revoked',
        'revoked_at',
        'revocation_reason',
        'last_used_at',
        'usage_count',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'not_before_at' => 'datetime',
        'is_revoked' => 'boolean',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
        'usage_count' => 'integer',
        'metadata' => 'json',
    ];

    /**
     * Hytale user kapcsolat
     */
    public function hytaleUser(): BelongsTo
    {
        return $this->belongsTo(HytaleUser::class);
    }

    /**
     * Token érvényes-e
     */
    public function isValid(): bool
    {
        if ($this->is_revoked) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->not_before_at && $this->not_before_at->isFuture()) {
            return false;
        }

        return true;
    }

    /**
     * Token lejárt-e
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Token visszavonása
     */
    public function revoke(string $reason = 'manual'): void
    {
        $this->update([
            'is_revoked' => true,
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);
    }

    /**
     * Token használat rögzítése
     */
    public function recordUsage(string $ipAddress = null, string $userAgent = null): void
    {
        $this->update([
            'last_used_at' => now(),
            'usage_count' => $this->usage_count + 1,
            'ip_address' => $ipAddress ?: $this->ip_address,
            'user_agent' => $userAgent ?: $this->user_agent,
        ]);
    }

    /**
     * Token hátralévő ideje (másodpercben)
     */
    public function timeToExpiry(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        return now()->diffInSeconds($this->expires_at, false);
    }

    /**
     * Token scope ellenőrzése
     */
    public function hasScope(string $scope): bool
    {
        if (!$this->scope) {
            return false;
        }

        $scopes = explode(' ', $this->scope);
        return in_array($scope, $scopes);
    }

    /**
     * Scope-ok listája
     */
    public function getScopes(): array
    {
        if (!$this->scope) {
            return [];
        }

        return explode(' ', $this->scope);
    }

    /**
     * Token dekriptálása
     */
    public function decryptToken(): ?string
    {
        if (!$this->encrypted_token) {
            return null;
        }

        try {
            return decrypt($this->encrypted_token);
        } catch (\Exception $e) {
            \Log::error('Token decryption failed', [
                'token_id' => $this->token_id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Access token létrehozása
     */
    public static function createAccessToken(
        HytaleUser $user,
        string $token,
        string $scope = '',
        int $expirationMinutes = 60,
        array $metadata = []
    ): self {
        return static::create([
            'hytale_user_id' => $user->id,
            'type' => self::TYPE_ACCESS_TOKEN,
            'token_id' => 'at_' . Str::random(32),
            'token_hash' => hash('sha256', $token),
            'encrypted_token' => encrypt($token),
            'scope' => $scope,
            'issued_at' => now(),
            'expires_at' => now()->addMinutes($expirationMinutes),
            'usage_count' => 0,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Refresh token létrehozása
     */
    public static function createRefreshToken(
        HytaleUser $user,
        string $token,
        int $expirationDays = 30,
        array $metadata = []
    ): self {
        return static::create([
            'hytale_user_id' => $user->id,
            'type' => self::TYPE_REFRESH_TOKEN,
            'token_id' => 'rt_' . Str::random(32),
            'token_hash' => hash('sha256', $token),
            'encrypted_token' => encrypt($token),
            'issued_at' => now(),
            'expires_at' => now()->addDays($expirationDays),
            'usage_count' => 0,
            'metadata' => $metadata,
        ]);
    }

    /**
     * ID token létrehozása
     */
    public static function createIdToken(
        HytaleUser $user,
        string $token,
        string $audience = '',
        string $issuer = '',
        int $expirationMinutes = 60,
        array $metadata = []
    ): self {
        return static::create([
            'hytale_user_id' => $user->id,
            'type' => self::TYPE_ID_TOKEN,
            'token_id' => 'it_' . Str::random(32),
            'token_hash' => hash('sha256', $token),
            'encrypted_token' => encrypt($token),
            'audience' => $audience,
            'issuer' => $issuer,
            'issued_at' => now(),
            'expires_at' => now()->addMinutes($expirationMinutes),
            'usage_count' => 0,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Token keresése hash alapján
     */
    public static function findByTokenHash(string $tokenHash): ?self
    {
        return static::where('token_hash', $tokenHash)
                     ->where('is_revoked', false)
                     ->first();
    }

    /**
     * Lejárt tokenek cleanup
     */
    public static function cleanupExpiredTokens(): int
    {
        return static::where('expires_at', '<', now())
                     ->where('is_revoked', false)
                     ->update([
                         'is_revoked' => true,
                         'revoked_at' => now(),
                         'revocation_reason' => 'expired',
                     ]);
    }

    /**
     * User összes tokenjének visszavonása
     */
    public static function revokeAllForUser(HytaleUser $user, string $reason = 'user_logout'): int
    {
        return static::where('hytale_user_id', $user->id)
                     ->where('is_revoked', false)
                     ->update([
                         'is_revoked' => true,
                         'revoked_at' => now(),
                         'revocation_reason' => $reason,
                     ]);
    }

    /**
     * Token típus alapján visszavonás
     */
    public static function revokeByTypeForUser(HytaleUser $user, string $type, string $reason = 'manual'): int
    {
        return static::where('hytale_user_id', $user->id)
                     ->where('type', $type)
                     ->where('is_revoked', false)
                     ->update([
                         'is_revoked' => true,
                         'revoked_at' => now(),
                         'revocation_reason' => $reason,
                     ]);
    }
}
