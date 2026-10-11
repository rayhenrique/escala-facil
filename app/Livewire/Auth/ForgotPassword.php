<?php

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.guest', ['title' => 'Recuperar senha'])]
class ForgotPassword extends Component
{
    #[Validate('required|string|lowercase|email', as: 'e-mail')]
    public string $email = '';

    public function sendResetLink(): void
    {
        $this->validate();

        $key = 'password-recovery:'.hash('sha256', (string) request()->ip());
        if (! RateLimiter::tooManyAttempts($key, 5)) {
            RateLimiter::hit($key, 60);
            Password::sendResetLink(['email' => $this->email, 'requires_activation' => false]);
        }
        session()->flash('status', 'Se houver uma conta ativa para este e-mail, você receberá um link para redefinir a senha.');
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password');
    }
}
