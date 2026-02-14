<?php

namespace App\Http\Controllers;

use App\Models\RolePermission;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccessMatrixController extends Controller
{
    private const SIDEBAR_CHECKS = [
        ['module' => 'Dashboard', 'permissions' => ['dashboard.view']],
        ['module' => 'Resident Management', 'permissions' => ['residents.view']],
        ['module' => 'Certificate Management', 'permissions' => ['certificates.view']],
        ['module' => 'Blotter Records', 'permissions' => ['blotter.view']],
        ['module' => 'Financial Management', 'permissions' => ['financial_management.view']],
        ['module' => 'Payment Processing', 'permissions' => ['payment_processing.view']],
        ['module' => 'Disbursement Requests', 'permissions' => ['finance.disbursement.view']],
        ['module' => 'Official Receipts', 'permissions' => ['official_receipts.view']],
        ['module' => 'Collection Reports', 'permissions' => ['collection_reports.view']],
        ['module' => 'Transaction History', 'permissions' => ['transaction_history.view']],
        ['module' => 'Financial Summary', 'permissions' => ['financial_summary.view']],
        ['module' => 'Youth Management', 'permissions' => ['youth_management.view']],
        ['module' => 'Youth Residents', 'permissions' => ['youth_residents.view']],
        ['module' => 'Youth Programs', 'permissions' => ['youth_programs.view']],
        ['module' => 'Youth Reports', 'permissions' => ['youth_reports.view']],
        ['module' => 'Programs & Projects', 'permissions' => ['programs.view']],
        ['module' => 'Committee Reports', 'permissions' => ['committee_reports.view']],
        ['module' => 'Programs Monitoring', 'permissions' => ['programs_monitoring.view']],
        ['module' => 'Analytics (Trends)', 'permissions' => ['reports_analytics.view']],
        ['module' => 'Reports (Export)', 'permissions' => ['reports.view']],
        ['module' => 'Document Archive', 'permissions' => ['document_archive.view']],
        ['module' => 'Upload Documents', 'permissions' => ['documents.upload']],
        ['module' => 'Data Quality', 'permissions' => ['data.validate', 'data.archive']],
        ['module' => 'User Management', 'permissions' => ['users.manage']],
        ['module' => 'Role Permissions', 'permissions' => ['roles.manage']],
        ['module' => 'Audit Logs', 'permissions' => ['audit.view']],
        ['module' => 'System Logs', 'permissions' => ['system.logs.view']],
        ['module' => 'Backup & Restore', 'permissions' => ['system.backup']],
        ['module' => 'System Settings', 'permissions' => ['system.settings']],
    ];

    public function index(): Response
    {
        $defaults = config('permissions.matrix', []);
        $roles = array_keys($defaults);

        $stored = RolePermission::query()
            ->whereIn('role', $roles)
            ->get()
            ->keyBy('role');

        $allPermissions = collect($defaults)
            ->flatten()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $matrix = [];
        foreach ($roles as $role) {
            $defaultPermissions = array_values($defaults[$role] ?? []);
            $effectivePermissions = array_values($stored[$role]->permissions ?? $defaultPermissions);

            $matrix[] = [
                'role' => $role,
                'default_permissions' => $defaultPermissions,
                'effective_permissions' => $effectivePermissions,
                'added_permissions' => array_values(array_diff($effectivePermissions, $defaultPermissions)),
                'removed_permissions' => array_values(array_diff($defaultPermissions, $effectivePermissions)),
            ];
        }

        return Inertia::render('Admin/AccessMatrix', [
            'allPermissions' => $allPermissions,
            'matrix' => $matrix,
        ]);
    }

    public function exportCsv(): StreamedResponse
    {
        $defaults = config('permissions.matrix', []);
        $roles = array_keys($defaults);

        $stored = RolePermission::query()
            ->whereIn('role', $roles)
            ->get()
            ->keyBy('role');

        $filename = 'access-matrix-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($roles, $defaults, $stored): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Role', 'Module', 'Status', 'Required Permissions (Readable)', 'Required Permissions (Key)', 'Effective Permission Count']);

            foreach ($roles as $role) {
                $defaultPermissions = array_values($defaults[$role] ?? []);
                $effectivePermissions = array_values($stored[$role]->permissions ?? $defaultPermissions);
                $allowed = array_flip($effectivePermissions);

                foreach (self::SIDEBAR_CHECKS as $check) {
                    $isAllowed = collect($check['permissions'])->contains(fn ($permission) => isset($allowed[$permission]));

                    fputcsv($handle, [
                        $this->roleLabel($role),
                        $check['module'],
                        $isAllowed ? 'allowed' : 'blocked',
                        implode(' or ', array_map(fn ($permission) => $this->permissionLabel($permission), $check['permissions'])),
                        implode(' or ', $check['permissions']),
                        count($effectivePermissions),
                    ]);
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function roleLabel(string $role): string
    {
        return collect(explode('_', $role))
            ->filter()
            ->map(fn ($chunk) => ucfirst($chunk))
            ->implode(' ');
    }

    private function permissionLabel(string $permission): string
    {
        $parts = explode('.', $permission);
        if (count($parts) === 1) {
            return $this->words($parts[0]);
        }

        $actionMap = [
            'view' => 'View',
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
            'approve' => 'Approve',
            'reject' => 'Reject',
            'submit' => 'Submit',
            'release_if_approved' => 'Release (If Approved)',
            'upload' => 'Upload',
            'download' => 'Download',
            'export' => 'Export',
            'archive' => 'Archive',
            'restore' => 'Restore',
            'reset' => 'Reset',
            'manage' => 'Manage',
            'record' => 'Record',
            'toggle' => 'Toggle',
        ];

        $actionKey = $parts[count($parts) - 1];
        $resource = implode('_', array_slice($parts, 0, -1));
        $action = $actionMap[$actionKey] ?? $this->words($actionKey);

        return $this->words($resource).': '.$action;
    }

    private function words(string $value): string
    {
        return collect(explode('_', str_replace('-', '_', $value)))
            ->filter()
            ->map(fn ($chunk) => ucfirst($chunk))
            ->implode(' ');
    }
}
