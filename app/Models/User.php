<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roleSlug(): string
    {
        return match ($this->role) {
            'admin' => 'super_admin',
            'captain', 'chairman', 'chairperson' => 'barangay_chairperson',
            'secretary', 'records_manager' => 'records_administrator',
            'staff', 'frontline_user' => 'staff_user',
            'committee_access' => 'committee_access_user',
            'youth_admin' => 'youth_administrator',
            'system_admin' => 'technical_administrator',
            'auditor' => 'external_auditor',
            default => $this->role,
        };
    }

    public function hasRole(string $role): bool
    {
        return $this->roleSlug() === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->roleSlug(), $roles, true);
    }

    public function hasPermission(string $permission): bool
    {
        $matrix = config('permissions.matrix', []);
        $role = $this->roleSlug();
        $overrides = RolePermission::permissionsForRole($role);
        $rolePermissions = is_array($overrides)
            ? $overrides
            : ($matrix[$role] ?? []);

        return in_array($permission, $rolePermissions, true);
    }

    public function isAdminPanelRole(): bool
    {
        return $this->hasAnyRole([
            'super_admin',
            'barangay_chairperson',
            'records_administrator',
            'finance_officer',
            'technical_administrator',
            'committee_access_user',
            'youth_administrator',
            'external_auditor',
        ]);
    }

    public function isStaffPanelRole(): bool
    {
        return $this->hasAnyRole([
            'staff_user',
            'encoder',
            'data_manager',
        ]);
    }
}
