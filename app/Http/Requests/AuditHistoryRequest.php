<?php

namespace App\Http\Requests;

use App\Models\OperationalAuditEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditHistoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'entity' => ['nullable', 'string', Rule::in(array_keys(OperationalAuditEvent::ENTITIES))],
            'record' => ['nullable', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'action' => ['nullable', 'string', Rule::in(array_keys(OperationalAuditEvent::ACTIONS))],
            'author' => ['nullable', 'string', 'max:100'],
            'dateFrom' => ['nullable', 'date_format:Y-m-d'],
            'dateTo' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('dateFrom'), 'after_or_equal:dateFrom')],
        ];
    }

    public function messages(): array
    {
        return [
            'entity.*' => 'Selecione um tipo de registro válido.', 'record.*' => 'Informe um ID de registro válido.',
            'action.*' => 'Selecione uma ação válida.', 'author.*' => 'Use até 100 caracteres para pesquisar o autor.',
            'dateFrom.*' => 'Informe uma data inicial válida.', 'dateTo.*' => 'Informe uma data final válida, igual ou posterior à inicial.',
        ];
    }
}
