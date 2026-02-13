<?php

namespace App\Http\Controllers;

use App\Models\BarangayProgram;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
        $reports = BarangayProgram::query()
            ->selectRaw('COALESCE(committee, "Unassigned") as committee')
            ->selectRaw('COUNT(*) as total_programs')
            ->selectRaw('SUM(CASE WHEN status = "ongoing" THEN 1 ELSE 0 END) as ongoing_programs')
            ->selectRaw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_programs')
            ->selectRaw('SUM(COALESCE(participants, 0)) as participants')
            ->where('category', 'barangay')
            ->groupBy('committee')
            ->orderByDesc('total_programs')
            ->get();

        return Inertia::render('Admin/ProgramsModule', [
            'section' => 'committee_reports',
            'committeeReports' => $reports,
        ]);
    }

    public function monitoring(Request $request): Response
    {
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'start_date');
        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortable = ['title', 'category', 'status', 'committee', 'start_date', 'end_date', 'participants'];

        if (! in_array($sort, $sortable, true)) {
            $sort = 'start_date';
        }

        $monitoring = BarangayProgram::query()
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('committee', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
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
}
