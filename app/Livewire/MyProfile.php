<?php

namespace App\Livewire;

use App\Models\User;
use App\Support\UiFeedback;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use SensitiveParameter;

#[Layout('layouts.app', ['title' => 'Meu Perfil'])]
class MyProfile extends Component
{
    public string $name = '';

    public function mount(): void
    {
        $this->name = $this->account()->name;
    }

    public function saveName(): void
    {
        $account = $this->account();
        $this->name = trim($this->name);
        $this->validateOnly('name', ['name' => ['required', 'string', 'max:255']]);
        $account->update(['name' => $this->name]);

        UiFeedback::success('Nome atualizado com sucesso.');
        $this->redirectRoute('profile', navigate: true);
    }

    public function changePassword(#[SensitiveParameter] array $credentials): void
    {
        $account = $this->account();

        $validated = Validator::make($credentials, [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ], attributes: ['current_password' => 'senha atual', 'password' => 'nova senha'])->validate();

        $updated = DB::transaction(function () use ($account, $validated): User {
            $locked = User::query()->lockForUpdate()->findOrFail($account->id);
            if (! Hash::check($validated['current_password'], $locked->getAuthPassword())) {
                throw ValidationException::withMessages(['current_password' => __('validation.current_password')]);
            }

            $locked->update(['password' => $validated['password']]);

            return $locked;
        });
        auth()->setUser($updated);
        $this->resetValidation();
        $this->dispatch('profile-password-changed');
        UiFeedback::success('Senha alterada com sucesso.');
    }

    private function account(): User
    {
        $account = auth()->user();
        abort_unless($account, 403);
        $this->authorize('updateOwn', $account);

        return $account;
    }

    public function render(): View
    {
        return view('livewire.my-profile', [
            'account' => $this->account()->loadMissing(['sector', 'category']),
        ]);
    }
}
