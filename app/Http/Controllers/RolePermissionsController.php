<?php

namespace App\Http\Controllers;

use App\Models\RolePermission;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RolePermissionsController extends Controller
{
    public function index(Request $request): Response
    {
        $matrix = config('permissions.matrix', []);
        $roles = array_keys($matrix);
        $allPermissions = collect($matrix)
            ->flatten()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $stored = RolePermission::query()
            ->whereIn('role', $roles)
            ->get()
            ->keyBy('role');

        $rolePermissions = [];
        $defaultPermissions = [];

        foreach ($roles as $role) {
            $defaultPermissions[$role] = array_values($matrix[$role] ?? []);
            $rolePermissions[$role] = array_values($stored[$role]->permissions ?? $defaultPermissions[$role]);
        }

        return Inertia::render('Admin/RolePermissions', [
            'roles' => $roles,
            'allPermissions' => $allPermissions,
            'rolePermissions' => $rolePermissions,
            'defaultPermissions' => $defaultPermissions,
            'selectedRole' => in_array($request->query('role'), $roles, true) ? $request->query('role') : ($roles[0] ?? null),
        ]);
    }

    public function update(Request $request, string $role)
    {
        $matrix = config('permissions.matrix', []);
        $roles = array_keys($matrix);

        if (! in_array($role, $roles, true)) {
            abort(404);
        }

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string'],
        ]);

        $allPermissions = collect($matrix)->flatten()->unique()->values()->all();
        $submitted = array_values(array_unique($validated['permissions']));
        $invalid = array_values(array_diff($submitted, $allPermissions));

        if (! empty($invalid)) {
            return redirect()->back()->with('error', 'Invalid permissions provided.');
        }

        $record = RolePermission::query()->firstOrNew(['role' => $role]);
        $before = $record->exists ? (array) $record->permissions : ($matrix[$role] ?? []);

        $record->permissions = $submitted;
        $record->updated_by = $request->user()->id;
        $record->save();

        RolePermission::forgetCache($role);

        AuditLogger::log(
            $request,
            'role.permissions.update',
            RolePermission::class,
            $record->id,
            ['role' => $role, 'permissions' => $before],
            ['role' => $role, 'permissions' => $submitted]
        );

        return redirect()->back()->with('success', 'Role permissions updated.');
    }

    public function reset(Request $request, string $role)
    {
        $matrix = config('permissions.matrix', []);
        $roles = array_keys($matrix);

        if (! in_array($role, $roles, true)) {
            abort(404);
        }

        $defaultPermissions = array_values($matrix[$role] ?? []);
        $record = RolePermission::query()->firstOrNew(['role' => $role]);
        $before = $record->exists ? (array) $record->permissions : $defaultPermissions;

        $record->permissions = $defaultPermissions;
        $record->updated_by = $request->user()->id;
        $record->save();

        RolePermission::forgetCache($role);

        AuditLogger::log(
            $request,
            'role.permissions.reset',
            RolePermission::class,
            $record->id,
            ['role' => $role, 'permissions' => $before],
            ['role' => $role, 'permissions' => $defaultPermissions]
        );

        return redirect()->back()->with('success', 'Role permissions reset to defaults.');
    }

    public function resetAll(Request $request)
    {
        $matrix = config('permissions.matrix', []);

        foreach ($matrix as $role => $permissions) {
            $record = RolePermission::query()->firstOrNew(['role' => $role]);
            $record->permissions = array_values($permissions);
            $record->updated_by = $request->user()->id;
            $record->save();
        }

        RolePermission::forgetCache();

        AuditLogger::log(
            $request,
            'role.permissions.reset_all',
            RolePermission::class,
            0,
            null,
            ['roles' => array_keys($matrix)]
        );

        return redirect()->back()->with('success', 'All role permissions reset to defaults.');
    }
}
