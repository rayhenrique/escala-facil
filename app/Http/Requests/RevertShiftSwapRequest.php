<?php

namespace App\Http\Requests;

class RevertShiftSwapRequest extends DataFormRequest
{
    public function rules(): array
    {
        return ['reversionReason' => ['required', 'string', 'min:3', 'max:1000'],
            'reversionSnapshot' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']];
    }

    public function attributes(): array
    {
        return ['reversionReason' => 'justificativa administrativa', 'reversionSnapshot' => 'prévia da reversão'];
    }
}
