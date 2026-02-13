<?php

use App\Http\Controllers\UsersController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AdminAccountController;
use App\Http\Controllers\AuditLogsController;
use App\Http\Controllers\DelegationController;
use App\Http\Controllers\ResidentsController;
use App\Http\Controllers\CertificatesController;
use App\Http\Controllers\BlottersController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome');
});

Route::post('/login', [UsersController::class, 'login'])->name('login');
Route::post('/logout', [UsersController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->get('/dashboard', function () {
    return auth()->user()->hasAnyRole(['captain', 'secretary', 'admin'])
        ? redirect()->route('admin.dashboard')
        : redirect()->route('staff.dashboard');
})->name('dashboard');

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'admin'])
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'admin'])
            ->middleware('permission:dashboard.view')
            ->name('dashboard');

        Route::get('/residents', [ResidentsController::class, 'index'])
            ->middleware('permission:residents.view')
            ->name('residents');
        Route::post('/residents', [ResidentsController::class, 'store'])
            ->middleware('permission:residents.create')
            ->name('residents.store');
        Route::put('/residents/{resident}', [ResidentsController::class, 'update'])
            ->middleware('permission:residents.update')
            ->name('residents.update');
        Route::get('/certificates', [CertificatesController::class, 'index'])
            ->middleware('permission:certificates.view')
            ->name('certificates');
        Route::post('/certificates', [CertificatesController::class, 'store'])
            ->middleware('permission:certificates.create')
            ->name('certificates.store');
        Route::put('/certificates/{certificate}', [CertificatesController::class, 'update'])
            ->middleware('permission:certificates.update')
            ->name('certificates.update');
        Route::patch('/certificates/{certificate}/submit', [CertificatesController::class, 'submit'])
            ->middleware('permission:certificates.submit')
            ->name('certificates.submit');
        Route::patch('/certificates/{certificate}/release', [CertificatesController::class, 'release'])
            ->middleware('permission:certificates.release_if_approved')
            ->name('certificates.release');
        Route::patch('/certificates/{certificate}/approve', [CertificatesController::class, 'approve'])
            ->middleware('permission:certificates.approve')
            ->name('certificates.approve');
        Route::patch('/certificates/{certificate}/reject', [CertificatesController::class, 'reject'])
            ->middleware('permission:certificates.approve')
            ->name('certificates.reject');
        Route::get('/blotter', [BlottersController::class, 'index'])
            ->middleware('permission:blotter.view')
            ->name('blotter');
        Route::post('/blotter', [BlottersController::class, 'store'])
            ->middleware('permission:blotter.create')
            ->name('blotter.store');
        Route::put('/blotter/{blotter}', [BlottersController::class, 'update'])
            ->middleware('permission:blotter.update')
            ->name('blotter.update');
        Route::patch('/blotter/{blotter}/approve', [BlottersController::class, 'approve'])
            ->middleware('permission:blotter.approve')
            ->name('blotter.approve');
        Route::patch('/blotter/{blotter}/reject', [BlottersController::class, 'reject'])
            ->middleware('permission:blotter.approve')
            ->name('blotter.reject');
        Route::get('/users', [UsersController::class, 'index'])
            ->middleware('permission:users.manage')
            ->name('users');
        Route::post('/users', [UsersController::class, 'store'])
            ->middleware('permission:users.manage')
            ->name('users.store');
        Route::put('/users/{user}', [UsersController::class, 'update'])
            ->middleware('permission:users.manage')
            ->name('users.update');
        Route::delete('/users/{user}', [UsersController::class, 'destroy'])
            ->middleware('permission:users.manage')
            ->name('users.destroy');
        Route::patch('/users/{user}/reset-password', [UsersController::class, 'resetPassword'])
            ->middleware('permission:users.manage')
            ->name('users.reset-password');
        Route::get('/audit-logs', [AuditLogsController::class, 'index'])
            ->middleware('permission:audit.view')
            ->name('audit-logs');
        Route::get('/audit-logs/export', [AuditLogsController::class, 'exportCsv'])
            ->middleware('permission:audit.view')
            ->name('audit-logs.export');

        Route::get('/account', [AdminAccountController::class, 'edit'])->name('account.edit');
        Route::put('/account/profile', [AdminAccountController::class, 'updateProfile'])->name('account.profile.update');
        Route::put('/account/password', [AdminAccountController::class, 'updatePassword'])->name('account.password.update');
        Route::delete('/account', [AdminAccountController::class, 'destroy'])->name('account.destroy');
        Route::patch('/delegation/toggle', [DelegationController::class, 'toggle'])
            ->middleware('permission:delegation.manage')
            ->name('delegation.toggle');
    });

Route::prefix('staff')
    ->name('staff.')
    ->middleware(['auth', 'staff'])
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'staff'])
            ->middleware('permission:dashboard.view')
            ->name('dashboard');

        Route::get('/residents', [ResidentsController::class, 'index'])
            ->middleware('permission:residents.view')
            ->name('residents');
        Route::post('/residents', [ResidentsController::class, 'store'])
            ->middleware('permission:residents.create')
            ->name('residents.store');
        Route::put('/residents/{resident}', [ResidentsController::class, 'update'])
            ->middleware('permission:residents.update')
            ->name('residents.update');
        Route::get('/certificates', [CertificatesController::class, 'index'])
            ->middleware('permission:certificates.view')
            ->name('certificates');
        Route::post('/certificates', [CertificatesController::class, 'store'])
            ->middleware('permission:certificates.create')
            ->name('certificates.store');
        Route::put('/certificates/{certificate}', [CertificatesController::class, 'update'])
            ->middleware('permission:certificates.update')
            ->name('certificates.update');
        Route::patch('/certificates/{certificate}/submit', [CertificatesController::class, 'submit'])
            ->middleware('permission:certificates.submit')
            ->name('certificates.submit');
        Route::patch('/certificates/{certificate}/release', [CertificatesController::class, 'release'])
            ->middleware('permission:certificates.release_if_approved')
            ->name('certificates.release');
        Route::patch('/certificates/{certificate}/approve', [CertificatesController::class, 'approve'])
            ->middleware('permission:certificates.approve')
            ->name('certificates.approve');
        Route::patch('/certificates/{certificate}/reject', [CertificatesController::class, 'reject'])
            ->middleware('permission:certificates.approve')
            ->name('certificates.reject');
        Route::get('/blotter', [BlottersController::class, 'index'])
            ->middleware('permission:blotter.view')
            ->name('blotter');
        Route::post('/blotter', [BlottersController::class, 'store'])
            ->middleware('permission:blotter.create')
            ->name('blotter.store');
        Route::put('/blotter/{blotter}', [BlottersController::class, 'update'])
            ->middleware('permission:blotter.update')
            ->name('blotter.update');
        Route::patch('/blotter/{blotter}/approve', [BlottersController::class, 'approve'])
            ->middleware('permission:blotter.approve')
            ->name('blotter.approve');
        Route::patch('/blotter/{blotter}/reject', [BlottersController::class, 'reject'])
            ->middleware('permission:blotter.approve')
            ->name('blotter.reject');
    });
