<?php

namespace App\Modules\HytaleAuth\Services;

use App\Modules\HytaleAuth\Models\HytaleUser;
use App\Modules\HytaleAuth\Models\HytaleToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TokenService
{
    private array $config;

    public function __construct()
    {
        $this->config = config('hytale-auth');
    }

    /**
     * Access token létrehozása
     */
    public function createAccessToken(
        HytaleUser $user,
        string $scope = '',
        int $expirationMinutes = null
    ): array {
        try {
            $expirationMinutes = $expirationMinutes ?: $this->config['tokens']['access_token_ttl'] / 60;
            
            // Token generálás
            $token = $this->generateSecureToken('access');
            
            // Adatbázisba mentés
            $tokenModel = HytaleToken::createAccessToken(
                $user,
                $token,
                $scope,
                $expirationMinutes,
                [
                    'created_by' => 'hytale_auth',
                    'user_agent' => request()->userAgent(),
                    'ip_address' => request()->ip(),
                ]
            );

            // Cache-be mentés
            if ($this->config['cache']['enabled']) {
                $this->cacheToken($tokenModel, $token);
            }

            Log::info('Access token created', [
                'user_id' => $user->id,
                'token_id' => $tokenModel->token_id,
                'scope' => $scope,
                'expires_in' => $expirationMinutes * 60,
            ]);

            return [
                'success' => true,
                'token' => $token,
                'token_id' => $tokenModel->token_id,
                'expires_in' => $expirationMinutes * 60,
                'expires_at' => $tokenModel->expires_at,
                'scope' => $scope,
            ];

        } catch (\Exception $e) {
            Log::error('Access token creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Refresh token létrehozása
     */
    public function createRefreshToken(
        HytaleUser $user,
        int $expirationDays = null
    ): array {
        try {
            $expirationDays = $expirationDays ?: ($this->config['tokens']['refresh_token_ttl'] / 86400);
            
            // Token generálás
            $token = $this->generateSecureToken('refresh');
            
            // Adatbázisba mentés
            $tokenModel = HytaleToken::createRefreshToken(
                $user,
                $token,
                $expirationDays,
                [
                    'created_by' => 'hytale_auth',
                    'user_agent' => request()->userAgent(),
                    'ip_address' => request()->ip(),
                ]
            );

            // Cache-be mentés
            if ($this->config['cache']['enabled']) {
                $this->cacheToken($tokenModel, $token);
            }

            Log::info('Refresh token created', [
                'user_id' => $user->id,
                'token_id' => $tokenModel->token_id,
                'expires_in' => $expirationDays * 24 * 60 * 60,
            ]);

            return [
                'success' => true,
                'token' => $token,
                'token_id' => $tokenModel->token_id,
                'expires_in' => $expirationDays * 24 * 60 * 60,
                'expires_at' => $tokenModel->expires_at,
            ];

        } catch (\Exception $e) {
            Log::error('Refresh token creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Token validáció
     */
    public function validateToken(string $token, string $type = 'access_token'): array
    {
        try {
            $tokenHash = hash('sha256', $token);
            
            // Cache-ből próbáljuk először
            if ($this->config['cache']['enabled']) {
                $cachedToken = $this->getCachedToken($tokenHash);
                if ($cachedToken && $cachedToken['type'] === $type) {
                    return [
                        'success' => true,
                        'token_data' => $cachedToken,
                        'source' => 'cache',
                    ];
                }
            }

            // Adatbázisból
            $tokenModel = HytaleToken::findByTokenHash($tokenHash);
            
            if (!$tokenModel) {
                return [
                    'success' => false,
                    'error' => 'Token not found',
                ];
            }

            if ($tokenModel->type !== $type) {
                return [
                    'success' => false,
                    'error' => 'Invalid token type',
                ];
            }

            if (!$tokenModel->isValid()) {
                return [
                    'success' => false,
                    'error' => $tokenModel->is_revoked ? 'Token revoked' : 'Token expired',
                ];
            }

            // Használat rögzítése
            $tokenModel->recordUsage(request()->ip(), request()->userAgent());

            // Cache frissítése
            if ($this->config['cache']['enabled']) {
                $this->cacheToken($tokenModel, $token);
            }

            return [
                'success' => true,
                'token_data' => [
                    'id' => $tokenModel->id,
                    'token_id' => $tokenModel->token_id,
                    'user_id' => $tokenModel->hytale_user_id,
                    'type' => $tokenModel->type,
                    'scope' => $tokenModel->scope,
                    'expires_at' => $tokenModel->expires_at,
                    'last_used_at' => $tokenModel->last_used_at,
                ],
                'user' => $tokenModel->hytaleUser,
                'source' => 'database',
            ];

        } catch (\Exception $e) {
            Log::error('Token validation failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Access token refresh
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        try {
            // Refresh token validálása
            $validation = $this->validateToken($refreshToken, 'refresh_token');
            
            if (!$validation['success']) {
                return $validation;
            }

            $user = $validation['user'];
            $oldRefreshToken = HytaleToken::findByTokenHash(hash('sha256', $refreshToken));

            // Új access token létrehozása
            $accessTokenResult = $this->createAccessToken(
                $user,
                $oldRefreshToken->scope ?? ''
            );

            if (!$accessTokenResult['success']) {
                return $accessTokenResult;
            }

            // Opcionálisan új refresh token is
            $newRefreshTokenResult = null;
            if ($this->config['tokens']['rotate_refresh_tokens'] ?? false) {
                // Régi refresh token visszavonása
                $oldRefreshToken->revoke('rotated');
                
                // Új refresh token létrehozása
                $newRefreshTokenResult = $this->createRefreshToken($user);
            }

            Log::info('Access token refreshed', [
                'user_id' => $user->id,
                'old_token_id' => $oldRefreshToken->token_id,
                'new_access_token_id' => $accessTokenResult['token_id'],
            ]);

            $result = [
                'success' => true,
                'access_token' => $accessTokenResult['token'],
                'token_type' => 'Bearer',
                'expires_in' => $accessTokenResult['expires_in'],
                'scope' => $oldRefreshToken->scope ?? '',
            ];

            if ($newRefreshTokenResult && $newRefreshTokenResult['success']) {
                $result['refresh_token'] = $newRefreshTokenResult['token'];
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Token refresh failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Token visszavonása
     */
    public function revokeToken(string $token, string $reason = 'manual'): array
    {
        try {
            $tokenHash = hash('sha256', $token);
            $tokenModel = HytaleToken::findByTokenHash($tokenHash);

            if (!$tokenModel) {
                return [
                    'success' => false,
                    'error' => 'Token not found',
                ];
            }

            $tokenModel->revoke($reason);

            // Cache-ből törlés
            if ($this->config['cache']['enabled']) {
                $this->removeCachedToken($tokenHash);
            }

            Log::info('Token revoked', [
                'token_id' => $tokenModel->token_id,
                'user_id' => $tokenModel->hytale_user_id,
                'reason' => $reason,
            ]);

            return [
                'success' => true,
                'message' => 'Token revoked successfully',
            ];

        } catch (\Exception $e) {
            Log::error('Token revocation failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * User összes tokenjének visszavonása
     */
    public function revokeAllUserTokens(HytaleUser $user, string $reason = 'logout'): array
    {
        try {
            $revokedCount = HytaleToken::revokeAllForUser($user, $reason);

            // Cache cleanup
            if ($this->config['cache']['enabled']) {
                $this->clearUserTokenCache($user);
            }

            Log::info('All user tokens revoked', [
                'user_id' => $user->id,
                'revoked_count' => $revokedCount,
                'reason' => $reason,
            ]);

            return [
                'success' => true,
                'revoked_count' => $revokedCount,
            ];

        } catch (\Exception $e) {
            Log::error('User token revocation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Bearer token kinyerése a request-ből
     */
    public function extractBearerToken(): ?string
    {
        $header = request()->header('Authorization');
        
        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return substr($header, 7);
    }

    /**
     * Biztonságos token generálás
     */
    private function generateSecureToken(string $type = 'access'): string
    {
        $prefix = [
            'access' => 'hya_',
            'refresh' => 'hyr_',
            'id' => 'hyi_',
        ];

        return ($prefix[$type] ?? 'hy_') . Str::random(64);
    }

    /**
     * Token cache-be mentése
     */
    private function cacheToken(HytaleToken $tokenModel, string $token): void
    {
        $key = $this->config['cache']['prefix'] . $this->config['cache']['token_key_prefix'] . hash('sha256', $token);
        
        $data = [
            'id' => $tokenModel->id,
            'token_id' => $tokenModel->token_id,
            'user_id' => $tokenModel->hytale_user_id,
            'type' => $tokenModel->type,
            'scope' => $tokenModel->scope,
            'expires_at' => $tokenModel->expires_at?->toISOString(),
            'is_revoked' => $tokenModel->is_revoked,
        ];

        Cache::put($key, $data, $this->config['cache']['ttl']);
    }

    /**
     * Token cache-ből lekérése
     */
    private function getCachedToken(string $tokenHash): ?array
    {
        $key = $this->config['cache']['prefix'] . $this->config['cache']['token_key_prefix'] . $tokenHash;
        return Cache::get($key);
    }

    /**
     * Token törlése cache-ből
     */
    private function removeCachedToken(string $tokenHash): void
    {
        $key = $this->config['cache']['prefix'] . $this->config['cache']['token_key_prefix'] . $tokenHash;
        Cache::forget($key);
    }

    /**
     * User token cache tisztítása
     */
    private function clearUserTokenCache(HytaleUser $user): void
    {
        $pattern = $this->config['cache']['prefix'] . 'user:' . $user->id . ':*';
        
        // Redis-ben
        if (Cache::getStore()->getRedis()) {
            $keys = Cache::getStore()->getRedis()->keys($pattern);
            if ($keys) {
                Cache::getStore()->getRedis()->del($keys);
            }
        }
    }

    /**
     * Lejárt tokenek cleanup
     */
    public function cleanupExpiredTokens(): array
    {
        try {
            $cleanedCount = HytaleToken::cleanupExpiredTokens();

            Log::info('Expired tokens cleaned up', [
                'cleaned_count' => $cleanedCount,
            ]);

            return [
                'success' => true,
                'cleaned_count' => $cleanedCount,
            ];

        } catch (\Exception $e) {
            Log::error('Token cleanup failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
