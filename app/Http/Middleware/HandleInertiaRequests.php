<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $settings = SystemSetting::current();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'permissions' => $request->user()
                    ? collect(config('permissions.matrix', []))
                        ->flatten()
                        ->unique()
                        ->filter(fn ($permission) => $request->user()->hasPermission((string) $permission))
                        ->values()
                        ->all()
                    : [],
            ],
            'systemSettings' => fn () => [
                'barangay_name' => $settings->barangay_name,
                'maintenance_mode' => $settings->maintenance_mode,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
