<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class SaveSectorRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sector_id' => ['nullable', 'integer', 'exists:sectors,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sectors', 'name')->ignore($this->integer('sector_id')),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nome do setor',
        ];
    }
}
