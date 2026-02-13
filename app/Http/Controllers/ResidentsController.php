<?php

namespace App\Http\Controllers;

use App\Models\Resident;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResidentsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'id');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = ['id', 'first_name', 'last_name', 'birthdate', 'gender', 'contact_number'];

        if (! in_array($sort, $sortable, true)) {
            $sort = 'id';
        }

        $residents = Resident::query()
            ->whereNull('archived_at')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%")
                        ->orWhere('gender', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render($this->pagePrefix($request).'Residents', [
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'residents' => $residents,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'birthdate' => ['required', 'date'],
            'gender' => ['required', 'in:Male,Female,Other'],
            'contact_number' => ['nullable', 'string', 'max:255'],
        ]);

        $resident = Resident::create($validated);

        AuditLogger::log(
            $request,
            'resident.create',
            Resident::class,
            $resident->id,
            null,
            $resident->only(['first_name', 'last_name', 'middle_name', 'birthdate', 'gender', 'contact_number'])
        );

        return redirect()->back()->with('success', 'Resident record created.');
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
    public function update(Request $request, Resident $resident)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'birthdate' => ['required', 'date'],
            'gender' => ['required', 'in:Male,Female,Other'],
            'contact_number' => ['nullable', 'string', 'max:255'],
        ]);

        $before = $resident->only(['first_name', 'last_name', 'middle_name', 'birthdate', 'gender', 'contact_number']);
        $resident->update($validated);

        AuditLogger::log(
            $request,
            'resident.update',
            Resident::class,
            $resident->id,
            $before,
            $resident->only(['first_name', 'last_name', 'middle_name', 'birthdate', 'gender', 'contact_number'])
        );

        return redirect()->back()->with('success', 'Resident record updated.');
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
