<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ManagerSwapFiltersRequest extends OperationalFiltersRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'listView' => ['sometimes', 'required', Rule::in(['pending', 'history'])],
            'sort' => ['sometimes', 'required', Rule::in(['oldest', 'newest'])],
            'requesterSearch' => ['nullable', 'string', 'max:100'],
            'substituteSearch' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'listView.*' => 'Selecione Aguardando decisão ou Histórico de trocas.',
            'sort.*' => 'Selecione uma ordenação válida.',
            'requesterSearch.*' => 'Use até 100 caracteres para pesquisar o solicitante.',
            'substituteSearch.*' => 'Use até 100 caracteres para pesquisar o substituto.',
        ];
    }
}
