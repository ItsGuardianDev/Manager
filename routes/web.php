<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationImportExportController;
use App\Http\Controllers\OrganizationPersonController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PersonImportExportController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
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

    Route::delete('notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
});
