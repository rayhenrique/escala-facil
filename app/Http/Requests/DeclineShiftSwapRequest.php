<?php

namespace App\Http\Requests;

class DeclineShiftSwapRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['declineReason' => ['nullable', 'string', 'max:1000']];
    }

    public function attributes(): array
    {
        return ['declineReason' => 'motivo da recusa'];
    }
}
