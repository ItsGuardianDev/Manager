<?php

use App\Http\Controllers\AbsenceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CollectiveLeaveController;
use App\Http\Controllers\DisciplineController;
use App\Http\Controllers\EmployeeAccessController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\EmployeeImportExportController;
use App\Http\Controllers\EmployeeRateController;
use App\Http\Controllers\EmploymentContractController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LeaveBalanceController;
use App\Http\Controllers\LeaveReportController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationImportExportController;
use App\Http\Controllers\OrganizationPersonController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PersonImportExportController;
use App\Http\Controllers\TeamController;
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
        Route::post('rates', [EmployeeRateController::class, 'store'])->name('rates.store');
        Route::delete('rates/{rate}', [EmployeeRateController::class, 'destroy'])->name('rates.destroy');
        Route::post('leave', [LeaveRequestController::class, 'store'])->name('leave.store');
        Route::post('balance', [LeaveBalanceController::class, 'store'])->name('balance.store');
        Route::post('documents', [EmployeeDocumentController::class, 'store'])->name('documents.store');
        Route::get('documents/{document}', [EmployeeDocumentController::class, 'show'])->name('documents.show');
        Route::delete('documents/{document}', [EmployeeDocumentController::class, 'destroy'])->name('documents.destroy');
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

    Route::delete('notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
});
