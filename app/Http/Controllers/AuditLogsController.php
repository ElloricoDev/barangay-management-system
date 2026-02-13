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
                    (string) $log->action,
                    (string) class_basename($log->auditable_type),
                    (string) $log->auditable_id,
                    json_encode($this->maskSensitiveArray($log->before ?? []), JSON_UNESCAPED_SLASHES),
                    json_encode($this->maskSensitiveArray($log->after ?? []), JSON_UNESCAPED_SLASHES),
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
}
