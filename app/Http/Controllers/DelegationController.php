<?php

namespace App\Http\Controllers;

use App\Models\DelegationSetting;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

class DelegationController extends Controller
{
    public function toggle(Request $request)
    {
        $setting = DelegationSetting::current();
        $before = $setting->only(['staff_can_approve', 'enabled_by', 'enabled_at']);
        $setting->staff_can_approve = ! $setting->staff_can_approve;
        $setting->enabled_by = $request->user()->id;
        $setting->enabled_at = now();
        $setting->save();

        AuditLogger::log(
            $request,
            'delegation.toggle',
            DelegationSetting::class,
            $setting->id,
            $before,
            $setting->only(['staff_can_approve', 'enabled_by', 'enabled_at'])
        );

        return redirect()->back()->with(
            'success',
            $setting->staff_can_approve
                ? 'Delegation enabled: staff can temporarily approve/reject.'
                : 'Delegation disabled: only captain/secretary can approve/reject.'
        );
    }
}
