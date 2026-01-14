<?php

namespace App\Modules\PlayerPermissions\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * A role-hoz tartozó felhasználók
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
                    ->withPivot(['assigned_at', 'expires_at'])
                    ->withTimestamps();
    }

    /**
     * A role-hoz tartozó jogosultságok
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
                    ->withTimestamps();
    }

    /**
     * Ellenőrzi, hogy a role-nak van-e adott permission-je
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions()->where('name', $permissionName)->exists();
    }

    /**
     * Scope az aktív role-okhoz
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
