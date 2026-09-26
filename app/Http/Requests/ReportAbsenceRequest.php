<?php

namespace App\Http\Requests;

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Shift;
use Illuminate\Validation\Validator;

class ReportAbsenceRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                $shift = Shift::query()->find($this->integer('shift_id'));

                if (! $user || ! $shift) {
                    return;
                }

                if ($user->role !== UserRole::Professional || $shift->user_id !== $user->id) {
                    $validator->errors()->add('shift_id', 'Você só pode informar ausência em um plantão atribuído a você.');
                }

                if ($shift->date->isBefore(today()) || $shift->status !== ShiftStatus::Scheduled) {
                    $validator->errors()->add('shift_id', 'A ausência só pode ser informada para um plantão atual ou futuro agendado.');
                }

                if (Absence::query()->where('shift_id', $shift->id)->exists()) {
                    $validator->errors()->add('shift_id', 'Já existe uma ausência registrada para este plantão.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.min' => 'Explique a ausência com pelo menos 10 caracteres.',
            'attachment.mimes' => 'O anexo deve ser PDF, JPG ou PNG.',
            'attachment.max' => 'O anexo deve ter no máximo 5 MB.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'shift_id' => 'plantão',
            'reason' => 'justificativa',
            'attachment' => 'anexo',
        ];
    }
}
