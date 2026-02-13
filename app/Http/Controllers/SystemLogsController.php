<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemLogsController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'action' => trim((string) $request->query('action', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
        ];

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $search = $filters['search'];
                $query->where(function ($inner) use ($search) {
                    $inner->where('action', 'like', "%{$search}%")
                        ->orWhere('auditable_type', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['action'] !== '', function ($query) use ($filters) {
                $query->where('action', $filters['action']);
            })
            ->when($filters['date_from'] !== '', function ($query) use ($filters) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            })
            ->when($filters['date_to'] !== '', function ($query) use ($filters) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $actions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return Inertia::render('Admin/SystemLogs', [
            'filters' => $filters,
            'logs' => $logs,
            'actions' => $actions,
            'appLogTail' => $this->tailLogFile(storage_path('logs/laravel.log'), 150),
        ]);
    }

    private function tailLogFile(string $path, int $maxLines = 150): array
    {
        if (! is_file($path)) {
            return [];
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if (! is_array($lines)) {
            return [];
        }

        $slice = array_slice($lines, -1 * $maxLines);
        return array_values(array_filter($slice, fn ($line) => trim($line) !== ''));
    }
}
