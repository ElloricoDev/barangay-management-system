<?php

namespace App\Http\Middleware;

use App\Models\DelegationSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Insufficient permission.');
        }

        if ($user->hasPermission($permission)) {
            return $next($request);
        }

        $canDelegateApproval = in_array($permission, ['certificates.approve', 'blotter.approve'], true)
            && $user->role === 'staff'
            && DelegationSetting::current()->staff_can_approve;

        if (! $canDelegateApproval) {
            abort(403, 'Insufficient permission.');
        }

        return $next($request);
    }
}
