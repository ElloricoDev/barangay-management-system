<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\DelegationSetting;
use App\Models\Resident;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CertificatesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'id');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = ['id', 'type', 'purpose', 'status', 'issue_date', 'resident_name'];

        if (! in_array($sort, $sortable, true)) {
            $sort = 'id';
        }

        $certificatesQuery = Certificate::query()
            ->with('resident:id,first_name,last_name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('type', 'like', "%{$search}%")
                        ->orWhere('purpose', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('resident', function ($residentQuery) use ($search) {
                            $residentQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            });

        if ($sort === 'resident_name') {
            $certificatesQuery
                ->leftJoin('residents', 'certificates.resident_id', '=', 'residents.id')
                ->select('certificates.*')
                ->orderBy('residents.last_name', $direction)
                ->orderBy('residents.first_name', $direction);
        } else {
            $certificatesQuery->orderBy($sort, $direction);
        }

        $certificates = $certificatesQuery
            ->paginate(10)
            ->withQueryString();

        return Inertia::render($this->pagePrefix($request).'Certificates', [
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'certificates' => $certificates,
            'residents' => Resident::query()
                ->select(['id', 'first_name', 'last_name'])
                ->whereNull('archived_at')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(200)
                ->get(),
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
            'resident_id' => ['required', 'exists:residents,id'],
            'type' => ['required', 'in:clearance,indigency,residency'],
            'purpose' => ['required', 'string', 'max:255'],
        ]);

        $certificate = Certificate::create([
            ...$validated,
            'status' => 'submitted',
            'issue_date' => null,
        ]);

        AuditLogger::log(
            $request,
            'certificate.create',
            Certificate::class,
            $certificate->id,
            null,
            $certificate->only(['resident_id', 'type', 'purpose', 'status', 'issue_date'])
        );

        return redirect()->back()->with('success', 'Certificate request created.');
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
    public function update(Request $request, Certificate $certificate)
    {
        if (in_array($certificate->status, ['approved', 'rejected', 'released'], true)) {
            return redirect()->back()->with('error', 'Finalized certificate cannot be edited.');
        }

        $validated = $request->validate([
            'resident_id' => ['required', 'exists:residents,id'],
            'type' => ['required', 'in:clearance,indigency,residency'],
            'purpose' => ['required', 'string', 'max:255'],
        ]);

        $before = $certificate->only(['resident_id', 'type', 'purpose', 'status', 'issue_date']);
        $certificate->update($validated);

        AuditLogger::log(
            $request,
            'certificate.update',
            Certificate::class,
            $certificate->id,
            $before,
            $certificate->only(['resident_id', 'type', 'purpose', 'status', 'issue_date'])
        );

        return redirect()->back()->with('success', 'Certificate request updated.');
    }

    public function submit(Request $request, Certificate $certificate)
    {
        if ($certificate->status !== 'submitted') {
            return redirect()->back()->with('error', 'Only submitted requests can be moved for approval.');
        }

        $before = $certificate->only(['status', 'issue_date', 'updated_at']);
        $certificate->status = 'ready_for_approval';
        $certificate->save();

        AuditLogger::log(
            $request,
            'certificate.submit_for_approval',
            Certificate::class,
            $certificate->id,
            $before,
            $certificate->only(['status', 'issue_date', 'updated_at'])
        );

        return redirect()->back()->with('success', 'Certificate submitted for approval.');
    }

    public function release(Request $request, Certificate $certificate)
    {
        if ($certificate->status !== 'approved') {
            return redirect()->back()->with('error', 'Only approved certificates can be released.');
        }

        $before = $certificate->only(['status', 'issue_date', 'updated_at']);
        $certificate->status = 'released';
        $certificate->issue_date = $certificate->issue_date ?? now()->toDateString();
        $certificate->save();

        AuditLogger::log(
            $request,
            'certificate.release',
            Certificate::class,
            $certificate->id,
            $before,
            $certificate->only(['status', 'issue_date', 'updated_at'])
        );

        return redirect()->back()->with('success', 'Certificate released.');
    }

    public function approve(Request $request, Certificate $certificate)
    {
        if ($certificate->status !== 'ready_for_approval') {
            return redirect()->back()->with('error', 'Certificate must be ready for approval first.');
        }

        $before = $certificate->only(['status', 'issue_date', 'updated_at']);

        $certificate->status = 'approved';
        $certificate->issue_date = $certificate->issue_date ?? now()->toDateString();
        $certificate->save();

        AuditLogger::log(
            $request,
            'certificate.approve',
            Certificate::class,
            $certificate->id,
            $before,
            $certificate->only(['status', 'issue_date', 'updated_at'])
        );

        return redirect()->back()->with('success', 'Certificate approved successfully.');
    }

    public function reject(Request $request, Certificate $certificate)
    {
        if ($certificate->status !== 'ready_for_approval') {
            return redirect()->back()->with('error', 'Certificate must be ready for approval first.');
        }

        $before = $certificate->only(['status', 'issue_date', 'updated_at']);

        $certificate->status = 'rejected';
        $certificate->save();

        AuditLogger::log(
            $request,
            'certificate.reject',
            Certificate::class,
            $certificate->id,
            $before,
            $certificate->only(['status', 'issue_date', 'updated_at'])
        );

        return redirect()->back()->with('success', 'Certificate rejected.');
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
