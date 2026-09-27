<?php

use App\Http\Controllers\DashboardRedirectController;
use App\Livewire\HelpCenter;
use App\Livewire\Manager\CategoryManager;
use App\Livewire\Manager\Dashboard;
use App\Livewire\Manager\SectorManager;
use App\Livewire\Manager\ShiftManager;
use App\Livewire\Manager\SwapApprovals;
use App\Livewire\Manager\UserManager;
use App\Livewire\MonthlyCalendar;
use App\Livewire\NotificationsCenter;
use App\Livewire\Professional\MySchedule;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('dashboard', DashboardRedirectController::class)
    ->middleware('auth')
    ->name('dashboard');

Route::get('help', HelpCenter::class)
    ->middleware('auth')
    ->name('help');

Route::get('notifications', NotificationsCenter::class)
    ->middleware('auth')
    ->name('notifications');

Route::middleware(['auth', 'professional'])
    ->prefix('professional')
    ->name('professional.')
    ->group(function () {
        Route::get('dashboard', MySchedule::class)->name('dashboard');
        Route::get('calendar', MonthlyCalendar::class)->name('calendar');
    });

Route::middleware(['auth', 'manager'])
    ->prefix('manager')
    ->name('manager.')
    ->group(function () {
        Route::get('dashboard', Dashboard::class)->name('dashboard');
        Route::get('calendar', MonthlyCalendar::class)->name('calendar');
        Route::get('shifts', ShiftManager::class)->name('shifts');
        Route::get('swaps', SwapApprovals::class)->name('swaps');
        Route::get('sectors', SectorManager::class)->name('sectors');
        Route::get('categories', CategoryManager::class)->name('categories');
        Route::get('users', UserManager::class)->name('users');
    });

require __DIR__.'/auth.php';
