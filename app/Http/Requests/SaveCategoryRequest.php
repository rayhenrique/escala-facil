<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class SaveCategoryRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->ignore($this->integer('category_id')),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nome da categoria',
        ];
    }
}
