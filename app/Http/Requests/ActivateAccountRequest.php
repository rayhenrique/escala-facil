<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;

class ActivateAccountRequest extends DataFormRequest
{
    public function rules(): array
    {
        return ['password' => ['required', 'string', 'confirmed', Password::defaults()]];
    }

    public function attributes(): array
    {
        return ['password' => 'senha'];
    }
}
