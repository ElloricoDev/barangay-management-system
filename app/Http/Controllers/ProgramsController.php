<?php

namespace App\Http\Controllers;

use App\Models\BarangayProgram;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgramsController extends Controller
{
    public function projects(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $sort = (string) $request->query('sort', 'created_at');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = ['title', 'committee', 'status', 'start_date', 'end_date', 'budget', 'participants', 'created_at'];

        if (! in_array($sort, $sortable, true)) {
            $sort = 'created_at';
        }

        $programs = BarangayProgram::query()
            ->where('category', 'barangay')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('committee', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/ProgramsModule', [
            'section' => 'programs_projects',
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'programs' => $programs,
        ]);
    }

    public function committeeReports(Request $request): Response
    {
        $data = $this->committeeReportData($request);

        return Inertia::render('Admin/ProgramsModule', [
            'section' => 'committee_reports',
            'filters' => $data['filters'],
            'committeeReports' => $data['committeeReports'],
            'selectedCommitteePrograms' => $data['selectedCommitteePrograms'],
        ]);
    }

    public function committeeReportsExport(Request $request): StreamedResponse
    {
        $data = $this->committeeReportData($request);
        $filename = 'committee-reports-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Date From', $data['filters']['date_from']]);
            fputcsv($handle, ['Date To', $data['filters']['date_to']]);
            fputcsv($handle, ['Status Filter', $data['filters']['status'] !== '' ? $data['filters']['status'] : 'All']);
            if ($data['filters']['committee'] !== '') {
                fputcsv($handle, ['Committee Drill-down', $data['filters']['committee']]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Committee', 'Total Programs', 'Ongoing', 'Completed', 'Participants']);
            foreach ($data['committeeReports'] as $row) {
                fputcsv($handle, [
                    $row['committee'] ?? '',
                    $row['total_programs'] ?? 0,
                    $row['ongoing_programs'] ?? 0,
                    $row['completed_programs'] ?? 0,
                    $row['participants'] ?? 0,
                ]);
            }

            if (! empty($data['selectedCommitteePrograms'])) {
                fputcsv($handle, []);
                fputcsv($handle, ['Programs for Committee', $data['filters']['committee']]);
                fputcsv($handle, ['Title', 'Status', 'Start Date', 'End Date', 'Participants', 'Budget']);

                foreach ($data['selectedCommitteePrograms']['data'] as $program) {
                    fputcsv($handle, [
                        $program['title'] ?? '',
                        $program['status'] ?? '',
                        $program['start_date'] ?? '',
                        $program['end_date'] ?? '',
                        $program['participants'] ?? 0,
                        $program['budget'] ?? 0,
                    ]);
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function monitoring(Request $request): Response
    {
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'start_date');
        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortable = ['title', 'status', 'committee', 'start_date', 'end_date', 'participants'];

        if (! in_array($sort, $sortable, true)) {
            $sort = 'start_date';
        }

        $monitoring = BarangayProgram::query()
            ->where('category', 'barangay')
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('committee', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Admin/ProgramsModule', [
            'section' => 'programs_monitoring',
            'filters' => [
                'status' => $status,
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'monitoring' => $monitoring,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'committee' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:planned,ongoing,completed,cancelled'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'participants' => ['nullable', 'integer', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ]);

        $program = BarangayProgram::create([
            ...$validated,
            'category' => 'barangay',
            'participants' => $validated['participants'] ?? 0,
            'created_by' => $request->user()->id,
        ]);

        AuditLogger::log(
            $request,
            'program.create',
            BarangayProgram::class,
            $program->id,
            null,
            $program->only(['title', 'category', 'committee', 'status', 'start_date', 'end_date', 'budget', 'participants'])
        );

        return redirect()->back()->with('success', 'Program/project created.');
    }

    public function update(Request $request, BarangayProgram $program)
    {
        if ($program->category !== 'barangay') {
            abort(404);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'committee' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:planned,ongoing,completed,cancelled'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'participants' => ['nullable', 'integer', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ]);

        $before = $program->only(['title', 'committee', 'status', 'start_date', 'end_date', 'budget', 'participants', 'remarks']);
        $program->update([
            ...$validated,
            'participants' => $validated['participants'] ?? 0,
        ]);

        AuditLogger::log(
            $request,
            'program.update',
            BarangayProgram::class,
            $program->id,
            $before,
            $program->only(['title', 'committee', 'status', 'start_date', 'end_date', 'budget', 'participants', 'remarks'])
        );

        return redirect()->back()->with('success', 'Program/project updated.');
    }

    public function destroy(Request $request, BarangayProgram $program)
    {
        if ($program->category !== 'barangay') {
            abort(404);
        }

        $before = $program->only(['title', 'committee', 'status', 'start_date', 'end_date', 'budget', 'participants', 'remarks']);
        $programId = $program->id;
        $program->delete();

        AuditLogger::log(
            $request,
            'program.delete',
            BarangayProgram::class,
            $programId,
            $before,
            null
        );

        return redirect()->back()->with('success', 'Program/project deleted.');
    }

    private function committeeReportData(Request $request): array
    {
        [$dateFrom, $dateTo] = $this->dateRange($request);
        $committee = trim((string) $request->query('committee', ''));
        $status = trim((string) $request->query('status', ''));
        $allowedStatuses = ['planned', 'ongoing', 'completed', 'cancelled'];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $base = BarangayProgram::query()
            ->where('category', 'barangay')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            });

        $reports = (clone $base)
            ->selectRaw('COALESCE(NULLIF(committee, ""), "Unassigned") as committee')
            ->selectRaw('COUNT(*) as total_programs')
            ->selectRaw('SUM(CASE WHEN status = "ongoing" THEN 1 ELSE 0 END) as ongoing_programs')
            ->selectRaw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_programs')
            ->selectRaw('SUM(COALESCE(participants, 0)) as participants')
            ->groupBy('committee')
            ->orderByDesc('total_programs')
            ->get()
            ->map(fn ($row) => [
                'committee' => (string) $row->committee,
                'total_programs' => (int) $row->total_programs,
                'ongoing_programs' => (int) $row->ongoing_programs,
                'completed_programs' => (int) $row->completed_programs,
                'participants' => (int) $row->participants,
            ])
            ->values();

        $selectedCommitteePrograms = null;
        if ($committee !== '') {
            $selectedCommitteePrograms = (clone $base)
                ->when($committee === 'Unassigned', function ($query) {
                    $query->where(function ($inner) {
                        $inner->whereNull('committee')->orWhere('committee', '');
                    });
                }, function ($query) use ($committee) {
                    $query->where('committee', $committee);
                })
                ->orderByDesc('created_at')
                ->paginate(10)
                ->withQueryString();
        }

        return [
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'status' => $status,
                'committee' => $committee,
            ],
            'committeeReports' => $reports,
            'selectedCommitteePrograms' => $selectedCommitteePrograms,
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
}
