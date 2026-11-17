<?php

use App\Http\Controllers\AbsenceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CollectiveLeaveController;
use App\Http\Controllers\CustomerGroupController;
use App\Http\Controllers\DebtorController;
use App\Http\Controllers\DefaultServiceController;
use App\Http\Controllers\DisciplineController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EmployeeAccessController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeImportExportController;
use App\Http\Controllers\EmploymentContractController;
use App\Http\Controllers\HoursController;
use App\Http\Controllers\HoursReportController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LeaveBalanceController;
use App\Http\Controllers\LeaveReportController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationImportExportController;
use App\Http\Controllers\OrganizationPersonController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PersonImportExportController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectImportExportController;
use App\Http\Controllers\ProjectLookupController;
use App\Http\Controllers\ProjectServiceController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\UserGroupController;
use App\Http\Controllers\WorkScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
    Route::get('invitation/{token}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('invitation/{token}', [InvitationController::class, 'store'])->name('invitation.store');
});

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/organizations');
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // Companies
    Route::get('organizations/export', [OrganizationImportExportController::class, 'export'])->name('organizations.export');
    Route::get('organizations/import', [OrganizationImportExportController::class, 'form'])->name('organizations.import');
    Route::post('organizations/import', [OrganizationImportExportController::class, 'import'])->name('organizations.import.store');
    Route::get('organizations/import/template', [OrganizationImportExportController::class, 'template'])->name('organizations.import.template');
    Route::patch('organizations/{organization}/toggle-active', [OrganizationController::class, 'toggleActive'])->name('organizations.toggle-active');
    Route::post('organizations/{organization}/notes', [NoteController::class, 'storeForOrganization'])->name('organizations.notes.store');
    Route::resource('organizations', OrganizationController::class)->except('destroy');

    Route::patch('preferences/organization-charts', [PreferenceController::class, 'organizationCharts'])->name('preferences.organization-charts');
    Route::get('debtors', [DebtorController::class, 'index'])->name('debtors.index');
    Route::resource('customer-groups', CustomerGroupController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['customer-groups' => 'customerGroup']);

    // People
    Route::get('people/export', [PersonImportExportController::class, 'export'])->name('people.export');
    Route::get('people/import', [PersonImportExportController::class, 'form'])->name('people.import');
    Route::post('people/import', [PersonImportExportController::class, 'import'])->name('people.import.store');
    Route::get('people/import/template', [PersonImportExportController::class, 'template'])->name('people.import.template');
    Route::patch('people/{person}/toggle-active', [PersonController::class, 'toggleActive'])->name('people.toggle-active');
    Route::post('people/{person}/notes', [NoteController::class, 'storeForPerson'])->name('people.notes.store');
    Route::resource('people', PersonController::class)->except('destroy')->parameters(['people' => 'person']);

    // Company ↔ person links
    Route::post('links', [OrganizationPersonController::class, 'store'])->name('links.store');
    Route::patch('links/{organization}/{person}', [OrganizationPersonController::class, 'update'])->name('links.update');
    Route::delete('links/{organization}/{person}', [OrganizationPersonController::class, 'destroy'])->name('links.destroy');

    // Employees
    Route::get('employees/export', [EmployeeImportExportController::class, 'export'])->name('employees.export');
    Route::get('employees/import', [EmployeeImportExportController::class, 'form'])->name('employees.import');
    Route::post('employees/import', [EmployeeImportExportController::class, 'import'])->name('employees.import.store');
    Route::get('employees/import/template', [EmployeeImportExportController::class, 'template'])->name('employees.import.template');
    Route::resource('employees', EmployeeController::class)->except('destroy');

    Route::prefix('employees/{employee}')->name('employees.')->scopeBindings()->group(function () {
        Route::post('offboard', [EmployeeController::class, 'offboard'])->name('offboard');
        Route::patch('reactivate', [EmployeeController::class, 'reactivate'])->name('reactivate');
        Route::post('invite', [EmployeeAccessController::class, 'invite'])->name('invite');
        Route::patch('access', [EmployeeAccessController::class, 'update'])->name('access');
        Route::post('contracts', [EmploymentContractController::class, 'store'])->name('contracts.store');
        Route::patch('contracts/{contract}', [EmploymentContractController::class, 'update'])->name('contracts.update');
        Route::delete('contracts/{contract}', [EmploymentContractController::class, 'destroy'])->name('contracts.destroy');
        Route::post('schedules', [WorkScheduleController::class, 'store'])->name('schedules.store');
        Route::delete('schedules/{schedule}', [WorkScheduleController::class, 'destroy'])->name('schedules.destroy');
        Route::post('leave', [LeaveRequestController::class, 'store'])->name('leave.store');
        Route::post('balance', [LeaveBalanceController::class, 'store'])->name('balance.store');
        Route::post('documents', [DocumentController::class, 'storeForEmployee'])->name('documents.store');
        Route::get('photo', [EmployeeController::class, 'photo'])->name('photo');
        Route::post('notes', [NoteController::class, 'storeForEmployee'])->name('notes.store');
    });

    Route::get('leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
    Route::patch('leave-requests/{leaveRequest}/decide', [LeaveRequestController::class, 'decide'])->name('leave-requests.decide');
    Route::patch('leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])->name('leave-requests.cancel');
    Route::get('collective-leave', [CollectiveLeaveController::class, 'index'])->name('collective-leave.index');
    Route::post('collective-leave', [CollectiveLeaveController::class, 'store'])->name('collective-leave.store');
    Route::resource('absences', AbsenceController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('reports/leave', [LeaveReportController::class, 'index'])->name('reports.leave');
    Route::get('reports/leave/export', [LeaveReportController::class, 'export'])->name('reports.leave.export');
    Route::resource('teams', TeamController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('disciplines', DisciplineController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('user-groups', UserGroupController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['user-groups' => 'group']);
    Route::patch('users/{user}/group', [UserGroupController::class, 'assign'])->name('users.group');

    Route::post('organizations/{organization}/documents', [DocumentController::class, 'storeForOrganization'])->name('organizations.documents.store');
    Route::post('people/{person}/documents', [DocumentController::class, 'storeForPerson'])->name('people.documents.store');
    Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::get('notes/{note}/attachment', [NoteController::class, 'attachment'])->name('notes.attachment');

    // Invoicing
    Route::prefix('invoicing')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('export', [InvoiceController::class, 'export'])->name('invoices.export');
        Route::get('report', [InvoiceController::class, 'report'])->name('invoices.report');
        Route::get('projects/{project}', [InvoiceController::class, 'proposal'])->name('invoices.proposal');
        Route::post('projects/{project}/invoices', [InvoiceController::class, 'storeFromProposal'])->name('invoices.from-proposal');
        Route::post('projects/{project}/write-off', [InvoiceController::class, 'writeOff'])->name('invoices.write-off');
        Route::post('send-many', [InvoiceController::class, 'sendMany'])->name('invoices.send-many');
        Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
        Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
        Route::post('invoices/{invoice}/lines', [InvoiceController::class, 'storeLine'])->name('invoices.lines.store');
        Route::put('invoices/{invoice}/lines/{line}', [InvoiceController::class, 'updateLine'])->name('invoices.lines.update');
        Route::delete('invoices/{invoice}/lines/{line}', [InvoiceController::class, 'destroyLine'])->name('invoices.lines.destroy');
        Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
        Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'storePayment'])->name('invoices.payments.store');
        Route::post('invoices/{invoice}/remind', [InvoiceController::class, 'remind'])->name('invoices.remind');
        Route::patch('invoices/{invoice}/reminders', [InvoiceController::class, 'toggleReminders'])->name('invoices.reminders');
        Route::post('invoices/{invoice}/credit', [InvoiceController::class, 'credit'])->name('invoices.credit');
    });

    // Projects
    Route::get('projects/export', [ProjectImportExportController::class, 'export'])->name('projects.export');
    Route::get('projects/import', [ProjectImportExportController::class, 'form'])->name('projects.import');
    Route::post('projects/import', [ProjectImportExportController::class, 'import'])->name('projects.import.store');
    Route::get('projects/import/template', [ProjectImportExportController::class, 'template'])->name('projects.import.template');
    Route::resource('projects', ProjectController::class)->only(['index', 'store', 'show', 'update']);

    Route::prefix('projects/{project}')->name('projects.')->scopeBindings()->group(function () {
        Route::post('close', [ProjectController::class, 'close'])->name('close');
        Route::patch('reopen', [ProjectController::class, 'reopen'])->name('reopen');
        Route::post('copy', [ProjectController::class, 'copy'])->name('copy');
        Route::get('services/create', [ProjectServiceController::class, 'create'])->name('services.create');
        Route::post('services', [ProjectServiceController::class, 'store'])->name('services.store');
        Route::get('services/{service}/edit', [ProjectServiceController::class, 'edit'])->name('services.edit');
        Route::put('services/{service}', [ProjectServiceController::class, 'update'])->name('services.update');
        Route::delete('services/{service}', [ProjectServiceController::class, 'destroy'])->name('services.destroy');
        Route::patch('services/{service}/toggle-active', [ProjectServiceController::class, 'toggleActive'])->name('services.toggle-active');
        Route::patch('services/{service}/progress', [ProjectServiceController::class, 'progress'])->name('services.progress');
        Route::patch('services/{service}/move', [ProjectServiceController::class, 'move'])->name('services.move');
        Route::post('hours', [TimeEntryController::class, 'store'])->name('hours.store');
        Route::delete('hours/{timeEntry}', [TimeEntryController::class, 'destroy'])->name('hours.destroy');
        Route::post('notes', [NoteController::class, 'storeForProject'])->name('notes.store');
        Route::post('documents', [DocumentController::class, 'storeForProject'])->name('documents.store');
    });

    // Hours
    Route::get('hours', [HoursController::class, 'index'])->name('hours.index');
    Route::get('hours/approvals', [HoursController::class, 'approvals'])->name('hours.approvals');
    Route::get('hours/week', [HoursController::class, 'week'])->name('hours.week');
    Route::post('hours/week', [HoursController::class, 'saveWeek'])->name('hours.week.save');
    Route::post('hours/week/submit', [HoursController::class, 'submitWeek'])->name('hours.week.submit');
    Route::post('hours/week/copy', [HoursController::class, 'copyWeek'])->name('hours.week.copy');
    Route::get('hours/calendar', [HoursController::class, 'calendar'])->name('hours.calendar');
    Route::get('hours/report', [HoursReportController::class, 'index'])->name('hours.report');
    Route::get('hours/report/export', [HoursReportController::class, 'export'])->name('hours.report.export');
    Route::post('hours/bulk', [HoursController::class, 'bulk'])->name('hours.bulk');
    Route::post('hours', [HoursController::class, 'store'])->name('hours.store');
    Route::put('hours/{entry}', [HoursController::class, 'update'])->name('hours.update');
    Route::delete('hours/{entry}', [HoursController::class, 'destroy'])->name('hours.destroy');
    Route::post('hours/{entry}/duplicate', [HoursController::class, 'duplicate'])->name('hours.duplicate');
    Route::post('hours/{entry}/split', [HoursController::class, 'split'])->name('hours.split');

    // Service catalogue
    Route::get('services/export', [DefaultServiceController::class, 'export'])->name('services.export');
    Route::get('services/import', [DefaultServiceController::class, 'importForm'])->name('services.import');
    Route::post('services/import', [DefaultServiceController::class, 'import'])->name('services.import.store');
    Route::get('services/import/template', [DefaultServiceController::class, 'template'])->name('services.import.template');
    Route::patch('services/{service}/toggle-blocked', [DefaultServiceController::class, 'toggleBlocked'])->name('services.toggle-blocked');
    Route::resource('services', DefaultServiceController::class)->except(['show'])->parameters(['services' => 'service']);

    Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::patch('purchases/{purchase}/process', [PurchaseController::class, 'process'])->name('purchases.process');
    Route::delete('purchases/{purchase}', [PurchaseController::class, 'destroy'])->name('purchases.destroy');

    Route::get('project-settings', [ProjectLookupController::class, 'index'])->name('project-settings.index');
    Route::post('project-settings/{type}', [ProjectLookupController::class, 'store'])->name('project-settings.store');
    Route::put('project-settings/{type}/{id}', [ProjectLookupController::class, 'update'])->name('project-settings.update');
    Route::delete('project-settings/{type}/{id}', [ProjectLookupController::class, 'destroy'])->name('project-settings.destroy');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::delete('notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
});
