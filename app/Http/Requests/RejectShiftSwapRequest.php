<?php

namespace App\Http\Requests;

class RejectShiftSwapRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['rejectionReason' => ['required', 'string', 'min:3', 'max:1000']];
    }

    public function attributes(): array
    {
        return ['rejectionReason' => 'justificativa'];
    }
}
