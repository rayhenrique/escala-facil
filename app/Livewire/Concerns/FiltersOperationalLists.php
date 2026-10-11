<?php

namespace App\Livewire\Concerns;

use App\Http\Requests\OperationalFiltersRequest;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Url;

trait FiltersOperationalLists
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $filterSectorId = '';

    #[Url]
    public string $filterCategoryId = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public function listFilters(): array
    {
        return $this->only(['search', 'filterSectorId', 'filterCategoryId', 'dateFrom', 'dateTo']);
    }

    public function updatedFiltersOperationalLists(string $property): void
    {
        if (array_key_exists($property, $this->listFilters())) {
            $this->resetPage();
        }
    }

    protected function validListFilters(): bool
    {
        $request = new OperationalFiltersRequest;
        $payload = $this->listFilters();
        foreach (['filter', 'swapStatusFilter'] as $key) {
            if (property_exists($this, $key)) {
                $payload[$key] = $this->{$key};
            }
        }
        $request->replace($payload);
        $validator = Validator::make($payload, $request->rules(), $request->messages());
        $this->resetValidation(array_keys($payload));
        foreach ($validator->errors()->messages() as $key => $messages) {
            $this->addError($key, $messages[0]);
        }

        return ! $validator->fails();
    }

    public function clearListFilters(): void
    {
        $this->reset(array_keys($this->listFilters()));
        $this->resetPage();
        $this->resetValidation(array_keys($this->listFilters()));
    }
}
