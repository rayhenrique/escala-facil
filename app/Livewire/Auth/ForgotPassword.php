<?php

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
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

        $status = Password::sendResetLink(['email' => $this->email]);

        if ($status !== Password::ResetLinkSent) {
            $this->addError('email', __($status));

            return;
        }

        session()->flash('status', __($status));
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password');
    }
}
