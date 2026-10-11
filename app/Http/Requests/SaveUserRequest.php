<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class SaveUserRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $editing = $this->filled('user_id');

        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->integer('user_id')),
            ],
            'role' => ['required', Rule::enum(UserRole::class)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'sector_id' => ['nullable', 'integer', 'exists:sectors,id'],
            'password' => [$editing ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $existing = $this->filled('user_id') ? User::find($this->integer('user_id')) : null;
                if ($existing?->requires_activation && ($this->filled('password') || $this->input('role') !== UserRole::Professional->value)) {
                    $validator->errors()->add('role', 'A conta aguarda ativação por convite. Preserve o perfil Profissional; a senha deve ser definida pelo próprio profissional.');
                }
                if ($this->input('role') !== UserRole::Professional->value) {
                    return;
                }

                if (! $this->filled('category_id')) {
                    $validator->errors()->add('category_id', 'A categoria é obrigatória para profissionais.');
                }

                if (! $this->filled('sector_id')) {
                    $validator->errors()->add('sector_id', 'O setor é obrigatório para profissionais.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'email' => 'e-mail',
            'role' => 'perfil',
            'category_id' => 'categoria',
            'sector_id' => 'setor',
            'password' => 'senha',
        ];
    }
}
