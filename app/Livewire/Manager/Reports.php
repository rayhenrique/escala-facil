<?php

namespace App\Livewire\Manager;

use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Services\ShiftReportService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Relatórios'])]
class Reports extends Component
{
    #[Url]
    public string $month = '';

    public string $sectorId = '';

    public string $categoryId = '';

    public function mount(): void
    {
        $this->authorize('viewCalendar', Shift::class);
        $this->month = $this->normalizeMonth($this->month);
    }

    public function updatedMonth(): void
    {
        $this->month = $this->normalizeMonth($this->month);
    }

    public function updatedSectorId(string $sectorId): void
    {
        $this->sectorId = ctype_digit($sectorId) && Sector::query()->whereKey($sectorId)->exists() ? $sectorId : '';
    }

    public function updatedCategoryId(string $categoryId): void
    {
        $this->categoryId = ctype_digit($categoryId) && Category::query()->whereKey($categoryId)->exists() ? $categoryId : '';
    }

    public function clearFilters(): void
    {
        $this->reset('sectorId', 'categoryId');
    }

    #[Computed]
    public function sectors(): EloquentCollection
    {
        return Sector::query()->orderBy('name')->get();
    }

    #[Computed]
    public function categories(): EloquentCollection
    {
        return Category::query()->orderBy('name')->get();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function report(): array
    {
        return app(ShiftReportService::class)->forPeriod($this->month, $this->sectorId, $this->categoryId);
    }

    private function normalizeMonth(string $month): string
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return now()->format('Y-m');
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01');

        return $date !== false && $date->format('Y-m') === $month
            ? $month
            : now()->format('Y-m');
    }

    public function render(): View
    {
        return view('livewire.manager.reports', [
            'routePrefix' => request()->routeIs('professional.*') ? 'professional.' : 'manager.',
        ]);
    }
}
