<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/SystemSettings', [
            'settings' => SystemSetting::current(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'barangay_name' => ['required', 'string', 'max:255'],
            'barangay_city' => ['nullable', 'string', 'max:255'],
            'barangay_province' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'receipt_prefix' => ['required', 'string', 'max:20'],
            'barangay_funds' => ['nullable', 'numeric', 'min:0'],
            'timezone' => ['required', 'string', 'max:100'],
            'maintenance_mode' => ['boolean'],
            'login_theme' => ['nullable', 'in:emerald,teal,blue,rose,amber'],
            'footer_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $settings = SystemSetting::current();
        $before = $settings->only([
            'barangay_name',
            'barangay_city',
            'barangay_province',
            'contact_number',
            'contact_email',
            'receipt_prefix',
            'barangay_funds',
            'timezone',
            'maintenance_mode',
            'login_theme',
            'footer_note',
        ]);

        $settings->update([
            ...$validated,
            'barangay_funds' => (float) ($validated['barangay_funds'] ?? 0),
            'maintenance_mode' => (bool) ($validated['maintenance_mode'] ?? false),
            'login_theme' => $validated['login_theme'] ?? 'emerald',
            'updated_by' => $request->user()->id,
        ]);

        AuditLogger::log(
            $request,
            'system.settings.update',
            SystemSetting::class,
            $settings->id,
            $before,
            $settings->only([
                'barangay_name',
                'barangay_city',
                'barangay_province',
                'contact_number',
                'contact_email',
                'receipt_prefix',
                'barangay_funds',
                'timezone',
                'maintenance_mode',
                'login_theme',
                'footer_note',
            ])
        );

        return redirect()->back()->with('success', 'System settings updated.');
    }
}
