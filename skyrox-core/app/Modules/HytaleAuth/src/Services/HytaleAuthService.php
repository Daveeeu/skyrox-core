<?php

namespace App\Modules\HytaleAuth\Services;

use App\Modules\HytaleAuth\Models\HytaleUser;
use App\Modules\HytaleAuth\Models\HytaleSession;
use App\Modules\HytaleAuth\Models\HytaleToken;
use App\Modules\HytaleAuth\Services\HytaleOAuth2Service;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class HytaleAuthService
{
    public function __construct(
        private HytaleOAuth2Service $hytaleOAuth2Service,
        private TokenService $tokenService
    ) {}

    /**
     * Device Code Flow indítása
     */
    public function initiateLogin(array $options = []): array
    {
        try {
            // Device Code Flow indítása
            $deviceResult = $this->hytaleOAuth2Service->initiateDeviceFlow();

            if (!$deviceResult['success']) {
                throw new \Exception('Device flow initiation failed: ' . $deviceResult['error']);
            }

            // Device code cache-be mentése state tracking céljából
            $stateKey = config('hytale-auth.cache.prefix') . config('hytale-auth.cache.device_key_prefix') . $deviceResult['user_code'];
            Cache::put($stateKey, [
                'device_code' => $deviceResult['device_code'],
                'user_code' => $deviceResult['user_code'],
                'created_at' => now()->toISOString(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ], $deviceResult['expires_in']);

            Log::info('Hytale Device Flow initiated', [
                'user_code' => $deviceResult['user_code'],
                'device_code' => substr($deviceResult['device_code'], 0, 10) . '...',
                'expires_in' => $deviceResult['expires_in'],
                'ip_address' => request()->ip(),
            ]);

            return [
                'success' => true,
                'device_code' => $deviceResult['device_code'],
                'user_code' => $deviceResult['user_code'],
                'verification_uri' => $deviceResult['verification_uri'],
                'verification_uri_complete' => $deviceResult['verification_uri_complete'] ?? null,
                'expires_in' => $deviceResult['expires_in'],
                'interval' => $deviceResult['interval'],
                'instructions' => [
                    'step1' => 'Visit: ' . $deviceResult['verification_uri'],
                    'step2' => 'Enter code: ' . $deviceResult['user_code'],
                    'or' => 'Direct link: ' . ($deviceResult['verification_uri_complete'] ?? $deviceResult['verification_uri']),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Hytale Device Flow initiation failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Device Code Flow polling (token várakozás)
     */
    public function pollDeviceToken(string $deviceCode, string $userCode): array
    {
        try {
            // Device code validáció
            if (!$this->validateDeviceCode($deviceCode, $userCode)) {
                throw new \Exception('Invalid or expired device code');
            }

            $result = $this->hytaleOAuth2Service->pollDeviceToken($deviceCode);

            if (!$result['success']) {
                return $result; // Pending, slow_down, denied, expired
            }

            // Sikeres autentikáció - user és session létrehozása
            $profiles = $this->hytaleOAuth2Service->getProfiles($result['access_token']);

            if (!$profiles['success'] || empty($profiles['profiles'])) {
                throw new \Exception('No Hytale profiles found for this account');
            }

            // Első profil kiválasztása (vagy user választhat később)
            $selectedProfile = $profiles['profiles'][0];

            // Game session létrehozása
            $gameSession = $this->hytaleOAuth2Service->createGameSession(
                $result['access_token'],
                $selectedProfile['uuid']
            );

            if (!$gameSession['success']) {
                throw new \Exception('Failed to create game session: ' . $gameSession['error']);
            }

            // User létrehozása vagy frissítése
            $user = $this->createOrUpdateUser($profiles, $selectedProfile);

            // Internal session létrehozása
            $session = $this->createUserSession($user, $result, $gameSession);

            // Saját tokenek generálása
            $accessTokenResult = $this->tokenService->createAccessToken($user, $result['scope']);
            $refreshTokenResult = $this->tokenService->createRefreshToken($user);

            if (!$accessTokenResult['success'] || !$refreshTokenResult['success']) {
                throw new \Exception('Internal token creation failed');
            }

            // Login esemény rögzítése
            $user->recordLogin(request()->ip());

            // Device code cleanup
            $this->cleanupDeviceCode($userCode);

            Log::info('Hytale user authenticated via Device Flow', [
                'user_id' => $user->id,
                'hytale_uuid' => $user->hytale_uuid,
                'profile_uuid' => $selectedProfile['uuid'],
                'session_id' => $session->session_id,
            ]);

            return [
                'success' => true,
                'user' => $this->formatUserResponse($user),
                'session' => [
                    'id' => $session->session_id,
                    'expires_at' => $session->expires_at,
                    'game_session_token' => $gameSession['session_token'],
                    'identity_token' => $gameSession['identity_token'],
                ],
                'tokens' => [
                    'access_token' => $accessTokenResult['token'],
                    'refresh_token' => $refreshTokenResult['token'],
                    'token_type' => 'Bearer',
                    'expires_in' => $accessTokenResult['expires_in'],
                    'scope' => $result['scope'],
                ],
                'hytale' => [
                    'profiles' => $profiles['profiles'],
                    'selected_profile' => $selectedProfile,
                    'owner_uuid' => $profiles['owner'],
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Hytale Device Flow polling failed', [
                'error' => $e->getMessage(),
                'device_code' => substr($deviceCode, 0, 10) . '...',
                'user_code' => $userCode,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * OAuth2 Callback kezelése (legacy support)
     */
    public function handleCallback(string $code, string $state): array
    {
        // A Hytale OAuth2 Device Code Flow-t használ, nem callback-et
        // Ez a metódus legacy support céljából marad

        Log::warning('Deprecated handleCallback method called - use pollDeviceToken instead', [
            'code' => substr($code, 0, 10) . '...',
            'state' => $state,
        ]);

        return [
            'success' => false,
            'error' => 'This method is deprecated. Use Device Code Flow with pollDeviceToken method instead.',
            'migration_info' => [
                'new_method' => 'pollDeviceToken',
                'flow' => 'Device Code Flow (RFC 8628)',
                'documentation' => 'https://support.hytale.com/hc/en-us/articles/45328341414043-Server-Provider-Authentication-Guide',
            ],
        ];
    }

    /**
     * Token refresh
     */
    public function refreshToken(string $refreshToken): array
    {
        try {
            $result = $this->tokenService->refreshAccessToken($refreshToken);

            if (!$result['success']) {
                throw new \Exception('Token refresh failed: ' . $result['error']);
            }

            Log::info('Hytale token refreshed successfully');

            return $result;

        } catch (\Exception $e) {
            Log::error('Hytale token refresh failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Logout
     */
    public function logout(string $accessToken = null): array
    {
        try {
            DB::beginTransaction();

            $user = null;

            if ($accessToken) {
                // Token alapú logout
                $validation = $this->tokenService->validateToken($accessToken, 'access_token');

                if ($validation['success']) {
                    $user = $validation['user'];

                    // User összes tokenjének visszavonása
                    $this->tokenService->revokeAllUserTokens($user, 'logout');

                    // User session-jeinek lezárása
                    $user->terminateAllSessions();
                }
            }

            // Auth0 logout (ha van refresh token)
            if ($user) {
                $refreshToken = $user->validRefreshToken();
                if ($refreshToken) {
                    $this->auth0Service->revokeToken($refreshToken->decryptToken(), 'refresh_token');
                }
            }

            DB::commit();

            Log::info('Hytale user logged out', [
                'user_id' => $user?->id,
            ]);

            return [
                'success' => true,
                'message' => 'Logout successful',
                'logout_url' => $this->auth0Service->getLogoutUrl(),
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Hytale logout failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Token validáció és user lekérése
     */
    public function validateTokenAndGetUser(string $accessToken): array
    {
        try {
            $validation = $this->tokenService->validateToken($accessToken, 'access_token');

            if (!$validation['success']) {
                return $validation;
            }

            $user = $validation['user'];

            // Session aktivitás frissítése
            $activeSession = $user->activeSession();
            if ($activeSession) {
                $activeSession->updateActivity(request()->ip());
            }

            return [
                'success' => true,
                'user' => $this->formatUserResponse($user),
                'token_data' => $validation['token_data'],
                'session' => $activeSession ? [
                    'id' => $activeSession->session_id,
                    'expires_at' => $activeSession->expires_at,
                    'last_activity' => $activeSession->last_activity_at,
                ] : null,
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
     * User profil frissítése
     */
    public function updateUserProfile(HytaleUser $user, array $profileData): array
    {
        try {
            $allowedFields = [
                'display_name',
                'avatar_url',
                'locale',
                'timezone',
                'preferences',
            ];

            $updateData = array_intersect_key($profileData, array_flip($allowedFields));

            if (isset($updateData['preferences'])) {
                $currentPreferences = $user->preferences ?? [];
                $updateData['preferences'] = array_merge($currentPreferences, $updateData['preferences']);
            }

            $user->update($updateData);

            Log::info('Hytale user profile updated', [
                'user_id' => $user->id,
                'updated_fields' => array_keys($updateData),
            ]);

            return [
                'success' => true,
                'user' => $this->formatUserResponse($user->fresh()),
            ];

        } catch (\Exception $e) {
            Log::error('Profile update failed', [
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
     * User session-jeinek lekérése
     */
    public function getUserSessions(HytaleUser $user): array
    {
        try {
            $sessions = $user->sessions()
                ->latest('created_at')
                ->take(10)
                ->get()
                ->map(function ($session) {
                    return [
                        'id' => $session->session_id,
                        'server_name' => $session->server_name,
                        'ip_address' => $session->ip_address,
                        'country' => $session->country,
                        'city' => $session->city,
                        'device_type' => $session->device_type,
                        'browser' => $session->browser,
                        'platform' => $session->platform,
                        'is_active' => $session->is_active,
                        'started_at' => $session->started_at,
                        'last_activity_at' => $session->last_activity_at,
                        'duration' => $session->duration(),
                    ];
                });

            return [
                'success' => true,
                'sessions' => $sessions,
                'active_count' => $user->sessions()->where('is_active', true)->count(),
            ];

        } catch (\Exception $e) {
            Log::error('Session retrieval failed', [
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
     * State validáció
     */
    private function validateState(string $state): bool
    {
        $stateKey = config('hytale-auth.cache.prefix') . config('hytale-auth.cache.state_key_prefix') . $state;
        $stateData = Cache::get($stateKey);

        if (!$stateData) {
            Log::warning('Invalid or expired state', ['state' => $state]);
            return false;
        }

        // IP cím ellenőrzése (opcionális biztonsági ellenőrzés)
        if (config('hytale-auth.security.verify_state_ip', true)) {
            if ($stateData['ip_address'] !== request()->ip()) {
                Log::warning('State IP mismatch', [
                    'state' => $state,
                    'expected_ip' => $stateData['ip_address'],
                    'actual_ip' => request()->ip(),
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * State cleanup
     */
    private function cleanupState(string $state): void
    {
        $stateKey = config('hytale-auth.cache.prefix') . config('hytale-auth.cache.state_key_prefix') . $state;
        Cache::forget($stateKey);
    }

    /**
     * Device code validáció
     */
    private function validateDeviceCode(string $deviceCode, string $userCode): bool
    {
        $stateKey = config('hytale-auth.cache.prefix') . config('hytale-auth.cache.device_key_prefix') . $userCode;
        $stateData = Cache::get($stateKey);

        if (!$stateData) {
            Log::warning('Invalid or expired device code', [
                'user_code' => $userCode,
                'device_code' => substr($deviceCode, 0, 10) . '...',
            ]);
            return false;
        }

        if ($stateData['device_code'] !== $deviceCode) {
            Log::warning('Device code mismatch', [
                'user_code' => $userCode,
                'expected_device_code' => substr($stateData['device_code'], 0, 10) . '...',
                'actual_device_code' => substr($deviceCode, 0, 10) . '...',
            ]);
            return false;
        }

        return true;
    }

    /**
     * Device code cleanup
     */
    private function cleanupDeviceCode(string $userCode): void
    {
        $stateKey = config('hytale-auth.cache.prefix') . config('hytale-auth.cache.device_key_prefix') . $userCode;
        Cache::forget($stateKey);
    }

    /**
     * User létrehozása vagy frissítése Hytale profilok alapján
     */
    private function createOrUpdateUser(array $profilesData, array $selectedProfile): HytaleUser
    {
        $ownerUuid = $profilesData['owner'];
        $profileUuid = $selectedProfile['uuid'];
        $username = $selectedProfile['username'];

        // Meglévő user keresése
        $user = HytaleUser::where('hytale_uuid', $profileUuid)->first();

        if (!$user) {
            // Owner UUID alapján is keresünk
            $user = HytaleUser::where('hytale_player_id', $ownerUuid)->first();
        }

        // Skin JSON helyes parsing-ja és újra kódolása
        $profileData = [
            'owner_uuid' => $ownerUuid,
            'profiles' => $profilesData['profiles'],
            'selected_profile' => $selectedProfile,
            'auth_method' => 'device_flow',
        ];

        $userData = [
            'hytale_uuid' => $profileUuid,
            'hytale_player_id' => $ownerUuid,
            'username' => $username,
            'display_name' => $username,
            'is_verified' => true,
            'profile_data' => json_encode($profileData), // Explicit JSON encode
            'is_active' => true,
            'email' => "hytale_{$ownerUuid}@hytale.local",
            'updated_at' => now(),
            'created_at' => $user ? $user->created_at : now(),
        ];

        if ($user) {
            $user->update($userData);
        } else {
            $user = HytaleUser::create($userData);
        }

        return $user;
    }


    /**
     * User session létrehozása Hytale adatokkal
     */
    private function createUserSession(HytaleUser $user, array $tokenData, array $gameSession): HytaleSession
    {
        $sessionId = 'hs_' . Str::random(32);

        // Meglévő session-ök kezelése
        $maxSessions = config('hytale-auth.session.max_sessions_per_account', 100);
        $activeSessions = HytaleSession::activeCountForUser($user->id);

        if ($activeSessions >= $maxSessions) {
            // Legrégebbi session lezárása
            $oldestSession = $user->sessions()
                ->where('is_active', true)
                ->oldest('last_activity_at')
                ->first();

            if ($oldestSession) {
                $oldestSession->terminate('max_sessions_exceeded');
            }
        }

        return HytaleSession::createForUser(
            $user,
            $sessionId,
            request()->get('server_name'),
            request()->ip(),
            request()->userAgent(),
            config('hytale-auth.session.session_timeout', 3600) / 60
        );
    }

    /**
     * User response formázása
     */
    private function formatUserResponse(HytaleUser $user): array
    {
        return [
            'id' => $user->id,
            'hytale_uuid' => $user->hytale_uuid,
            'hytale_player_id' => $user->hytale_player_id,
            'username' => $user->username,
            'display_name' => $user->display_name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'locale' => $user->locale,
            'is_verified' => $user->is_verified,
            'last_login_at' => $user->last_login_at,
            'login_count' => $user->login_count,
            'profile_completeness' => $user->profileCompleteness(),
            'is_online' => $user->isOnline(),
            'last_activity' => $user->lastActivity(),
        ];
    }
}
