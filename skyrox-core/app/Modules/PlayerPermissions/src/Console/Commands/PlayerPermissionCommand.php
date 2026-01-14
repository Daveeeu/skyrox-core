<?php

namespace App\Modules\PlayerPermissions\Console\Commands;

use App\Modules\PlayerPermissions\Models\Permission;
use App\Modules\PlayerPermissions\Models\PlayerSession;
use App\Modules\PlayerPermissions\Models\Role;
use App\Modules\PlayerPermissions\Services\PlayerPermissionRedisService;
use Illuminate\Console\Command;
use App\Models\User;
use App\Modules\PlayerPermissions\Services\PlayerPermissionService;

class PlayerPermissionCommand extends Command
{
    protected $signature = 'player-permission {action}
                          {--user-id= : User ID}
                          {--role= : Role name}
                          {--permission= : Permission name}
                          {--all : Apply to all users}
                          {--force : Force operation without confirmation}';

    protected $description = 'Hytale Player Permission Management';

    public function __construct(
        private PlayerPermissionService $permissionService,
        private PlayerPermissionRedisService $redisService
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $action = $this->argument('action');

        match ($action) {
            'cache:clear' => $this->clearCache(),
            'cache:rebuild' => $this->rebuildCache(),
            'user:assign-role' => $this->assignRole(),
            'user:remove-role' => $this->removeRole(),
            'user:list-permissions' => $this->listUserPermissions(),
            'role:list' => $this->listRoles(),
            'permission:list' => $this->listPermissions(),
            'stats' => $this->showStats(),
            'cleanup' => $this->cleanup(),
            'test:permission' => $this->testPermission(),
            'online:list' => $this->listOnlinePlayers(),
            'online:kick-all' => $this->kickAllPlayers(),
            default => $this->showHelp(),
        };
    }

    private function clearCache(): void
    {
        $this->info('Player permission cache törlése...');

        if ($this->option('user-id')) {
            $this->redisService->removePlayerData((int) $this->option('user-id'));
            $this->info("Cache törölve a felhasználóra: {$this->option('user-id')}");
        } else {
            $this->redisService->invalidateAllPlayerData();
            $this->info('Összes player cache törölve.');
        }
    }

    private function rebuildCache(): void
    {
        $this->info('Player permission cache újraépítése...');

        $users = User::with(['roles.permissions'])->get();
        $bar = $this->output->createProgressBar($users->count());

        foreach ($users as $user) {
            $this->redisService->cachePlayerData($user);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Cache újraépítve {$users->count()} felhasználóra.");
    }

    private function assignRole(): void
    {
        $userId = $this->option('user-id');
        $roleName = $this->option('role');

        if (!$userId || !$roleName) {
            $this->error('--user-id és --role paraméterek kötelezőek!');
            return;
        }

        try {
            $user = User::findOrFail($userId);
            $role = Role::where('name', $roleName)->firstOrFail();

            $user->assignRole($role);
            $this->redisService->cachePlayerData($user);

            $this->info("Role '{$roleName}' sikeresen hozzárendelve a felhasználóhoz: {$user->name}");
        } catch (\Exception $e) {
            $this->error("Hiba: {$e->getMessage()}");
        }
    }

    private function removeRole(): void
    {
        $userId = $this->option('user-id');
        $roleName = $this->option('role');

        if (!$userId || !$roleName) {
            $this->error('--user-id és --role paraméterek kötelezőek!');
            return;
        }

        try {
            $user = User::findOrFail($userId);
            $role = Role::where('name', $roleName)->firstOrFail();

            $user->removeRole($role);
            $this->redisService->cachePlayerData($user);

            $this->info("Role '{$roleName}' sikeresen eltávolítva a felhasználótól: {$user->name}");
        } catch (\Exception $e) {
            $this->error("Hiba: {$e->getMessage()}");
        }
    }

    private function listUserPermissions(): void
    {
        $userId = $this->option('user-id');

        if (!$userId) {
            $this->error('--user-id paraméter kötelező!');
            return;
        }

        try {
            $user = User::findOrFail($userId);

            $this->info("Felhasználó: {$user->name} (ID: {$user->id})");
            $this->info("Role-ok: " . implode(', ', $user->getRoleNames()));
            $this->info("Jogosultságok:");

            foreach ($user->getAllPermissions() as $permission) {
                $this->line("  - {$permission}");
            }
        } catch (\Exception $e) {
            $this->error("Hiba: {$e->getMessage()}");
        }
    }

    private function listRoles(): void
    {
        $roles = Role::with(['permissions'])->get();

        $this->table(
            ['ID', 'Név', 'Megjelenő Név', 'Aktív', 'Jogosultságok'],
            $roles->map(function ($role) {
                return [
                    $role->id,
                    $role->name,
                    $role->display_name,
                    $role->is_active ? 'Igen' : 'Nem',
                    $role->permissions->count(),
                ];
            })
        );
    }

    private function listPermissions(): void
    {
        $permissions = Permission::all();

        $this->table(
            ['ID', 'Név', 'Megjelenő Név', 'Kategória', 'Aktív'],
            $permissions->map(function ($permission) {
                return [
                    $permission->id,
                    $permission->name,
                    $permission->display_name,
                    $permission->category,
                    $permission->is_active ? 'Igen' : 'Nem',
                ];
            })
        );
    }

    private function showStats(): void
    {
        $userCount = User::count();
        $roleCount = Role::count();
        $permissionCount = Permission::count();
        $onlineCount = User::whereHas('playerSessions', function ($query) {
            $query->where('is_active', true);
        })->count();

        $this->info('=== Player Permission Statisztikák ===');
        $this->line("Összes felhasználó: {$userCount}");
        $this->line("Online felhasználók: {$onlineCount}");
        $this->line("Szerepkörök száma: {$roleCount}");
        $this->line("Jogosultságok száma: {$permissionCount}");
    }

    private function cleanup(): void
    {
        if (!$this->option('force') && !$this->confirm('Biztos vagy benne, hogy törölni akarod a régi session-öket?')) {
            return;
        }

        $this->info('Régi session-ök törlése...');

        $deletedCount = PlayerSession::where('created_at', '<', now()->subDays(30))->delete();

        $this->info("{$deletedCount} régi session törölve.");
    }

    private function testPermission(): void
    {
        $userId = $this->option('user-id');
        $permission = $this->option('permission');

        if (!$userId || !$permission) {
            $this->error('--user-id és --permission paraméterek kötelezőek!');
            return;
        }

        $result = $this->permissionService->checkPermission((int) $userId, $permission);

        if ($result['success']) {
            $status = $result['has_permission'] ? 'VAN' : 'NINCS';
            $this->info("Jogosultság teszt eredmény: {$status} jogosultsága a '{$permission}' művelethez.");
        } else {
            $this->error("Teszt hiba: {$result['message']}");
        }
    }

    private function listOnlinePlayers(): void
    {
        $result = $this->permissionService->getOnlinePlayers();

        if ($result['success']) {
            $this->info("Online játékosok ({$result['count']}):");
            foreach ($result['players'] as $player) {
                $roles = implode(', ', $player['roles'] ?? []);
                $this->line("  - {$player['username']} (Role: {$roles})");
            }
        } else {
            $this->error('Nem sikerült lekérni az online játékosokat.');
        }
    }

    private function kickAllPlayers(): void
    {
        if (!$this->option('force') && !$this->confirm('Biztos vagy benne, hogy ki akarod léptetni az összes online játékost?')) {
            return;
        }

        $this->info('Összes online játékos kiléptetése...');

        $onlineUsers = User::whereHas('playerSessions', function ($query) {
            $query->where('is_active', true);
        })->get();

        foreach ($onlineUsers as $user) {
            $user->logout();
            $this->redisService->cachePlayerData($user);
        }

        $this->info("{$onlineUsers->count()} játékos kiléptetésre került.");
    }

    private function showHelp(): void
    {
        $this->info('Elérhető műveletek:');
        $this->line('  cache:clear        - Cache törlése');
        $this->line('  cache:rebuild      - Cache újraépítése');
        $this->line('  user:assign-role   - Role hozzárendelése felhasználóhoz');
        $this->line('  user:remove-role   - Role eltávolítása felhasználótól');
        $this->line('  user:list-permissions - Felhasználó jogosultságainak listázása');
        $this->line('  role:list          - Role-ok listázása');
        $this->line('  permission:list    - Jogosultságok listázása');
        $this->line('  stats              - Statisztikák megjelenítése');
        $this->line('  cleanup            - Régi adatok törlése');
        $this->line('  test:permission    - Jogosultság tesztelése');
        $this->line('  online:list        - Online játékosok listázása');
        $this->line('  online:kick-all    - Összes online játékos kiléptetése');
        $this->newLine();
        $this->line('Példa használat:');
        $this->line('  php artisan player-permission user:assign-role --user-id=1 --role=admin');
        $this->line('  php artisan player-permission test:permission --user-id=1 --permission=game.build');
    }
}
