<?php

namespace App\Http\Requests;

class ImportShiftsRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.mimes' => 'O arquivo deve estar no formato XLSX. Baixe o modelo padrão.',
            'file.max' => 'O arquivo deve ter no máximo 5 MB.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'file' => 'arquivo',
        ];
    }
}
