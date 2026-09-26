<?php

namespace App\Livewire\Auth;

use App\Livewire\Forms\LoginForm;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest', ['title' => 'Entrar'])]
class Login extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->form->validate();
        $this->form->authenticate();
        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
