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

    private static array $cache = [];

    public static function permissionsForRole(string $role): ?array
    {
        if (! Schema::hasTable('role_permissions')) {
            return null;
        }

        if (array_key_exists($role, self::$cache)) {
            return self::$cache[$role];
        }

        try {
            $record = self::query()->where('role', $role)->first();
        } catch (QueryException) {
            return null;
        }

        self::$cache[$role] = $record?->permissions;

        return self::$cache[$role];
    }

    public static function forgetCache(?string $role = null): void
    {
        if ($role === null) {
            self::$cache = [];
            return;
        }

        unset(self::$cache[$role]);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
