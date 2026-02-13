<?php

namespace App\Http\Controllers;

use App\Models\BarangayProgram;
use App\Models\Resident;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class YouthController extends Controller
{
    public function management(Request $request): Response
    {
        $stats = [
            'youth_residents' => Resident::query()
                ->whereDate('birthdate', '>=', now()->subYears(30)->toDateString())
                ->count(),
            'active_programs' => BarangayProgram::query()
                ->where('category', 'youth')
                ->where('status', 'ongoing')
                ->count(),
            'completed_programs' => BarangayProgram::query()
                ->where('category', 'youth')
                ->where('status', 'completed')
                ->count(),
        ];

        $recentPrograms = BarangayProgram::query()
            ->where('category', 'youth')
            ->latest()
            ->limit(6)
            ->get();

        return Inertia::render('Admin/YouthModule', [
            'section' => 'youth_management',
            'stats' => $stats,
            'recentPrograms' => $recentPrograms,
        ]);
    }

    public function residents(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'last_name');
        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortable = ['first_name', 'last_name', 'birthdate', 'created_at'];

        if (! in_array($sort, $sortable, true)) {
            $sort = 'last_name';
        }

        $residents = Resident::query()
            ->whereDate('birthdate', '>=', now()->subYears(30)->toDateString())
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('gender', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Admin/YouthModule', [
            'section' => 'youth_residents',
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'youthResidents' => $residents,
        ]);
    }

    public function programs(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $sort = (string) $request->query('sort', 'created_at');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = ['title', 'status', 'start_date', 'end_date', 'budget', 'participants', 'created_at'];

        if (! in_array($sort, $sortable, true)) {
            $sort = 'created_at';
        }

        $programs = BarangayProgram::query()
            ->where('category', 'youth')
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

        return Inertia::render('Admin/YouthModule', [
            'section' => 'youth_programs',
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'youthPrograms' => $programs,
        ]);
    }

    public function reports(Request $request): Response
    {
        $base = BarangayProgram::query()->where('category', 'youth');

        $summary = [
            'total_programs' => (clone $base)->count(),
            'ongoing_programs' => (clone $base)->where('status', 'ongoing')->count(),
            'completed_programs' => (clone $base)->where('status', 'completed')->count(),
            'planned_programs' => (clone $base)->where('status', 'planned')->count(),
            'total_budget' => (float) (clone $base)->sum('budget'),
            'total_participants' => (int) (clone $base)->sum('participants'),
        ];

        $byCommittee = (clone $base)
            ->selectRaw('COALESCE(committee, "Unassigned") as committee')
            ->selectRaw('COUNT(*) as programs_count')
            ->selectRaw('SUM(COALESCE(participants, 0)) as participants_count')
            ->groupBy('committee')
            ->orderByDesc('programs_count')
            ->get();

        return Inertia::render('Admin/YouthModule', [
            'section' => 'youth_reports',
            'reportSummary' => $summary,
            'reportByCommittee' => $byCommittee,
        ]);
    }

    public function storeProgram(Request $request)
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
            'category' => 'youth',
            'created_by' => $request->user()->id,
            'participants' => $validated['participants'] ?? 0,
        ]);

        AuditLogger::log(
            $request,
            'youth.program.create',
            BarangayProgram::class,
            $program->id,
            null,
            $program->only(['title', 'category', 'committee', 'status', 'start_date', 'end_date', 'budget', 'participants'])
        );

        return redirect()->back()->with('success', 'Youth program created.');
    }

    public function updateProgram(Request $request, BarangayProgram $program)
    {
        if ($program->category !== 'youth') {
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
            'youth.program.update',
            BarangayProgram::class,
            $program->id,
            $before,
            $program->only(['title', 'committee', 'status', 'start_date', 'end_date', 'budget', 'participants', 'remarks'])
        );

        return redirect()->back()->with('success', 'Youth program updated.');
    }

    public function destroyProgram(Request $request, BarangayProgram $program)
    {
        if ($program->category !== 'youth') {
            abort(404);
        }

        $before = $program->only(['title', 'committee', 'status', 'start_date', 'end_date', 'budget', 'participants', 'remarks']);
        $programId = $program->id;
        $program->delete();

        AuditLogger::log(
            $request,
            'youth.program.delete',
            BarangayProgram::class,
            $programId,
            $before,
            null
        );

        return redirect()->back()->with('success', 'Youth program deleted.');
    }
}
