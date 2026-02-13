<?php

namespace App\Http\Controllers;

use App\Models\Blotter;
use App\Models\DelegationSetting;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlottersController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'id');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = ['id', 'complainant_name', 'respondent_name', 'incident_date', 'status'];

        if (! in_array($sort, $sortable, true)) {
            $sort = 'id';
        }

        $blotters = Blotter::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('complainant_name', 'like', "%{$search}%")
                        ->orWhere('respondent_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render($this->pagePrefix($request).'Blotter', [
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'blotters' => $blotters,
            'delegation' => [
                'staff_can_approve' => DelegationSetting::current()->staff_can_approve,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'complainant_name' => ['required', 'string', 'max:255'],
            'respondent_name' => ['required', 'string', 'max:255'],
            'incident_date' => ['required', 'date'],
            'description' => ['required', 'string'],
            'status' => ['nullable', 'in:ongoing,settled'],
        ]);

        $blotter = Blotter::create([
            ...$validated,
            'status' => $validated['status'] ?? 'ongoing',
        ]);

        AuditLogger::log(
            $request,
            'blotter.create',
            Blotter::class,
            $blotter->id,
            null,
            $blotter->only(['complainant_name', 'respondent_name', 'incident_date', 'description', 'status'])
        );

        return redirect()->back()->with('success', 'Blotter case created.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Blotter $blotter)
    {
        $validated = $request->validate([
            'complainant_name' => ['required', 'string', 'max:255'],
            'respondent_name' => ['required', 'string', 'max:255'],
            'incident_date' => ['required', 'date'],
            'description' => ['required', 'string'],
            'status' => ['required', 'in:ongoing,settled'],
        ]);

        $before = $blotter->only(['complainant_name', 'respondent_name', 'incident_date', 'description', 'status']);
        $blotter->update($validated);

        AuditLogger::log(
            $request,
            'blotter.update',
            Blotter::class,
            $blotter->id,
            $before,
            $blotter->only(['complainant_name', 'respondent_name', 'incident_date', 'description', 'status'])
        );

        return redirect()->back()->with('success', 'Blotter case updated.');
    }

    public function approve(Request $request, Blotter $blotter)
    {
        $before = $blotter->only(['status', 'updated_at']);

        $blotter->status = 'settled';
        $blotter->save();

        AuditLogger::log(
            $request,
            'blotter.approve',
            Blotter::class,
            $blotter->id,
            $before,
            $blotter->only(['status', 'updated_at'])
        );

        return redirect()->back()->with('success', 'Blotter case approved and marked settled.');
    }

    public function reject(Request $request, Blotter $blotter)
    {
        $before = $blotter->only(['status', 'updated_at']);

        $blotter->status = 'ongoing';
        $blotter->save();

        AuditLogger::log(
            $request,
            'blotter.reject',
            Blotter::class,
            $blotter->id,
            $before,
            $blotter->only(['status', 'updated_at'])
        );

        return redirect()->back()->with('success', 'Blotter case moved back to ongoing.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    private function pagePrefix(Request $request): string
    {
        return $request->routeIs('admin.*') ? 'Admin/' : 'Staff/';
    }
}
