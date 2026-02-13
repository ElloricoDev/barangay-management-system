<?php

namespace App\Http\Controllers;

use App\Models\Blotter;
use App\Models\Certificate;
use App\Models\DelegationSetting;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function admin(Request $request): Response
    {
        $stats = [
            'residents' => Resident::count(),
            'certificates' => Certificate::count(),
            'blotters' => Blotter::count(),
            'users' => User::count(),
            'pending_certificates' => Certificate::whereIn('status', ['submitted', 'ready_for_approval'])->count(),
            'open_blotters' => Blotter::where('status', 'ongoing')->count(),
        ];

        $recentResidents = Resident::query()
            ->select(['id', 'first_name', 'last_name', 'created_at'])
            ->latest()
            ->limit(5)
            ->get();

        $recentCertificates = Certificate::query()
            ->with('resident:id,first_name,last_name')
            ->select(['id', 'resident_id', 'type', 'status', 'issue_date'])
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recentResidents' => $recentResidents,
            'recentCertificates' => $recentCertificates,
            'delegation' => [
                'staff_can_approve' => DelegationSetting::current()->staff_can_approve,
            ],
        ]);
    }

    public function staff(Request $request): Response
    {
        $stats = [
            'pending_certificates' => Certificate::whereIn('status', ['submitted', 'ready_for_approval'])->count(),
            'approved_certificates' => Certificate::where('status', 'approved')->count(),
            'open_blotters' => Blotter::where('status', 'ongoing')->count(),
            'settled_blotters' => Blotter::where('status', 'settled')->count(),
        ];

        $recentPendingCertificates = Certificate::query()
            ->with('resident:id,first_name,last_name')
            ->where('status', 'pending')
            ->orWhere('status', 'ready_for_approval')
            ->select(['id', 'resident_id', 'type', 'status', 'issue_date'])
            ->latest()
            ->limit(5)
            ->get();

        $recentOpenBlotters = Blotter::query()
            ->where('status', 'ongoing')
            ->select(['id', 'complainant_name', 'respondent_name', 'incident_date', 'status'])
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('Staff/Dashboard', [
            'stats' => $stats,
            'recentPendingCertificates' => $recentPendingCertificates,
            'recentOpenBlotters' => $recentOpenBlotters,
        ]);
    }
}
