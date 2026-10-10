<?php

namespace App\Http\Requests;

class ReallocateShiftRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reallocationUserId' => ['required', 'integer', 'exists:users,id']];
    }

    public function attributes(): array
    {
        return ['reallocationUserId' => 'profissional'];
    }
}
