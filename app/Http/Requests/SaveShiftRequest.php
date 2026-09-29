<?php

namespace App\Http\Requests;

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveShiftRequest extends DataFormRequest
{
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
            'end_time' => ['required', 'date_format:H:i', 'different:start_time'],
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
                if (! $this->filled('user_id')) {
                    return;
                }

                $professional = User::query()->find($this->integer('user_id'));

                if (! $professional) {
                    return;
                }

                if ($professional->role !== UserRole::Professional) {
                    $validator->errors()->add('user_id', 'Selecione um profissional para o plantão.');
                }

                if ($professional->category_id !== $this->integer('category_id')
                    || $professional->sector_id !== $this->integer('sector_id')) {
                    $validator->errors()->add('user_id', 'O profissional deve pertencer à categoria e ao setor do plantão.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'date.after_or_equal' => 'A data do plantão não pode estar no passado.',
            'end_time.different' => 'O horário final deve ser diferente do horário inicial.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'date' => 'data',
            'start_time' => 'horário inicial',
            'end_time' => 'horário final',
            'sector_id' => 'setor',
            'category_id' => 'categoria',
            'user_id' => 'profissional',
            'status' => 'status',
        ];
    }
}
