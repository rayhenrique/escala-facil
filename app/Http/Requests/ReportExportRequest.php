<?php

namespace App\Http\Requests;

use App\Enums\ShiftSwapStatus;
use App\Models\Shift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewCalendar', Shift::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m', 'regex:/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/'],
            'sector_id' => ['nullable', 'integer', 'min:1', 'exists:sectors,id'],
            'category_id' => ['nullable', 'integer', 'min:1', 'exists:categories,id'],
            'searchProfessional' => ['nullable', 'string', 'max:250'],
            'swapStatusFilter' => ['nullable', Rule::enum(ShiftSwapStatus::class)],
        ];
    }
}
