<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class RolePermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'role',
        'permissions',
        'updated_by',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    public static function permissionsForRole(string $role): ?array
    {
        if (! Schema::hasTable('role_permissions')) {
            return null;
        }

        try {
            $record = self::query()->where('role', $role)->first();
        } catch (QueryException) {
            return null;
        }

        return $record?->permissions;
    }

    public static function forgetCache(?string $role = null): void
    {
        // Permission lookups are read directly from DB.
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
