<?php

namespace App\Livewire\Auth;

use App\Actions\Accounts\ActivateAccountAction;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.guest', ['title' => 'Ativar conta'])]
class ActivateAccount extends Component
{
    #[Locked]
    public string $grant = '';

    public function mount(): void
    {
        $grant = request()->query('grant');
        $this->grant = is_string($grant) && preg_match('/^[a-f0-9]{32}$/', $grant) ? $grant : '';
    }

    public string $password = '';

    public string $password_confirmation = '';

    public function activate(ActivateAccountAction $action): void
    {
        abort_if(auth()->check(), 403);
        $key = 'account-activation:'.hash('sha256', (string) request()->ip());
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('invitation', 'Aguarde um minuto antes de tentar novamente.');

            return;
        }
        RateLimiter::hit($key, 60);
        $action->execute((string) session('account_activation_grants.'.$this->grant, ''), $this->only(['password', 'password_confirmation']));
        session()->forget('account_activation_grants.'.$this->grant);
        session()->regenerate();
        $this->reset(['password', 'password_confirmation']);
        session()->flash('status', 'Sua conta foi ativada. Entre com seu e-mail e a senha que você criou.');
        $this->redirectRoute('login', navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.activate-account');
    }
}
