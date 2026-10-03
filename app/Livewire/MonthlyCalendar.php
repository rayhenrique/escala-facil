<?php

namespace App\Livewire;

use App\Enums\ShiftSwapStatus;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Support\ShiftCoverage;
use Carbon\CarbonImmutable;
use Carbon\WeekDay;
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

        $allShifts = $this->getShiftsForMonth($monthStart, $monthEnd);
        $shiftsByDate = $allShifts->groupBy(fn (Shift $shift): string => $shift->date->format('Y-m-d'));
        $swappedShiftsDetails = $this->getSwappedShiftsDetails($monthStart, $monthEnd);
        $swappedShiftIds = array_keys($swappedShiftsDetails);

        $calendarDays = $this->calendarDays($monthStart, $monthEnd, $shiftsByDate, $allShifts, $swappedShiftsDetails);
        $selectedShifts = $shiftsByDate->get($this->selectedDate, collect());

        return view('livewire.monthly-calendar', [
            'calendarDays' => $calendarDays,
            'monthLabel' => $monthStart->translatedFormat('F \d\e Y'),
            'selectedDateLabel' => CarbonImmutable::parse($this->selectedDate)->translatedFormat('l, d \d\e F \d\e Y'),
            'selectedShifts' => $selectedShifts,
            'swappedShiftIds' => $swappedShiftIds,
            'swappedShiftsDetails' => $swappedShiftsDetails,
            'assignedProfessionalsCount' => $selectedShifts->pluck('user_id')->filter()->unique()->count(),
            'canManageShifts' => auth()->user()?->can('create', Shift::class) ?? false,
            'hasActiveFilters' => $this->sectorId !== '' || $this->categoryId !== '',
        ]);
    }

    /**
     * @return EloquentCollection<int, Shift>
     */
    private function getShiftsForMonth(CarbonImmutable $monthStart, CarbonImmutable $monthEnd): EloquentCollection
    {
        return Shift::query()
            ->with(['category', 'sector', 'user'])
            ->whereBetween('date', [$monthStart->subDay(), $monthEnd])
            ->when($this->sectorId !== '', fn ($query) => $query->where('sector_id', $this->sectorId))
            ->when($this->categoryId !== '', fn ($query) => $query->where('category_id', $this->categoryId))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * @return array<int, array{
     *     requester_name: string,
     *     substitute_name: string,
     *     is_mutual: bool,
     *     headline: string,
     *     tooltip: string
     * }>
     */
    private function getSwappedShiftsDetails(CarbonImmutable $monthStart, CarbonImmutable $monthEnd): array
    {
        $swaps = ShiftSwap::query()
            ->where('status', ShiftSwapStatus::Approved)
            ->with(['requester', 'substitute'])
            ->where(function ($query) use ($monthStart, $monthEnd): void {
                $query->whereHas('shift', fn ($q) => $q->whereBetween('date', [$monthStart->subDay(), $monthEnd]))
                    ->orWhereHas('substituteShift', fn ($q) => $q->whereBetween('date', [$monthStart->subDay(), $monthEnd]));
            })
            ->get();

        $details = [];

        foreach ($swaps as $swap) {
            $requesterName = $swap->requester?->name ?? 'Profissional';
            $substituteName = $swap->substitute?->name ?? 'Profissional';
            $isMutual = $swap->substitute_shift_id !== null;

            if ($swap->shift_id) {
                $headline = $isMutual
                    ? "Troca mútua realizada entre {$requesterName} e {$substituteName}"
                    : "Troca realizada: {$requesterName} cedeu para {$substituteName}";

                $tooltip = $isMutual
                    ? "Troca mútua realizada entre {$requesterName} e {$substituteName} (Solicitante: {$requesterName} ⇄ Substituto: {$substituteName})"
                    : "Troca realizada: {$requesterName} trocou com {$substituteName} (Solicitante: {$requesterName} → Substituto: {$substituteName})";

                $details[$swap->shift_id] = [
                    'requester_name' => $requesterName,
                    'substitute_name' => $substituteName,
                    'is_mutual' => $isMutual,
                    'headline' => $headline,
                    'tooltip' => $tooltip,
                ];
            }

            if ($swap->substitute_shift_id) {
                $headline = "Troca mútua realizada entre {$requesterName} e {$substituteName}";
                $tooltip = "Troca mútua realizada entre {$requesterName} e {$substituteName} (Solicitante: {$requesterName} ⇄ Substituto: {$substituteName})";

                $details[$swap->substitute_shift_id] = [
                    'requester_name' => $requesterName,
                    'substitute_name' => $substituteName,
                    'is_mutual' => true,
                    'headline' => $headline,
                    'tooltip' => $tooltip,
                ];
            }
        }

        return $details;
    }

    /**
     * @return list<int>
     */
    private function getSwappedShiftIds(CarbonImmutable $monthStart, CarbonImmutable $monthEnd): array
    {
        return array_keys($this->getSwappedShiftsDetails($monthStart, $monthEnd));
    }

    /**
     * @param  Collection<string, Collection<int, Shift>>  $shiftsByDate
     * @param  Collection<int, Shift>  $allShifts
     * @param  array<int, array{requester_name: string, substitute_name: string, is_mutual: bool, headline: string, tooltip: string}>  $swappedShiftsDetails
     * @return Collection<int, array{date: string, day: string, in_month: bool, is_today: bool, shifts_count: int, uncovered_count: int, covered_24h: bool, has_swap: bool, swap_tooltip: ?string}>
     */
    private function calendarDays(
        CarbonImmutable $monthStart,
        CarbonImmutable $monthEnd,
        Collection $shiftsByDate,
        Collection $allShifts,
        array $swappedShiftsDetails = [],
    ): Collection {
        $days = collect();
        $cursor = $monthStart->startOfWeek(WeekDay::Sunday);
        $gridEnd = $monthEnd->endOfWeek(WeekDay::Saturday);

        while ($cursor->lessThanOrEqualTo($gridEnd)) {
            $date = $cursor->format('Y-m-d');
            $inMonth = $cursor->month === $monthStart->month;
            $dayShifts = $inMonth ? $shiftsByDate->get($date, collect()) : collect();

            $daySwappedShifts = $dayShifts->filter(fn (Shift $shift) => isset($swappedShiftsDetails[$shift->id]));
            $hasSwap = $inMonth && $daySwappedShifts->isNotEmpty();

            $swapTooltip = null;
            if ($hasSwap) {
                $swapSummaries = $daySwappedShifts->map(function (Shift $shift) use ($swappedShiftsDetails): string {
                    $item = $swappedShiftsDetails[$shift->id];

                    return "{$item['requester_name']} ⇄ {$item['substitute_name']}";
                })->unique()->values();

                $swapTooltip = 'Troca(s) realizada(s): '.$swapSummaries->implode(' · ');
            }

            $days->push([
                'date' => $date,
                'day' => $cursor->format('d'),
                'in_month' => $inMonth,
                'is_today' => $cursor->isToday(),
                'shifts_count' => $dayShifts->count(),
                'uncovered_count' => $dayShifts->whereNull('user_id')->count(),
                'covered_24h' => $inMonth
                    && $dayShifts->isNotEmpty()
                    && ShiftCoverage::isDayFullyCovered($allShifts, $date),
                'has_swap' => $hasSwap,
                'swap_tooltip' => $swapTooltip,
            ]);

            $cursor = $cursor->addDay();
        }

        return $days;
    }
}
