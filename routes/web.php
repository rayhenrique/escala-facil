<?php

use App\Http\Controllers\ClientErrorController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\Manager\IssueAttachmentController;
use App\Http\Controllers\Manager\ReportPdfController;
use App\Http\Controllers\Manager\ShiftSpreadsheetController;
use App\Http\Controllers\Manager\WallRosterPdfController;
use App\Http\Controllers\SwapReceiptPdfController;
use App\Http\Middleware\ValidateOperationalQuery;
use App\Livewire\HelpCenter;
use App\Livewire\Manager\AuditHistory;
use App\Livewire\Manager\CategoryManager;
use App\Livewire\Manager\Dashboard;
use App\Livewire\Manager\IssueReports;
use App\Livewire\Manager\Reports;
use App\Livewire\Manager\SectorManager;
use App\Livewire\Manager\ShiftManager;
use App\Livewire\Manager\SwapApprovals;
use App\Livewire\Manager\UserManager;
use App\Livewire\MonthlyCalendar;
use App\Livewire\NotificationsCenter;
use App\Livewire\Professional\MySchedule;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : view('landing');
})->name('home');

Route::view('/landing', 'landing')->name('landing');

Route::get('dashboard', DashboardRedirectController::class)
    ->middleware('auth')
    ->name('dashboard');

Route::get('help', HelpCenter::class)
    ->middleware('auth')
    ->name('help');

Route::get('notifications', NotificationsCenter::class)
    ->middleware('auth')
    ->name('notifications');

Route::get('swaps/{swap}/receipt/pdf', SwapReceiptPdfController::class)
    ->middleware('auth')
    ->name('swaps.receipt.pdf');

Route::post('support/client-error', ClientErrorController::class)
    ->middleware(['auth', 'throttle:10,1'])
    ->name('support.client-error');

Route::middleware(['auth', 'professional', ValidateOperationalQuery::class])
    ->prefix('professional')
    ->name('professional.')
    ->group(function () {
        Route::get('dashboard', MySchedule::class)->name('dashboard');
        Route::get('calendar', MonthlyCalendar::class)->name('calendar');
        Route::get('reports', Reports::class)->name('reports');
        Route::get('reports/pdf', ReportPdfController::class)->name('reports.pdf');
        Route::get('reports/wall', WallRosterPdfController::class)->name('reports.wall');
    });

Route::middleware(['auth', 'manager', ValidateOperationalQuery::class])
    ->prefix('manager')
    ->name('manager.')
    ->group(function () {
        Route::get('dashboard', Dashboard::class)->name('dashboard');
        Route::get('calendar', MonthlyCalendar::class)->name('calendar');
        Route::get('shifts', ShiftManager::class)->name('shifts');
        Route::get('shifts/template', [ShiftSpreadsheetController::class, 'template'])->name('shifts.template');
        Route::get('shifts/export', [ShiftSpreadsheetController::class, 'export'])->name('shifts.export');
        Route::get('reports', Reports::class)->name('reports');
        Route::get('reports/pdf', ReportPdfController::class)->name('reports.pdf');
        Route::get('reports/wall', WallRosterPdfController::class)->name('reports.wall');
        Route::get('swaps', SwapApprovals::class)->name('swaps');
        Route::get('sectors', SectorManager::class)->name('sectors');
        Route::get('categories', CategoryManager::class)->name('categories');
        Route::get('users', UserManager::class)->name('users');
        Route::get('audit', AuditHistory::class)->name('audit');
        Route::get('issue-reports', IssueReports::class)->name('issue-reports');
        Route::get('issue-reports/{report}/attachment', IssueAttachmentController::class)->name('issue-reports.attachment');
    });

require __DIR__.'/auth.php';
