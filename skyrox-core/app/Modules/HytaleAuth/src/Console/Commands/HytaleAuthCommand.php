<?php

namespace App\Modules\HytaleAuth\Console\Commands;

use App\Modules\HytaleAuth\Models\HytaleUser;
use App\Modules\HytaleAuth\Models\HytaleSession;
use App\Modules\HytaleAuth\Models\HytaleToken;
use App\Modules\HytaleAuth\Services\TokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class HytaleAuthCommand extends Command
{
    protected $signature = 'hytale:auth {action : Action to perform} 
                           {--user-id= : User ID for user-specific actions}
                           {--hytale-uuid= : Hytale UUID for user lookup}
                           {--days=30 : Days for cleanup operations}
                           {--force : Force cleanup without confirmation}';

    protected $description = 'Hytale authentication management commands';

    public function __construct(
        private TokenService $tokenService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'cleanup:tokens' => $this->cleanupExpiredTokens(),
            'cleanup:sessions' => $this->cleanupExpiredSessions(),
            'cleanup:all' => $this->cleanupAll(),
            'stats' => $this->showStats(),
            'user:info' => $this->showUserInfo(),
            'user:revoke-tokens' => $this->revokeUserTokens(),
            'user:terminate-sessions' => $this->terminateUserSessions(),
            'cache:clear' => $this->clearCache(),
            'health' => $this->healthCheck(),
            default => $this->showHelp(),
        };
    }

    /**
     * Lejárt tokenek tisztítása
     */
    private function cleanupExpiredTokens(): int
    {
        $this->info('🧹 Cleaning up expired tokens...');

        $result = $this->tokenService->cleanupExpiredTokens();

        if ($result['success']) {
            $this->info("✅ Cleaned up {$result['cleaned_count']} expired tokens.");
            return self::SUCCESS;
        } else {
            $this->error("❌ Token cleanup failed: {$result['error']}");
            return self::FAILURE;
        }
    }

    /**
     * Lejárt session-ök tisztítása
     */
    private function cleanupExpiredSessions(): int
    {
        $this->info('🧹 Cleaning up expired sessions...');

        $cleanedCount = HytaleSession::cleanupExpiredSessions();
        
        $this->info("✅ Cleaned up {$cleanedCount} expired sessions.");
        return self::SUCCESS;
    }

    /**
     * Teljes cleanup
     */
    private function cleanupAll(): int
    {
        if (!$this->option('force') && !$this->confirm('This will clean up all expired tokens and sessions. Continue?')) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        $this->info('🧹 Performing full cleanup...');

        // Tokenek
        $tokenResult = $this->tokenService->cleanupExpiredTokens();
        $tokenCount = $tokenResult['success'] ? $tokenResult['cleaned_count'] : 0;

        // Session-ök
        $sessionCount = HytaleSession::cleanupExpiredSessions();

        $this->info("✅ Cleanup complete:");
        $this->line("   - Tokens cleaned: {$tokenCount}");
        $this->line("   - Sessions cleaned: {$sessionCount}");

        return self::SUCCESS;
    }

    /**
     * Statisztikák megjelenítése
     */
    private function showStats(): int
    {
        $this->info('📊 Hytale Authentication Statistics');
        $this->newLine();

        // User statisztikák
        $totalUsers = HytaleUser::count();
        $activeUsers = HytaleUser::where('is_active', true)->count();
        $verifiedUsers = HytaleUser::where('is_verified', true)->count();
        $onlineUsers = HytaleUser::whereHas('sessions', function ($query) {
            $query->where('is_active', true)->where('expires_at', '>', now());
        })->count();

        $this->table(['Metric', 'Count'], [
            ['Total Users', $totalUsers],
            ['Active Users', $activeUsers],
            ['Verified Users', $verifiedUsers],
            ['Online Users', $onlineUsers],
        ]);

        // Token statisztikák
        $this->newLine();
        $this->info('🎫 Token Statistics:');

        $tokenStats = HytaleToken::selectRaw('type, COUNT(*) as count, SUM(CASE WHEN is_revoked = 0 THEN 1 ELSE 0 END) as active_count')
                                ->groupBy('type')
                                ->get();

        $tokenData = [];
        foreach ($tokenStats as $stat) {
            $tokenData[] = [$stat->type, $stat->count, $stat->active_count];
        }

        $this->table(['Type', 'Total', 'Active'], $tokenData);

        // Session statisztikák
        $this->newLine();
        $this->info('📱 Session Statistics:');

        $activeSessions = HytaleSession::where('is_active', true)->where('expires_at', '>', now())->count();
        $totalSessions = HytaleSession::count();

        $this->table(['Metric', 'Count'], [
            ['Total Sessions', $totalSessions],
            ['Active Sessions', $activeSessions],
        ]);

        return self::SUCCESS;
    }

    /**
     * User információk megjelenítése
     */
    private function showUserInfo(): int
    {
        $user = $this->findUser();
        
        if (!$user) {
            return self::FAILURE;
        }

        $this->info("👤 User Information for: {$user->username}");
        $this->newLine();

        $this->table(['Field', 'Value'], [
            ['ID', $user->id],
            ['Hytale UUID', $user->hytale_uuid ?? 'N/A'],
            ['Username', $user->username ?? 'N/A'],
            ['Email', $user->email ?? 'N/A'],
            ['Display Name', $user->display_name ?? 'N/A'],
            ['Is Active', $user->is_active ? 'Yes' : 'No'],
            ['Is Verified', $user->is_verified ? 'Yes' : 'No'],
            ['Login Count', $user->login_count],
            ['Last Login', $user->last_login_at?->format('Y-m-d H:i:s') ?? 'Never'],
            ['Is Online', $user->isOnline() ? 'Yes' : 'No'],
            ['Profile Completeness', $user->profileCompleteness() . '%'],
        ]);

        // Active sessions
        $activeSessions = $user->sessions()->where('is_active', true)->get();
        if ($activeSessions->count() > 0) {
            $this->newLine();
            $this->info('📱 Active Sessions:');
            
            $sessionData = $activeSessions->map(function ($session) {
                return [
                    substr($session->session_id, 0, 12) . '...',
                    $session->ip_address,
                    $session->device_type,
                    $session->last_activity_at->format('Y-m-d H:i:s'),
                ];
            })->toArray();

            $this->table(['Session ID', 'IP', 'Device', 'Last Activity'], $sessionData);
        }

        // Active tokens
        $activeTokens = $user->tokens()->where('is_revoked', false)->where('expires_at', '>', now())->get();
        if ($activeTokens->count() > 0) {
            $this->newLine();
            $this->info('🎫 Active Tokens:');
            
            $tokenData = $activeTokens->map(function ($token) {
                return [
                    substr($token->token_id, 0, 12) . '...',
                    $token->type,
                    $token->expires_at->format('Y-m-d H:i:s'),
                    $token->usage_count,
                ];
            })->toArray();

            $this->table(['Token ID', 'Type', 'Expires At', 'Usage'], $tokenData);
        }

        return self::SUCCESS;
    }

    /**
     * User tokenjeinek visszavonása
     */
    private function revokeUserTokens(): int
    {
        $user = $this->findUser();
        
        if (!$user) {
            return self::FAILURE;
        }

        if (!$this->option('force') && !$this->confirm("Revoke all tokens for user: {$user->username}?")) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        $result = $this->tokenService->revokeAllUserTokens($user, 'admin_command');

        if ($result['success']) {
            $this->info("✅ Revoked {$result['revoked_count']} tokens for user: {$user->username}");
            return self::SUCCESS;
        } else {
            $this->error("❌ Token revocation failed: {$result['error']}");
            return self::FAILURE;
        }
    }

    /**
     * User session-jeinek lezárása
     */
    private function terminateUserSessions(): int
    {
        $user = $this->findUser();
        
        if (!$user) {
            return self::FAILURE;
        }

        if (!$this->option('force') && !$this->confirm("Terminate all sessions for user: {$user->username}?")) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        $user->terminateAllSessions();
        $this->info("✅ Terminated all sessions for user: {$user->username}");

        return self::SUCCESS;
    }

    /**
     * Cache tisztítása
     */
    private function clearCache(): int
    {
        $this->info('🧹 Clearing Hytale auth cache...');

        $prefix = config('hytale-auth.cache.prefix', 'hytale:auth:');
        
        try {
            // Redis cache pattern alapú törlés
            if (Cache::getStore()->getRedis()) {
                $keys = Cache::getStore()->getRedis()->keys($prefix . '*');
                if ($keys) {
                    Cache::getStore()->getRedis()->del($keys);
                    $this->info("✅ Cleared " . count($keys) . " cache keys.");
                } else {
                    $this->info("✅ No cache keys found to clear.");
                }
            } else {
                $this->warn("⚠️  Redis not available, cache may not be fully cleared.");
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Cache clear failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Health check
     */
    private function healthCheck(): int
    {
        $this->info('🏥 Hytale Authentication Health Check');
        $this->newLine();

        $issues = [];

        // Database kapcsolat
        try {
            \DB::connection()->getPdo();
            $this->info('✅ Database connection: OK');
        } catch (\Exception $e) {
            $this->error('❌ Database connection: FAILED');
            $issues[] = 'Database connection failed: ' . $e->getMessage();
        }

        // Cache kapcsolat
        try {
            Cache::put('health_check', 'test', 10);
            $value = Cache::get('health_check');
            Cache::forget('health_check');
            
            if ($value === 'test') {
                $this->info('✅ Cache connection: OK');
            } else {
                $this->warn('⚠️  Cache connection: DEGRADED');
                $issues[] = 'Cache not working properly';
            }
        } catch (\Exception $e) {
            $this->error('❌ Cache connection: FAILED');
            $issues[] = 'Cache connection failed: ' . $e->getMessage();
        }

        // Konfiguráció ellenőrzés
        $requiredConfigs = [
            'hytale-auth.auth0.client_id',
            'hytale-auth.auth0.client_secret',
            'hytale-auth.auth0.domain',
        ];

        $configOk = true;
        foreach ($requiredConfigs as $config) {
            if (!config($config)) {
                $configOk = false;
                $issues[] = "Missing configuration: {$config}";
            }
        }

        if ($configOk) {
            $this->info('✅ Configuration: OK');
        } else {
            $this->error('❌ Configuration: MISSING VALUES');
        }

        // Összegzés
        $this->newLine();
        if (empty($issues)) {
            $this->info('🎉 All systems operational!');
            return self::SUCCESS;
        } else {
            $this->error('🚨 Issues found:');
            foreach ($issues as $issue) {
                $this->line("   - {$issue}");
            }
            return self::FAILURE;
        }
    }

    /**
     * User keresése
     */
    private function findUser(): ?HytaleUser
    {
        if ($userId = $this->option('user-id')) {
            $user = HytaleUser::find($userId);
            if (!$user) {
                $this->error("User not found with ID: {$userId}");
                return null;
            }
            return $user;
        }

        if ($hytaleUuid = $this->option('hytale-uuid')) {
            $user = HytaleUser::findByHytaleUuid($hytaleUuid);
            if (!$user) {
                $this->error("User not found with Hytale UUID: {$hytaleUuid}");
                return null;
            }
            return $user;
        }

        $this->error('Please provide either --user-id or --hytale-uuid option.');
        return null;
    }

    /**
     * Segítség megjelenítése
     */
    private function showHelp(): int
    {
        $this->info('🎮 Hytale Authentication Management');
        $this->newLine();
        
        $this->info('Available actions:');
        $this->table(['Action', 'Description'], [
            ['cleanup:tokens', 'Clean up expired tokens'],
            ['cleanup:sessions', 'Clean up expired sessions'],
            ['cleanup:all', 'Clean up all expired data'],
            ['stats', 'Show authentication statistics'],
            ['user:info', 'Show user information (requires --user-id or --hytale-uuid)'],
            ['user:revoke-tokens', 'Revoke all tokens for a user'],
            ['user:terminate-sessions', 'Terminate all sessions for a user'],
            ['cache:clear', 'Clear authentication cache'],
            ['health', 'Perform health check'],
        ]);

        $this->newLine();
        $this->info('Examples:');
        $this->line('  php artisan hytale:auth stats');
        $this->line('  php artisan hytale:auth cleanup:all --force');
        $this->line('  php artisan hytale:auth user:info --user-id=123');
        $this->line('  php artisan hytale:auth user:info --hytale-uuid=abc-123-def');

        return self::SUCCESS;
    }
}
