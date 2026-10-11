<?php

namespace App\Http\Requests;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Models\User;
use App\Support\ShiftAssignmentAnalysis;
use App\Support\ShiftPeriod;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveShiftRequest extends DataFormRequest
{
    public function validatePayload(array $payload, ?User $user = null): array
    {
        $validated = parent::validatePayload($payload, $user);
        $period = new ShiftPeriod($validated['date'], $validated['start_time'], $validated['end_time'], $validated['end_date'] ?? null);
        $validated['end_date'] = $period->end->format('Y-m-d');

        return $validated;
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'sector_id' => ['required', 'integer', 'exists:sectors,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', Rule::enum(ShiftStatus::class)],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['date', 'start_time', 'end_time', 'end_date'])) {
                    return;
                }

                if (! $this->filled('end_date') && $this->input('start_time') === $this->input('end_time')) {
                    $validator->errors()->add('end_time', 'Para um plantão de 24h, informe a data de término no dia seguinte.');

                    return;
                }

                $period = new ShiftPeriod($this->input('date'), $this->input('start_time'), $this->input('end_time'), $this->filled('end_date') ? $this->input('end_date') : null);
                if ($period->durationMinutes < 1 || $period->durationMinutes > 1440) {
                    $validator->errors()->add('end_date', 'Informe um término após o início. Este cadastro representa plantões de 1 minuto até 24h.');
                }
            },
            function (Validator $validator): void {
                if (! $this->filled('user_id')) {
                    return;
                }

                $professional = User::query()->find($this->integer('user_id'));

                if (! $professional) {
                    return;
                }

                $candidate = new Shift(['sector_id' => $this->integer('sector_id'), 'category_id' => $this->integer('category_id')]);
                foreach (app(ShiftAssignmentAnalysis::class)->compatibility($professional, $candidate) as $block) {
                    $validator->errors()->add('user_id', $block);
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'date.after_or_equal' => 'A data do plantão não pode estar no passado.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'date' => 'data',
            'start_time' => 'horário inicial',
            'end_time' => 'horário final',
            'end_date' => 'data de término',
            'sector_id' => 'setor',
            'category_id' => 'categoria',
            'user_id' => 'profissional',
            'status' => 'status',
        ];
    }
}
