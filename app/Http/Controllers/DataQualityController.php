<?php

namespace App\Http\Controllers;

use App\Models\Resident;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DataQualityController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $archived = trim((string) $request->query('archived', 'active'));

        $residents = Resident::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%");
                });
            })
            ->when($archived === 'active', fn ($query) => $query->whereNull('archived_at'))
            ->when($archived === 'archived', fn ($query) => $query->whereNotNull('archived_at'))
            ->with('archiver:id,name')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(12)
            ->withQueryString();

        $duplicates = Resident::query()
            ->whereNull('archived_at')
            ->get(['id', 'first_name', 'last_name', 'middle_name', 'birthdate', 'contact_number'])
            ->groupBy(function (Resident $resident) {
                return strtolower(trim($resident->last_name)).'|'
                    .strtolower(trim($resident->first_name)).'|'
                    .strtolower(trim((string) $resident->middle_name)).'|'
                    .$resident->birthdate?->format('Y-m-d');
            })
            ->filter(fn ($group) => $group->count() > 1)
            ->map(function ($group) {
                /** @var \Illuminate\Support\Collection $group */
                $first = $group->first();
                return [
                    'signature' => sprintf(
                        '%s, %s %s (%s)',
                        $first->last_name,
                        $first->first_name,
                        (string) $first->middle_name,
                        $first->birthdate?->format('M d, Y')
                    ),
                    'count' => $group->count(),
                    'resident_ids' => $group->pluck('id')->values()->all(),
                ];
            })
            ->values();

        return Inertia::render('Staff/DataQuality', [
            'filters' => [
                'search' => $search,
                'archived' => $archived,
            ],
            'residents' => $residents,
            'duplicates' => $duplicates,
        ]);
    }

    public function archive(Request $request, Resident $resident)
    {
        $validated = $request->validate([
            'archive' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $before = $resident->only(['archived_at', 'archived_by', 'archive_reason', 'updated_at']);

        if ((bool) $validated['archive']) {
            $resident->archived_at = now();
            $resident->archived_by = $request->user()->id;
            $resident->archive_reason = $validated['reason'] ?? null;
        } else {
            $resident->archived_at = null;
            $resident->archived_by = null;
            $resident->archive_reason = null;
        }

        $resident->save();

        AuditLogger::log(
            $request,
            (bool) $validated['archive'] ? 'resident.archive' : 'resident.unarchive',
            Resident::class,
            $resident->id,
            $before,
            $resident->only(['archived_at', 'archived_by', 'archive_reason', 'updated_at'])
        );

        return redirect()->back()->with('success', (bool) $validated['archive']
            ? 'Resident archived successfully.'
            : 'Resident restored successfully.');
    }
}

