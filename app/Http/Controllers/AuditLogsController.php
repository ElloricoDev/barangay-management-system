<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogsController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $query = $this->filteredQuery($filters)->latest();

        $auditLogs = $query
            ->paginate(15)
            ->withQueryString();

        $availableActions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return Inertia::render('Admin/AuditLogs', [
            'filters' => $filters,
            'auditLogs' => $auditLogs,
            'availableActions' => $availableActions,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $logs = $this->filteredQuery($filters)
            ->latest()
            ->get();

        $filename = 'audit-logs-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Date',
                'User Name',
                'User Email',
                'Action',
                'Module',
                'Record ID',
                'Before',
                'After',
                'IP Address',
            ]);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    (string) $log->created_at,
                    (string) ($log->user?->name ?? 'System'),
                    (string) ($log->user?->email ?? ''),
                    (string) $this->actionLabel((string) $log->action),
                    (string) class_basename($log->auditable_type),
                    (string) $log->auditable_id,
                    json_encode($this->formatForExport($this->maskSensitiveArray($log->before ?? [])), JSON_UNESCAPED_SLASHES),
                    json_encode($this->formatForExport($this->maskSensitiveArray($log->after ?? [])), JSON_UNESCAPED_SLASHES),
                    (string) ($log->ip_address ?? ''),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function filters(Request $request): array
    {
        return [
            'user' => trim((string) $request->query('user', '')),
            'action' => trim((string) $request->query('action', '')),
            'module' => trim((string) $request->query('module', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
        ];
    }

    private function filteredQuery(array $filters)
    {
        return AuditLog::query()
            ->with('user:id,name,email')
            ->when($filters['user'] !== '', function ($q) use ($filters) {
                $q->whereHas('user', function ($userQuery) use ($filters) {
                    $userQuery->where('name', 'like', "%{$filters['user']}%")
                        ->orWhere('email', 'like', "%{$filters['user']}%");
                });
            })
            ->when($filters['action'] !== '', function ($q) use ($filters) {
                $q->where('action', 'like', "%{$filters['action']}%");
            })
            ->when($filters['module'] !== '', function ($q) use ($filters) {
                $map = [
                    'certificate' => 'Certificate',
                    'blotter' => 'Blotter',
                    'user' => 'User',
                    'payment' => 'Payment',
                    'budget' => 'BudgetAllocation',
                    'disbursement' => 'DisbursementRequest',
                    'fund_adjustment' => 'FundAdjustment',
                    'document' => 'Document',
                    'role_permission' => 'RolePermission',
                    'system_setting' => 'SystemSetting',
                ];

                if (isset($map[$filters['module']])) {
                    $q->where('auditable_type', 'like', '%'.$map[$filters['module']]);
                }
            })
            ->when($filters['date_from'] !== '', function ($q) use ($filters) {
                $q->whereDate('created_at', '>=', $filters['date_from']);
            })
            ->when($filters['date_to'] !== '', function ($q) use ($filters) {
                $q->whereDate('created_at', '<=', $filters['date_to']);
            });
    }

    private function maskSensitiveArray(array $data): array
    {
        $masked = [];

        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            $isSensitive = str_contains($normalizedKey, 'password')
                || str_contains($normalizedKey, 'token')
                || str_contains($normalizedKey, 'secret')
                || str_contains($normalizedKey, 'remember')
                || str_contains($normalizedKey, 'otp')
                || str_contains($normalizedKey, 'pin');

            if ($isSensitive) {
                $masked[$key] = '[MASKED]';
            } elseif (is_array($value)) {
                $masked[$key] = $this->maskSensitiveArray($value);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }

    private function formatForExport(array $payload): array
    {
        $formatted = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $formatted[$key] = $this->formatForExport($value);
                continue;
            }

            if ($this->isPermissionKey((string) $key)) {
                $formatted[$key] = $this->formatPermissionValues($value);
                continue;
            }

            $formatted[$key] = $value;
        }

        return $formatted;
    }

    private function isPermissionKey(string $key): bool
    {
        $normalized = strtolower($key);
        return $normalized === 'permissions' || str_ends_with($normalized, '_permissions');
    }

    private function formatPermissionValues(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->permissionLabel((string) $item), $value);
        }

        if (is_string($value) && str_contains($value, ',')) {
            $parts = array_values(array_filter(array_map('trim', explode(',', $value))));
            return array_map(fn ($item) => $this->permissionLabel((string) $item), $parts);
        }

        if (is_string($value)) {
            return $this->permissionLabel($value);
        }

        return $value;
    }

    private function actionLabel(string $action): string
    {
        $parts = array_values(array_filter(explode('.', trim($action))));
        if (empty($parts)) {
            return $action;
        }

        $verbMap = [
            'create' => 'Created',
            'update' => 'Updated',
            'delete' => 'Deleted',
            'destroy' => 'Deleted',
            'approve' => 'Approved',
            'reject' => 'Rejected',
            'submit' => 'Submitted',
            'release' => 'Released',
            'upload' => 'Uploaded',
            'download' => 'Downloaded',
            'export' => 'Exported',
            'archive' => 'Archived',
            'restore' => 'Restored',
            'reset' => 'Reset',
            'toggle' => 'Toggled',
            'login' => 'Logged In',
            'logout' => 'Logged Out',
            'record' => 'Recorded',
            'adjust' => 'Adjusted',
            'request' => 'Requested',
        ];

        if (count($parts) === 1) {
            return $verbMap[strtolower($parts[0])] ?? $this->words($parts[0]);
        }

        $verbRaw = strtolower($parts[count($parts) - 1]);
        $verb = $verbMap[$verbRaw] ?? $this->words($parts[count($parts) - 1]);
        $target = $this->words(implode('_', array_slice($parts, 0, -1)));

        return trim("{$verb} {$target}");
    }

    private function permissionLabel(string $permission): string
    {
        $parts = explode('.', trim($permission));
        if (count($parts) <= 1) {
            return $this->words($permission);
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

        $action = $actionMap[$parts[count($parts) - 1]] ?? $this->words($parts[count($parts) - 1]);
        $resource = $this->words(implode('_', array_slice($parts, 0, -1)));

        return "{$resource}: {$action}";
    }

    private function words(string $value): string
    {
        return collect(explode('_', str_replace('-', '_', $value)))
            ->filter()
            ->map(fn ($chunk) => ucfirst($chunk))
            ->implode(' ');
    }
}
