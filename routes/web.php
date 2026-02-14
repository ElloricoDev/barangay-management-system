<?php

use App\Http\Controllers\UsersController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AdminAccountController;
use App\Http\Controllers\AuditLogsController;
use App\Http\Controllers\DelegationController;
use App\Http\Controllers\DocumentsController;
use App\Http\Controllers\FinancialManagementController;
use App\Http\Controllers\RolePermissionsController;
use App\Http\Controllers\BackupRestoreController;
use App\Http\Controllers\SystemLogsController;
use App\Http\Controllers\YouthController;
use App\Http\Controllers\ProgramsController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ResidentsController;
use App\Http\Controllers\CertificatesController;
use App\Http\Controllers\BlottersController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\DataQualityController;
use App\Http\Controllers\AccessMatrixController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome');
});

Route::get('/login', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome');
})->name('login');
Route::post('/login', [UsersController::class, 'login'])->name('login.attempt');
Route::post('/logout', [UsersController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->get('/dashboard', function () {
    return auth()->user()->isAdminPanelRole()
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

        Route::get('/financial-management', [FinancialManagementController::class, 'financialManagement'])
            ->middleware('permission:financial_management.view')
            ->name('financial-management');
        Route::post('/financial-management/adjust-funds', [FinancialManagementController::class, 'adjustFunds'])
            ->middleware('permission:finance.funds.adjust')
            ->name('financial-management.adjust-funds');
        Route::get('/budget-planning', [FinancialManagementController::class, 'budgetPlanning'])
            ->middleware('permission:finance.budget.view')
            ->name('budget-planning');
        Route::post('/budget-planning', [FinancialManagementController::class, 'storeBudget'])
            ->middleware('permission:finance.budget.manage')
            ->name('budget-planning.store');
        Route::put('/budget-planning/{allocation}', [FinancialManagementController::class, 'updateBudget'])
            ->middleware('permission:finance.budget.manage')
            ->name('budget-planning.update');
        Route::delete('/budget-planning/{allocation}', [FinancialManagementController::class, 'destroyBudget'])
            ->middleware('permission:finance.budget.manage')
            ->name('budget-planning.destroy');
        Route::get('/payment-processing', [FinancialManagementController::class, 'paymentProcessing'])
            ->middleware('permission:payment_processing.view')
            ->name('payment-processing');
        Route::get('/official-receipts', [FinancialManagementController::class, 'officialReceipts'])
            ->middleware('permission:official_receipts.view')
            ->name('official-receipts');
        Route::get('/collection-reports', [FinancialManagementController::class, 'collectionReports'])
            ->middleware('permission:collection_reports.view')
            ->name('collection-reports');
        Route::get('/transaction-history', [FinancialManagementController::class, 'transactionHistory'])
            ->middleware('permission:transaction_history.view')
            ->name('transaction-history');
        Route::get('/financial-summary', [FinancialManagementController::class, 'financialSummary'])
            ->middleware('permission:financial_summary.view')
            ->name('financial-summary');
        Route::post('/payments', [FinancialManagementController::class, 'store'])
            ->middleware('permission:finance.record')
            ->name('payments.store');
        Route::put('/payments/{payment}', [FinancialManagementController::class, 'update'])
            ->middleware('permission:finance.record')
            ->name('payments.update');
        Route::delete('/payments/{payment}', [FinancialManagementController::class, 'destroy'])
            ->middleware('permission:finance.record')
            ->name('payments.destroy');
        Route::get('/payments/export', [FinancialManagementController::class, 'exportCsv'])
            ->middleware('permission:finance.reports.export')
            ->name('payments.export');
        Route::get('/payments/{payment}/receipt', [FinancialManagementController::class, 'receipt'])
            ->middleware('permission:finance.receipts')
            ->name('payments.receipt');

        Route::get('/youth-management', [YouthController::class, 'management'])
            ->middleware('permission:youth_management.view')
            ->name('youth-management');
        Route::get('/youth-residents', [YouthController::class, 'residents'])
            ->middleware('permission:youth_residents.view')
            ->name('youth-residents');
        Route::get('/youth-programs', [YouthController::class, 'programs'])
            ->middleware('permission:youth_programs.view')
            ->name('youth-programs');
        Route::post('/youth-programs', [YouthController::class, 'storeProgram'])
            ->middleware('permission:youth.manage')
            ->name('youth-programs.store');
        Route::put('/youth-programs/{program}', [YouthController::class, 'updateProgram'])
            ->middleware('permission:youth.manage')
            ->name('youth-programs.update');
        Route::delete('/youth-programs/{program}', [YouthController::class, 'destroyProgram'])
            ->middleware('permission:youth.manage')
            ->name('youth-programs.destroy');
        Route::get('/youth-reports', [YouthController::class, 'reports'])
            ->middleware('permission:youth_reports.view')
            ->name('youth-reports');

        Route::get('/programs-projects', [ProgramsController::class, 'projects'])
            ->middleware('permission:programs.view')
            ->name('programs-projects');
        Route::post('/programs-projects', [ProgramsController::class, 'store'])
            ->middleware('permission:programs.manage')
            ->name('programs-projects.store');
        Route::put('/programs-projects/{program}', [ProgramsController::class, 'update'])
            ->middleware('permission:programs.manage')
            ->name('programs-projects.update');
        Route::delete('/programs-projects/{program}', [ProgramsController::class, 'destroy'])
            ->middleware('permission:programs.manage')
            ->name('programs-projects.destroy');

        Route::get('/committee-reports', [ProgramsController::class, 'committeeReports'])
            ->middleware('permission:committee_reports.view')
            ->name('committee-reports');
        Route::get('/committee-reports/export', [ProgramsController::class, 'committeeReportsExport'])
            ->middleware('permission:committee_reports.view')
            ->name('committee-reports.export');
        Route::get('/programs-monitoring', [ProgramsController::class, 'monitoring'])
            ->middleware('permission:programs_monitoring.view')
            ->name('programs-monitoring');

        Route::get('/reports-analytics', [ReportsController::class, 'analytics'])
            ->middleware('permission:reports_analytics.view')
            ->name('reports-analytics');

        Route::get('/reports', [ReportsController::class, 'reports'])
            ->middleware('permission:reports.view')
            ->name('reports');
        Route::get('/reports/export', [ReportsController::class, 'exportCsv'])
            ->middleware('permission:reports.view')
            ->name('reports.export');

        Route::get('/document-archive', [DocumentsController::class, 'archiveIndex'])
            ->middleware('permission:document_archive.view')
            ->name('document-archive');
        Route::get('/documents/{document}/download', [DocumentsController::class, 'download'])
            ->middleware('permission:documents.download')
            ->name('documents.download');
        Route::patch('/documents/{document}/approve', [DocumentsController::class, 'approve'])
            ->middleware('permission:documents.approve')
            ->name('documents.approve');
        Route::patch('/documents/{document}/reject', [DocumentsController::class, 'reject'])
            ->middleware('permission:documents.approve')
            ->name('documents.reject');
        Route::delete('/documents/{document}', [DocumentsController::class, 'destroy'])
            ->middleware('permission:documents.delete')
            ->name('documents.destroy');

        Route::get('/role-permissions', [RolePermissionsController::class, 'index'])
            ->middleware('permission:roles.manage')
            ->name('role-permissions');
        Route::put('/role-permissions/{role}', [RolePermissionsController::class, 'update'])
            ->middleware('permission:roles.manage')
            ->name('role-permissions.update');
        Route::patch('/role-permissions/{role}/reset', [RolePermissionsController::class, 'reset'])
            ->middleware('permission:roles.manage')
            ->name('role-permissions.reset');
        Route::patch('/role-permissions/reset-all', [RolePermissionsController::class, 'resetAll'])
            ->middleware('permission:roles.manage')
            ->name('role-permissions.reset-all');
        Route::get('/access-matrix', [AccessMatrixController::class, 'index'])
            ->middleware('permission:roles.manage')
            ->name('access-matrix');
        Route::get('/access-matrix/export', [AccessMatrixController::class, 'exportCsv'])
            ->middleware('permission:roles.manage')
            ->name('access-matrix.export');

        Route::get('/system-logs', [SystemLogsController::class, 'index'])
            ->middleware('permission:system.logs.view')
            ->name('system-logs');

        Route::get('/backup-restore', [BackupRestoreController::class, 'index'])
            ->middleware('permission:system.backup')
            ->name('backup-restore');
        Route::post('/backup-restore/create', [BackupRestoreController::class, 'create'])
            ->middleware('permission:system.backup')
            ->name('backup-restore.create');
        Route::get('/backup-restore/{name}/download', [BackupRestoreController::class, 'download'])
            ->middleware('permission:system.backup')
            ->where('name', '.*')
            ->name('backup-restore.download');
        Route::delete('/backup-restore/{name}', [BackupRestoreController::class, 'destroy'])
            ->middleware('permission:system.backup')
            ->where('name', '.*')
            ->name('backup-restore.destroy');
        Route::post('/backup-restore/{name}/restore', [BackupRestoreController::class, 'restore'])
            ->middleware('permission:backup.restore')
            ->where('name', '.*')
            ->name('backup-restore.restore');

        Route::get('/system-settings', [SystemSettingsController::class, 'index'])
            ->middleware('permission:system.settings')
            ->name('system-settings');
        Route::put('/system-settings', [SystemSettingsController::class, 'update'])
            ->middleware('permission:system.settings')
            ->name('system-settings.update');
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

        Route::get('/upload-documents', [DocumentsController::class, 'uploadIndex'])
            ->middleware('permission:documents.upload')
            ->name('upload-documents');
        Route::post('/upload-documents', [DocumentsController::class, 'store'])
            ->middleware('permission:documents.upload')
            ->name('upload-documents.store');
        Route::get('/documents/{document}/download', [DocumentsController::class, 'download'])
            ->middleware('permission:documents.upload')
            ->name('documents.download');
        Route::delete('/documents/{document}', [DocumentsController::class, 'destroy'])
            ->middleware('permission:documents.upload')
            ->name('documents.destroy');

        Route::get('/data-quality', [DataQualityController::class, 'index'])
            ->middleware('permission:data.validate')
            ->name('data-quality');
        Route::patch('/data-quality/residents/{resident}/archive', [DataQualityController::class, 'archive'])
            ->middleware('permission:data.archive')
            ->name('data-quality.archive');
    });
