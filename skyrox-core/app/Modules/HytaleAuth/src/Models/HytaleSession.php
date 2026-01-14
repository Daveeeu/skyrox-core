<?php

namespace App\Modules\HytaleAuth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class HytaleSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'hytale_user_id',
        'session_id',
        'server_name',
        'ip_address',
        'user_agent',
        'country',
        'city',
        'device_type',
        'browser',
        'platform',
        'is_active',
        'started_at',
        'last_activity_at',
        'expires_at',
        'terminated_at',
        'termination_reason',
        'session_data',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'started_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'expires_at' => 'datetime',
        'terminated_at' => 'datetime',
        'session_data' => 'json',
    ];

    /**
     * Hytale user kapcsolat
     */
    public function hytaleUser(): BelongsTo
    {
        return $this->belongsTo(HytaleUser::class);
    }

    /**
     * Session frissítése (activity tracking)
     */
    public function updateActivity(string $ipAddress = null): void
    {
        $updateData = [
            'last_activity_at' => now(),
        ];

        if ($ipAddress && $ipAddress !== $this->ip_address) {
            $updateData['ip_address'] = $ipAddress;
            
            // IP változás logolása
            $sessionData = $this->session_data ?? [];
            $sessionData['ip_history'][] = [
                'ip' => $ipAddress,
                'changed_at' => now()->toISOString(),
            ];
            $updateData['session_data'] = $sessionData;
        }

        $this->update($updateData);
    }

    /**
     * Session lejárt-e
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Session aktív-e
     */
    public function isActive(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    /**
     * Session lezárása
     */
    public function terminate(string $reason = 'manual'): void
    {
        $this->update([
            'is_active' => false,
            'terminated_at' => now(),
            'termination_reason' => $reason,
        ]);
    }

    /**
     * Session kiterjesztése
     */
    public function extend(int $additionalMinutes = 60): void
    {
        $this->update([
            'expires_at' => now()->addMinutes($additionalMinutes),
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Session időtartama (másodpercben)
     */
    public function duration(): int
    {
        $end = $this->terminated_at ?? $this->last_activity_at ?? now();
        return $this->started_at->diffInSeconds($end);
    }

    /**
     * Inaktivitás ideje (másodpercben)
     */
    public function inactiveTime(): int
    {
        if (!$this->last_activity_at) {
            return 0;
        }
        
        return $this->last_activity_at->diffInSeconds(now());
    }

    /**
     * Device info frissítése User-Agent alapján
     */
    public function parseUserAgent(string $userAgent): void
    {
        // Egyszerű User-Agent parsing
        $deviceType = 'desktop';
        $browser = 'unknown';
        $platform = 'unknown';

        // Mobile detection
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            $deviceType = 'mobile';
        }

        // Browser detection
        if (preg_match('/Chrome/', $userAgent)) {
            $browser = 'chrome';
        } elseif (preg_match('/Firefox/', $userAgent)) {
            $browser = 'firefox';
        } elseif (preg_match('/Safari/', $userAgent)) {
            $browser = 'safari';
        }

        // Platform detection
        if (preg_match('/Windows/', $userAgent)) {
            $platform = 'windows';
        } elseif (preg_match('/Mac/', $userAgent)) {
            $platform = 'macos';
        } elseif (preg_match('/Linux/', $userAgent)) {
            $platform = 'linux';
        }

        $this->update([
            'user_agent' => $userAgent,
            'device_type' => $deviceType,
            'browser' => $browser,
            'platform' => $platform,
        ]);
    }

    /**
     * Geolocáció frissítése IP alapján
     */
    public function updateGeolocation(string $country = null, string $city = null): void
    {
        $this->update([
            'country' => $country,
            'city' => $city,
        ]);
    }

    /**
     * Aktív session-ök száma egy user-re
     */
    public static function activeCountForUser(int $hytaleUserId): int
    {
        return static::where('hytale_user_id', $hytaleUserId)
                     ->where('is_active', true)
                     ->where('expires_at', '>', now())
                     ->count();
    }

    /**
     * Lejárt session-ök cleanup
     */
    public static function cleanupExpiredSessions(): int
    {
        return static::where('expires_at', '<', now())
                     ->where('is_active', true)
                     ->update([
                         'is_active' => false,
                         'terminated_at' => now(),
                         'termination_reason' => 'expired',
                     ]);
    }

    /**
     * Session létrehozása
     */
    public static function createForUser(
        HytaleUser $user, 
        string $sessionId, 
        string $serverName = null,
        string $ipAddress = null,
        string $userAgent = null,
        int $expirationMinutes = 1440 // 24 óra
    ): self {
        $session = static::create([
            'hytale_user_id' => $user->id,
            'session_id' => $sessionId,
            'server_name' => $serverName,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'is_active' => true,
            'started_at' => now(),
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes($expirationMinutes),
        ]);

        // User-Agent parsing ha van
        if ($userAgent) {
            $session->parseUserAgent($userAgent);
        }

        return $session;
    }
}
