<?php

namespace App\Modules\HytaleAuth\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Auth0Service
{
    private array $config;

    public function __construct()
    {
        $this->config = config('hytale-auth.auth0');
    }

    /**
     * Authorization URL generálása
     */
    public function getAuthorizationUrl(
        string $state = null,
        string $redirectUri = null,
        string $scope = null
    ): array {
        $state = $state ?: Str::random(32);
        $redirectUri = $redirectUri ?: $this->config['redirect_uri'];
        $scope = $scope ?: $this->config['scope'];

        $params = [
            'client_id' => $this->config['client_id'],
            'response_type' => 'code',
            'scope' => $scope,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ];

        $url = $this->config['authorize_url'] . '?' . http_build_query($params);

        Log::info('Auth0 authorization URL generated', [
            'state' => $state,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
        ]);

        return [
            'url' => $url,
            'state' => $state,
        ];
    }

    /**
     * Authorization code cseréje tokenekre
     */
    public function exchangeCodeForTokens(
        string $code,
        string $redirectUri = null
    ): array {
        $redirectUri = $redirectUri ?: $this->config['redirect_uri'];

        try {
            $response = Http::timeout(30)
                ->asForm()
                ->post($this->config['token_url'], [
                    'client_id' => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $redirectUri,
                ]);

            if (!$response->successful()) {
                Log::error('Auth0 token exchange failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                throw new \Exception('Token exchange failed: ' . $response->body());
            }

            $tokens = $response->json();

            Log::info('Auth0 tokens obtained successfully', [
                'token_type' => $tokens['token_type'] ?? 'unknown',
                'scope' => $tokens['scope'] ?? 'unknown',
                'expires_in' => $tokens['expires_in'] ?? 0,
            ]);

            return [
                'success' => true,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'id_token' => $tokens['id_token'] ?? null,
                'token_type' => $tokens['token_type'] ?? 'Bearer',
                'expires_in' => $tokens['expires_in'] ?? 3600,
                'scope' => $tokens['scope'] ?? '',
            ];

        } catch (\Exception $e) {
            Log::error('Auth0 token exchange error', [
                'error' => $e->getMessage(),
                'code' => $code,
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
            $response = Http::timeout(30)
                ->asForm()
                ->post($this->config['token_url'], [
                    'client_id' => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]);

            if (!$response->successful()) {
                Log::error('Auth0 token refresh failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                throw new \Exception('Token refresh failed: ' . $response->body());
            }

            $tokens = $response->json();

            Log::info('Auth0 token refreshed successfully');

            return [
                'success' => true,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? $refreshToken,
                'id_token' => $tokens['id_token'] ?? null,
                'token_type' => $tokens['token_type'] ?? 'Bearer',
                'expires_in' => $tokens['expires_in'] ?? 3600,
                'scope' => $tokens['scope'] ?? '',
            ];

        } catch (\Exception $e) {
            Log::error('Auth0 token refresh error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * User info lekérése access token-nel
     */
    public function getUserInfo(string $accessToken): array
    {
        try {
            $response = Http::timeout(30)
                ->withToken($accessToken)
                ->get($this->config['userinfo_url']);

            if (!$response->successful()) {
                Log::error('Auth0 userinfo request failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                throw new \Exception('Userinfo request failed: ' . $response->body());
            }

            $userInfo = $response->json();

            Log::info('Auth0 user info retrieved', [
                'sub' => $userInfo['sub'] ?? 'unknown',
                'email' => $userInfo['email'] ?? 'unknown',
            ]);

            return [
                'success' => true,
                'user_info' => $userInfo,
            ];

        } catch (\Exception $e) {
            Log::error('Auth0 userinfo error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Token revocation (logout)
     */
    public function revokeToken(string $token, string $tokenType = 'refresh_token'): array
    {
        try {
            $response = Http::timeout(30)
                ->asForm()
                ->post($this->config['domain'] . '/oauth/revoke', [
                    'client_id' => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'token' => $token,
                    'token_type_hint' => $tokenType,
                ]);

            if (!$response->successful()) {
                Log::warning('Auth0 token revocation failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                // Nem kritikus hiba, folytatjuk
                return [
                    'success' => false,
                    'error' => 'Token revocation failed',
                ];
            }

            Log::info('Auth0 token revoked successfully');

            return [
                'success' => true,
            ];

        } catch (\Exception $e) {
            Log::error('Auth0 token revocation error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Logout URL generálása
     */
    public function getLogoutUrl(string $returnTo = null): string
    {
        $params = [
            'client_id' => $this->config['client_id'],
        ];

        if ($returnTo) {
            $params['returnTo'] = $returnTo;
        }

        return $this->config['domain'] . '/v2/logout?' . http_build_query($params);
    }

    /**
     * JWT token dekódolás és validáció
     */
    public function validateJwtToken(string $jwt): array
    {
        try {
            // Egyszerű JWT parsing (éles környezetben firebase/php-jwt használandó)
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

            // Issuer ellenőrzés
            if (isset($payload['iss']) && !str_contains($payload['iss'], 'auth0.com')) {
                throw new \Exception('Invalid token issuer');
            }

            return [
                'success' => true,
                'header' => $header,
                'payload' => $payload,
            ];

        } catch (\Exception $e) {
            Log::error('JWT validation error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * State token validáció
     */
    public function validateState(string $receivedState, string $expectedState): bool
    {
        return hash_equals($expectedState, $receivedState);
    }

    /**
     * PKCE support (Code Challenge/Verifier)
     */
    public function generatePkceChallenge(): array
    {
        $codeVerifier = Str::random(128);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        return [
            'code_verifier' => $codeVerifier,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];
    }

    /**
     * Hytale-specifikus scope-ok
     */
    public function getHytaleScopes(): array
    {
        return [
            'hytale:player' => 'Player információk olvasása',
            'hytale:server' => 'Szerver adatok elérése',
            'hytale:profile' => 'Profil adatok kezelése',
            'hytale:friends' => 'Barátlista kezelése',
            'hytale:achievements' => 'Eredmények megtekintése',
        ];
    }
}
