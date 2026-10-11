<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Middleware\InvitationPrivacy;
use App\Livewire\Auth\ActivateAccount;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use Illuminate\Support\Facades\Route;

Route::get('activate-account', ActivateAccount::class)->middleware(['throttle:60,1', InvitationPrivacy::class, 'guest'])->name('account.activate');

Route::middleware('guest')->group(function () {
    Route::get('login', Login::class)->name('login');
    Route::get('forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::post('logout', LogoutController::class)
    ->middleware('auth')
    ->name('logout');
