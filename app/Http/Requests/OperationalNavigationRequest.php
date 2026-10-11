<?php

namespace App\Http\Requests;

use App\Support\OperationalNavigation;
use Illuminate\Foundation\Http\FormRequest;

class OperationalNavigationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'focus' => ['nullable', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'request' => ['nullable', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'from' => ['nullable', 'string', 'max:30'],
            'context' => ['nullable', 'array', 'max:16', function ($attribute, $value, $fail): void {
                if (is_array($value) && ! OperationalNavigation::validStateShape($value)) {
                    $fail('Contexto de navegação inválido.');
                }
            }],
            'month' => ['nullable', 'string', 'max:7'],
            'period' => ['nullable', 'string', 'max:20'],
            'filter' => ['nullable', 'string', 'max:30'],
            'selectedDate' => ['nullable', 'date_format:Y-m-d'],
            'sectorId' => ['nullable', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'categoryId' => ['nullable', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'tab' => ['nullable', 'string', 'max:30'],
            'searchProfessional' => ['nullable', 'string', 'max:250'],
            'swapStatusFilter' => ['nullable', 'string', 'max:30'],
            'search' => ['nullable', 'string', 'max:100'],
            'filterSectorId' => ['nullable', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'filterCategoryId' => ['nullable', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'dateFrom' => ['nullable', 'date_format:Y-m-d'],
            'dateTo' => ['nullable', 'date_format:Y-m-d'],
            'shiftsPage' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'incomingPage' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'professionalsPage' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'swapsPage' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'absencesPage' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
