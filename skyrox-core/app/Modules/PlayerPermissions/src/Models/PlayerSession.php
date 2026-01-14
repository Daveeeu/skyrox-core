<?php

namespace App\Modules\PlayerPermissions\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'server_name',
        'ip_address',
        'logged_in_at',
        'logged_out_at',
        'is_active',
    ];

    protected $casts = [
        'logged_in_at' => 'datetime',
        'logged_out_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * A session-höz tartozó felhasználó
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope az aktív session-ökhöz
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Session lezárása
     */
    public function logout(): void
    {
        $this->update([
            'logged_out_at' => now(),
            'is_active' => false,
        ]);
    }
}
