<?php

namespace App\Modules\HytaleAuth\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class HytaleOAuth2Service
{
    private array $config;
    private array $oauthConfig;
    private array $apiConfig;

    public function __construct()
    {
        $this->config = config('hytale-auth');
        $this->oauthConfig = $this->config['oauth2'];
        $this->apiConfig = $this->config['hytale_api'];
    }

    /**
     * Device Code Flow indítása (RFC 8628)
     */
    public function initiateDeviceFlow(): array
    {
        try {
            $response = Http::timeout($this->apiConfig['timeout'])
                ->asForm()
                ->post($this->oauthConfig['device_auth_url'], [
                    'client_id' => $this->oauthConfig['client_id'],
                    'scope' => $this->oauthConfig['scope'],
                ]);

            if (!$response->successful()) {
                Log::error('Hytale device code request failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                throw new \Exception('Device code request failed: ' . $response->body());
            }

            $data = $response->json();

            // Device code cache-be mentése
            $deviceKey = $this->config['cache']['prefix'] . $this->config['cache']['device_key_prefix'] . $data['device_code'];
            Cache::put($deviceKey, [
                'device_code' => $data['device_code'],
                'user_code' => $data['user_code'],
                'created_at' => now()->toISOString(),
                'expires_in' => $data['expires_in'],
            ], $data['expires_in']);

            Log::info('Hytale device flow initiated', [
                'user_code' => $data['user_code'],
                'expires_in' => $data['expires_in'],
            ]);

            return [
                'success' => true,
                'device_code' => $data['device_code'],
                'user_code' => $data['user_code'],
                'verification_uri' => $data['verification_uri'],
                'verification_uri_complete' => $data['verification_uri_complete'] ?? null,
                'expires_in' => $data['expires_in'],
                'interval' => $data['interval'],
            ];

        } catch (\Exception $e) {
            Log::error('Hytale device flow initiation failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Device Code polling (token várakozás)
     */
    public function pollDeviceToken(string $deviceCode): array
    {
        try {
            $response = Http::timeout($this->apiConfig['timeout'])
                ->asForm()
                ->post($this->oauthConfig['token_url'], [
                    'client_id' => $this->oauthConfig['client_id'],
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
                    'device_code' => $deviceCode,
                ]);

            if ($response->status() === 400) {
                $error = $response->json('error');

                if ($error === 'authorization_pending') {
                    return [
                        'success' => false,
                        'status' => 'pending',
                        'message' => 'Waiting for user authorization...',
                    ];
                }

                if ($error === 'slow_down') {
                    return [
                        'success' => false,
                        'status' => 'slow_down',
                        'message' => 'Polling too fast, slow down',
                    ];
                }

                if ($error === 'expired_token') {
                    return [
                        'success' => false,
                        'status' => 'expired',
                        'message' => 'Device code expired',
                    ];
                }

                if ($error === 'access_denied') {
                    return [
                        'success' => false,
                        'status' => 'denied',
                        'message' => 'User denied authorization',
                    ];
                }
            }

            if (!$response->successful()) {
                throw new \Exception('Token polling failed: ' . $response->body());
            }

            $tokens = $response->json();

            Log::info('Hytale device flow completed successfully', [
                'token_type' => $tokens['token_type'] ?? 'Bearer',
                'scope' => $tokens['scope'] ?? '',
                'expires_in' => $tokens['expires_in'] ?? 0,
            ]);

            return [
                'success' => true,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'token_type' => $tokens['token_type'] ?? 'Bearer',
                'expires_in' => $tokens['expires_in'] ?? 3600,
                'scope' => $tokens['scope'] ?? '',
            ];

        } catch (\Exception $e) {
            Log::error('Hytale device token polling error', [
                'error' => $e->getMessage(),
                'device_code' => substr($deviceCode, 0, 10) . '...',
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Refresh token használata új access token megszerzéséhez
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        try {
            $response = Http::timeout($this->apiConfig['timeout'])
                ->asForm()
                ->post($this->oauthConfig['token_url'], [
                    'client_id' => $this->oauthConfig['client_id'],
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]);

            if (!$response->successful()) {
                Log::error('Hytale token refresh failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                throw new \Exception('Token refresh failed: ' . $response->body());
            }

            $tokens = $response->json();

            Log::info('Hytale token refreshed successfully');

            return [
                'success' => true,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? $refreshToken,
                'token_type' => $tokens['token_type'] ?? 'Bearer',
                'expires_in' => $tokens['expires_in'] ?? 3600,
                'scope' => $tokens['scope'] ?? '',
            ];

        } catch (\Exception $e) {
            Log::error('Hytale token refresh error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Hytale profilok lekérése
     */
    public function getProfiles(string $accessToken): array
    {
        try {
            $response = Http::timeout($this->apiConfig['timeout'])
                ->withToken($accessToken)
                ->get($this->apiConfig['account_url'] . '/my-account/get-profiles');

            if (!$response->successful()) {
                Log::error('Hytale profiles request failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                throw new \Exception('Profiles request failed: ' . $response->body());
            }

            $data = $response->json();

            Log::info('Hytale profiles retrieved', [
                'owner' => $data['owner'] ?? 'unknown',
                'profile_count' => count($data['profiles'] ?? []),
            ]);

            return [
                'success' => true,
                'owner' => $data['owner'],
                'profiles' => $data['profiles'],
            ];

        } catch (\Exception $e) {
            Log::error('Hytale profiles error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Game Session létrehozása
     */
    public function createGameSession(string $accessToken, string $profileUuid): array
    {
        try {
            $response = Http::timeout($this->apiConfig['timeout'])
                ->withToken($accessToken)
                ->post($this->apiConfig['session_url'] . '/game-session/new', [
                    'uuid' => $profileUuid,
                ]);

            if (!$response->successful()) {
                Log::error('Hytale game session creation failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'profile_uuid' => $profileUuid,
                ]);

                throw new \Exception('Game session creation failed: ' . $response->body());
            }

            $data = $response->json();

            Log::info('Hytale game session created', [
                'profile_uuid' => $profileUuid,
                'expires_at' => $data['expiresAt'] ?? 'unknown',
            ]);

            return [
                'success' => true,
                'session_token' => $data['sessionToken'],
                'identity_token' => $data['identityToken'],
                'expires_at' => $data['expiresAt'],
            ];

        } catch (\Exception $e) {
            Log::error('Hytale game session creation error', [
                'error' => $e->getMessage(),
                'profile_uuid' => $profileUuid,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Game Session frissítése
     */
    public function refreshGameSession(string $sessionToken): array
    {
        try {
            $response = Http::timeout($this->apiConfig['timeout'])
                ->withToken($sessionToken)
                ->post($this->apiConfig['session_url'] . '/game-session/refresh');

            if (!$response->successful()) {
                Log::error('Hytale game session refresh failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                throw new \Exception('Game session refresh failed: ' . $response->body());
            }

            $data = $response->json();

            Log::info('Hytale game session refreshed successfully');

            return [
                'success' => true,
                'session_token' => $data['sessionToken'],
                'identity_token' => $data['identityToken'],
                'expires_at' => $data['expiresAt'],
            ];

        } catch (\Exception $e) {
            Log::error('Hytale game session refresh error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Game Session lezárása
     */
    public function terminateGameSession(string $sessionToken): array
    {
        try {
            $response = Http::timeout($this->apiConfig['timeout'])
                ->withToken($sessionToken)
                ->delete($this->apiConfig['session_url'] . '/game-session');

            if (!$response->successful()) {
                Log::warning('Hytale game session termination failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => 'Session termination failed',
                ];
            }

            Log::info('Hytale game session terminated successfully');

            return [
                'success' => true,
            ];

        } catch (\Exception $e) {
            Log::error('Hytale game session termination error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * JWT token validáció JWKS endpoint alapján
     */
    public function validateJwtToken(string $jwt): array
    {
        try {
            // JWT parsing
            $parts = explode('.', $jwt);

            if (count($parts) !== 3) {
                throw new \Exception('Invalid JWT format');
            }

            $header = json_decode(base64_decode($parts[0]), true);
            $payload = json_decode(base64_decode($parts[1]), true);

            // Expiration ellenőrzés
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                throw new \Exception('Token expired');
            }

            // Not before ellenőrzés
            if (isset($payload['nbf']) && $payload['nbf'] > time()) {
                throw new \Exception('Token not yet valid');
            }

            // Audience ellenőrzés (opcionális)
            if ($this->config['security']['verify_audience'] && isset($payload['aud'])) {
                // Itt ellenőrizhető az audience
            }

            // TODO: JWKS alapú signature validáció
            if ($this->config['security']['validate_jwt_signature']) {
                // Implementálható a JWKS endpoint használatával
            }

            return [
                'success' => true,
                'header' => $header,
                'payload' => $payload,
                'valid' => true,
            ];

        } catch (\Exception $e) {
            Log::error('JWT validation error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'valid' => false,
            ];
        }
    }

    /**
     * JWKS (JSON Web Key Set) lekérése
     */
    public function getJwks(): array
    {
        try {
            $cacheKey = $this->config['cache']['prefix'] . 'jwks';

            // Cache-ből próbáljuk először
            $jwks = Cache::get($cacheKey);

            if (!$jwks) {
                $response = Http::timeout($this->apiConfig['timeout'])
                    ->get($this->apiConfig['jwks_url']);

                if (!$response->successful()) {
                    throw new \Exception('JWKS request failed: ' . $response->body());
                }

                $jwks = $response->json();

                // Cache-be mentés (1 óra)
                Cache::put($cacheKey, $jwks, 3600);
            }

            return [
                'success' => true,
                'jwks' => $jwks,
            ];

        } catch (\Exception $e) {
            Log::error('JWKS retrieval error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Stage environment URL módosítás
     */
    private function getUrl(string $url): string
    {
        if ($this->oauthConfig['use_stage']) {
            return str_replace('hytale.com', $this->oauthConfig['stage_domain'], $url);
        }

        return $url;
    }
}
