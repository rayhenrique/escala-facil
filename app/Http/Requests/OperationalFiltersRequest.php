<?php

namespace App\Http\Requests;

use App\Enums\ShiftSwapStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OperationalFiltersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'filter' => ['nullable', Rule::in(['scheduled', 'absence_reported', 'uncovered', 'uncovered_month'])],
            'swapStatusFilter' => ['nullable', Rule::enum(ShiftSwapStatus::class)],
            'searchProfessional' => ['nullable', 'string', 'max:250'],
            'search' => ['nullable', 'string', 'max:100'],
            'filterSectorId' => ['nullable', 'integer', 'min:1', 'exists:sectors,id'],
            'filterCategoryId' => ['nullable', 'integer', 'min:1', 'exists:categories,id'],
            'dateFrom' => ['nullable', 'date_format:Y-m-d'],
            'dateTo' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('dateFrom'), 'after_or_equal:dateFrom')],
        ];
    }

    public function messages(): array
    {
        return [
            'filter.*' => 'Selecione uma situação válida.',
            'swapStatusFilter.*' => 'Selecione uma situação válida.',
            'searchProfessional.max' => 'Use até 250 caracteres na pesquisa.',
            'search.max' => 'Use até 100 caracteres na pesquisa.',
            'filterSectorId.*' => 'Selecione um setor válido.',
            'filterCategoryId.*' => 'Selecione uma categoria válida.',
            'dateFrom.*' => 'Informe uma data inicial válida.',
            'dateTo.date_format' => 'Informe uma data final válida.',
            'dateTo.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
        ];
    }
}
