<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class RectifyShiftSwapRequest extends DataFormRequest
{
    public function rules(): array
    {
        return [
            'administrativeKind' => ['required', Rule::in(['annulment', 'rectification'])],
            'administrativeReason' => ['required', 'string', 'min:10', 'max:2000'],
            'administrativeEvidence' => ['required', 'string', 'min:10', 'max:4000'],
            'administrativeConfirmed' => ['required', 'accepted'],
            'administrativeSnapshot' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }

    public function attributes(): array
    {
        return ['administrativeKind' => 'tipo de registro', 'administrativeReason' => 'justificativa', 'administrativeEvidence' => 'evidências', 'administrativeConfirmed' => 'confirmação explícita', 'administrativeSnapshot' => 'revisão administrativa'];
    }
}
