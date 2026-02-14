<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Blotter;
use App\Models\Certificate;
use App\Models\Payment;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function reports(Request $request): Response
    {
        return Inertia::render('Admin/Reports', $this->reportsData($request));
    }

    public function analytics(Request $request): Response
    {
        return Inertia::render('Admin/ReportsAnalytics', $this->analyticsData($request));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $data = $this->reportsData($request);
        $filename = 'reports-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Report', 'Value']);
            fputcsv($handle, ['Residents', $data['kpis']['residents'] ?? 0]);
            fputcsv($handle, ['Certificates', $data['kpis']['certificates'] ?? 0]);
            fputcsv($handle, ['Blotters', $data['kpis']['blotters'] ?? 0]);
            fputcsv($handle, ['Users', $data['kpis']['users'] ?? 0]);
            fputcsv($handle, ['Collections Total', $data['kpis']['collections_total'] ?? 0]);
            fputcsv($handle, ['Collections Range', $data['kpis']['collections_range'] ?? 0]);
            fputcsv($handle, ['Audit Events Range', $data['kpis']['audit_events_range'] ?? 0]);

            fputcsv($handle, []);
            fputcsv($handle, ['Top Service Type', 'Transactions', 'Amount']);
            foreach ($data['topServices'] as $row) {
                fputcsv($handle, [
                    $row['service_type'] ?? '',
                    $row['transactions'] ?? 0,
                    $row['amount'] ?? 0,
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Recent Activity', 'User', 'Action', 'Module']);
            foreach ($data['recentActivity'] as $row) {
                fputcsv($handle, [
                    $row['created_at'] ?? '',
                    data_get($row, 'user.name', 'System'),
                    $row['action'] ?? '',
                    class_basename((string) ($row['auditable_type'] ?? '')),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function reportsData(Request $request): array
    {
        [$dateFrom, $dateTo] = $this->dateRange($request);

        return [
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ],
            'kpis' => $this->kpis($dateFrom, $dateTo),
            'recentActivity' => AuditLog::query()
                ->with('user:id,name,email')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->latest()
                ->limit(20)
                ->get(),
            'topServices' => Payment::query()
                ->select('service_type')
                ->selectRaw('COUNT(*) as transactions')
                ->selectRaw('SUM(amount) as amount')
                ->whereBetween('paid_at', [$dateFrom, $dateTo])
                ->groupBy('service_type')
                ->orderByDesc('amount')
                ->limit(10)
                ->get(),
        ];
    }

    private function analyticsData(Request $request): array
    {
        [$dateFrom, $dateTo] = $this->dateRange($request);

        $periodLabels = collect();
        $cursor = $dateFrom->copy()->startOfMonth();
        $limitEnd = $dateTo->copy()->endOfMonth();
        while ($cursor->lte($limitEnd)) {
            $periodLabels->push($cursor->format('Y-m'));
            $cursor->addMonth();
        }

        $residentCounts = Resident::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period")
            ->selectRaw('COUNT(*) as total')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('period')
            ->pluck('total', 'period');

        $certificateCounts = Certificate::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period")
            ->selectRaw('COUNT(*) as total')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('period')
            ->pluck('total', 'period');

        $paymentSums = Payment::query()
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as period")
            ->selectRaw('SUM(amount) as total')
            ->whereBetween('paid_at', [$dateFrom, $dateTo])
            ->groupBy('period')
            ->pluck('total', 'period');

        return [
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ],
            'kpis' => $this->kpis($dateFrom, $dateTo),
            'timeline' => $periodLabels->map(function ($period) use ($residentCounts, $certificateCounts, $paymentSums) {
                return [
                    'period' => $period,
                    'residents' => (int) ($residentCounts[$period] ?? 0),
                    'certificates' => (int) ($certificateCounts[$period] ?? 0),
                    'collections' => (float) ($paymentSums[$period] ?? 0),
                ];
            })->values(),
            'certificateStatus' => Certificate::query()
                ->select('status')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('status')
                ->orderBy('status')
                ->get(),
            'blotterStatus' => Blotter::query()
                ->select('status')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('status')
                ->orderBy('status')
                ->get(),
        ];
    }

    private function dateRange(Request $request): array
    {
        $dateFrom = $request->query('date_from')
            ? Carbon::parse((string) $request->query('date_from'))->startOfDay()
            : now()->subMonths(11)->startOfMonth();
        $dateTo = $request->query('date_to')
            ? Carbon::parse((string) $request->query('date_to'))->endOfDay()
            : now()->endOfDay();

        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        return [$dateFrom, $dateTo];
    }

    private function kpis(Carbon $dateFrom, Carbon $dateTo): array
    {
        return [
            'residents' => Resident::count(),
            'certificates' => Certificate::count(),
            'blotters' => Blotter::count(),
            'users' => User::count(),
            'collections_total' => (float) Payment::sum('amount'),
            'collections_range' => (float) Payment::whereBetween('paid_at', [$dateFrom, $dateTo])->sum('amount'),
            'audit_events_range' => (int) AuditLog::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
        ];
    }
}
