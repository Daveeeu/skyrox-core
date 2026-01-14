<?php

namespace Database\Seeders;

use App\Modules\PlayerPermissions\Models\Permission;
use App\Modules\PlayerPermissions\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PlayerPermissionSeeder extends Seeder
{
    /**
     * Alapértelmezett jogosultságok és szerepkörök létrehozása
     */
    public function run(): void
    {
        DB::transaction(function () {
            // Permissions létrehozása
            $permissions = $this->getDefaultPermissions();
            foreach ($permissions as $permissionData) {
                Permission::firstOrCreate(
                    ['name' => $permissionData['name']],
                    $permissionData
                );
            }

            // Roles létrehozása
            $roles = $this->getDefaultRoles();
            foreach ($roles as $roleData) {
                $role = Role::firstOrCreate(
                    ['name' => $roleData['name']],
                    Arr::except($roleData, 'permissions')  // array_except helyett Arr::except
                );

                // Permissions hozzárendelése a role-hoz
                if (isset($roleData['permissions'])) {
                    $permissionIds = Permission::whereIn('name', $roleData['permissions'])->pluck('id');
                    $role->permissions()->sync($permissionIds);
                }
            }
        });

        $this->command->info('Player permissions and roles seeded successfully!');
    }

    /**
     * Alapértelmezett permission-ök
     */
    private function getDefaultPermissions(): array
    {
        return [
            // Alapvető játék jogosultságok
            [
                'name' => 'game.join',
                'display_name' => 'Játékba belépés',
                'description' => 'Beléphet a játékba',
                'category' => 'basic',
            ],
            [
                'name' => 'game.chat',
                'display_name' => 'Chat használat',
                'description' => 'Használhatja a chat-et',
                'category' => 'basic',
            ],
            [
                'name' => 'game.build',
                'display_name' => 'Építés',
                'description' => 'Építhet és bonthat',
                'category' => 'building',
            ],
            [
                'name' => 'game.pvp',
                'display_name' => 'PvP',
                'description' => 'Részt vehet PvP-ben',
                'category' => 'combat',
            ],

            // Moderátor jogosultságok
            [
                'name' => 'moderation.kick',
                'display_name' => 'Játékos kirúgása',
                'description' => 'Kirúghat játékosokat',
                'category' => 'moderation',
            ],
            [
                'name' => 'moderation.ban',
                'display_name' => 'Játékos bannolása',
                'description' => 'Bannolhat játékosokat',
                'category' => 'moderation',
            ],
            [
                'name' => 'moderation.mute',
                'display_name' => 'Játékos némítása',
                'description' => 'Némíthat játékosokat',
                'category' => 'moderation',
            ],
            [
                'name' => 'moderation.warn',
                'display_name' => 'Figyelmeztetés',
                'description' => 'Figyelmeztethet játékosokat',
                'category' => 'moderation',
            ],

            // Admin jogosultságok
            [
                'name' => 'admin.teleport',
                'display_name' => 'Teleportálás',
                'description' => 'Teleportálhat bárhova',
                'category' => 'admin',
            ],
            [
                'name' => 'admin.fly',
                'display_name' => 'Repülés',
                'description' => 'Repülhet',
                'category' => 'admin',
            ],
            [
                'name' => 'admin.god',
                'display_name' => 'Halhatatlanság',
                'description' => 'Halhatatlan mód',
                'category' => 'admin',
            ],
            [
                'name' => 'admin.creative',
                'display_name' => 'Kreatív mód',
                'description' => 'Kreatív módot használhat',
                'category' => 'admin',
            ],
            [
                'name' => 'admin.console',
                'display_name' => 'Konzol parancsok',
                'description' => 'Konzol parancsokat futtathat',
                'category' => 'admin',
            ],

            // Speciális jogosultságok
            [
                'name' => 'special.vip',
                'display_name' => 'VIP juttatások',
                'description' => 'VIP juttatásokat használhat',
                'category' => 'special',
            ],
            [
                'name' => 'special.donor',
                'display_name' => 'Támogatói juttatások',
                'description' => 'Támogatói juttatásokat használhat',
                'category' => 'special',
            ],
        ];
    }

    /**
     * Alapértelmezett role-ok
     */
    private function getDefaultRoles(): array
    {
        return [
            [
                'name' => 'guest',
                'display_name' => 'Vendég',
                'description' => 'Alapértelmezett vendég jogosultság',
                'permissions' => ['game.join', 'game.chat'],
            ],
            [
                'name' => 'player',
                'display_name' => 'Játékos',
                'description' => 'Alap játékos jogosultságok',
                'permissions' => ['game.join', 'game.chat', 'game.build', 'game.pvp'],
            ],
            [
                'name' => 'vip',
                'display_name' => 'VIP',
                'description' => 'VIP játékos juttatásokkal',
                'permissions' => ['game.join', 'game.chat', 'game.build', 'game.pvp', 'special.vip'],
            ],
            [
                'name' => 'donor',
                'display_name' => 'Támogató',
                'description' => 'Támogató játékos speciális juttatásokkal',
                'permissions' => ['game.join', 'game.chat', 'game.build', 'game.pvp', 'special.vip', 'special.donor'],
            ],
            [
                'name' => 'helper',
                'display_name' => 'Segítő',
                'description' => 'Segítő moderátor alapvető jogosultságokkal',
                'permissions' => ['game.join', 'game.chat', 'game.build', 'game.pvp', 'moderation.warn'],
            ],
            [
                'name' => 'moderator',
                'display_name' => 'Moderátor',
                'description' => 'Moderátor teljes moderációs jogosultságokkal',
                'permissions' => [
                    'game.join', 'game.chat', 'game.build', 'game.pvp',
                    'moderation.kick', 'moderation.ban', 'moderation.mute', 'moderation.warn',
                    'admin.teleport', 'admin.fly'
                ],
            ],
            [
                'name' => 'admin',
                'display_name' => 'Adminisztrátor',
                'description' => 'Teljes adminisztrátori jogosultságok',
                'permissions' => [
                    'game.join', 'game.chat', 'game.build', 'game.pvp',
                    'moderation.kick', 'moderation.ban', 'moderation.mute', 'moderation.warn',
                    'admin.teleport', 'admin.fly', 'admin.god', 'admin.creative', 'admin.console',
                    'special.vip', 'special.donor'
                ],
            ],
            [
                'name' => 'owner',
                'display_name' => 'Tulajdonos',
                'description' => 'Szerver tulajdonos - minden jogosultság',
                'permissions' => [
                    'game.join', 'game.chat', 'game.build', 'game.pvp',
                    'moderation.kick', 'moderation.ban', 'moderation.mute', 'moderation.warn',
                    'admin.teleport', 'admin.fly', 'admin.god', 'admin.creative', 'admin.console',
                    'special.vip', 'special.donor'
                ],
            ],
        ];
    }
}
