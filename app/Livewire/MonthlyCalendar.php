<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Calendário de plantões'])]
class MonthlyCalendar extends Component
{
    #[Url]
    public string $month = '';

    public string $selectedDate = '';

    public string $sectorId = '';

    public string $categoryId = '';

    public function mount(): void
    {
        $this->authorize('viewCalendar', Shift::class);

        $this->month = $this->validMonth($this->month) ? $this->month : now()->format('Y-m');
        $this->selectedDate = $this->month === now()->format('Y-m')
            ? today()->format('Y-m-d')
            : $this->month.'-01';
    }

    public function updatedMonth(string $month): void
    {
        if (! $this->validMonth($month)) {
            $this->month = now()->format('Y-m');
        }

        $this->selectedDate = $this->month === now()->format('Y-m')
            ? today()->format('Y-m-d')
            : $this->month.'-01';
    }

    public function updatedSectorId(string $sectorId): void
    {
        $this->sectorId = $this->validFilterId($sectorId, Sector::class) ? $sectorId : '';
    }

    public function updatedCategoryId(string $categoryId): void
    {
        $this->categoryId = $this->validFilterId($categoryId, Category::class) ? $categoryId : '';
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

    public function previousMonth(): void
    {
        $this->moveMonth(-1);
    }

    public function nextMonth(): void
    {
        $this->moveMonth(1);
    }

    public function selectDate(string $date): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $date)) {
            return;
        }

        try {
            $parsedDate = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            return;
        }

        if (! $parsedDate || $parsedDate->format('Y-m-d') !== $date || $parsedDate->format('Y-m') !== $this->month) {
            return;
        }

        $this->selectedDate = $date;
    }

    private function moveMonth(int $months): void
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $this->month.'-01')
            ->addMonthsNoOverflow($months);

        $this->month = $date->format('Y-m');
        $this->selectedDate = $this->month === now()->format('Y-m')
            ? today()->format('Y-m-d')
            : $date->format('Y-m-d');
    }

    private function validMonth(string $month): bool
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return false;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01');

        return $date !== false && $date->format('Y-m') === $month;
    }

    private function validFilterId(string $value, string $model): bool
    {
        return ctype_digit($value) && $model::query()->whereKey($value)->exists();
    }

    public function render(): View
    {
        $monthStart = CarbonImmutable::createFromFormat('!Y-m-d', $this->month.'-01')->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();

        $shifts = Shift::query()
            ->with(['category', 'sector', 'user'])
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->when($this->sectorId !== '', fn ($query) => $query->where('sector_id', $this->sectorId))
            ->when($this->categoryId !== '', fn ($query) => $query->where('category_id', $this->categoryId))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $shiftsByDate = $shifts->groupBy(fn (Shift $shift): string => $shift->date->format('Y-m-d'));
        $calendarDays = $this->calendarDays($monthStart, $monthEnd, $shiftsByDate);
        $selectedShifts = $shiftsByDate->get($this->selectedDate, collect());

        return view('livewire.monthly-calendar', [
            'calendarDays' => $calendarDays,
            'monthLabel' => $monthStart->translatedFormat('F \d\e Y'),
            'selectedDateLabel' => CarbonImmutable::parse($this->selectedDate)->translatedFormat('l, d \d\e F \d\e Y'),
            'selectedShifts' => $selectedShifts,
            'assignedProfessionalsCount' => $selectedShifts->pluck('user_id')->filter()->unique()->count(),
            'canManageShifts' => auth()->user()->can('create', Shift::class),
            'hasActiveFilters' => $this->sectorId !== '' || $this->categoryId !== '',
        ]);
    }

    /**
     * @param  Collection<string, Collection<int, Shift>>  $shiftsByDate
     * @return Collection<int, array{date: string, day: string, in_month: bool, is_today: bool, shifts_count: int, uncovered_count: int}>
     */
    private function calendarDays(
        CarbonImmutable $monthStart,
        CarbonImmutable $monthEnd,
        Collection $shiftsByDate,
    ): Collection {
        $days = collect();
        $cursor = $monthStart->startOfWeek(CarbonInterface::SUNDAY);
        $gridEnd = $monthEnd->endOfWeek(CarbonInterface::SATURDAY);

        while ($cursor->lessThanOrEqualTo($gridEnd)) {
            $date = $cursor->format('Y-m-d');
            $dayShifts = $shiftsByDate->get($date, collect());

            $days->push([
                'date' => $date,
                'day' => $cursor->format('d'),
                'in_month' => $cursor->month === $monthStart->month,
                'is_today' => $cursor->isToday(),
                'shifts_count' => $dayShifts->count(),
                'uncovered_count' => $dayShifts->whereNull('user_id')->count(),
            ]);

            $cursor = $cursor->addDay();
        }

        return $days;
    }
}
