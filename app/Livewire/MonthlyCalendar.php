<?php

namespace App\Livewire;

use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
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

        $user = auth()->user();
        if ($user?->role === UserRole::Professional) {
            $this->sectorId = (string) ($user->sector_id ?? '');
        }

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
        $user = auth()->user();
        if ($user?->role === UserRole::Professional) {
            $this->sectorId = (string) ($user->sector_id ?? '');

            return;
        }

        $this->sectorId = $this->validFilterId($sectorId, Sector::class) ? $sectorId : '';
    }

    public function updatedCategoryId(string $categoryId): void
    {
        $this->categoryId = $this->validFilterId($categoryId, Category::class) ? $categoryId : '';
    }

    public function clearFilters(): void
    {
        $user = auth()->user();
        if ($user?->role === UserRole::Professional) {
            $this->reset('categoryId');
            $this->sectorId = (string) ($user->sector_id ?? '');

            return;
        }

        $this->reset('sectorId', 'categoryId');
    }

    #[Computed]
    public function sectors(): EloquentCollection
    {
        $user = auth()->user();
        if ($user?->role === UserRole::Professional) {
            return $user->sector_id
                ? Sector::query()->whereKey($user->sector_id)->get()
                : new EloquentCollection;
        }

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
        $user = auth()->user();
        $isProfessional = $user?->role === UserRole::Professional;
        $effectiveSectorId = $isProfessional
            ? (string) ($user->sector_id ?? 0)
            : $this->sectorId;

        $monthStart = CarbonImmutable::createFromFormat('!Y-m-d', $this->month.'-01')->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();

        $allShifts = $this->getShiftsForMonth($monthStart, $monthEnd);
        $shiftsByDate = $allShifts->groupBy(fn (Shift $shift): string => $shift->date->format('Y-m-d'));
        $swappedShiftsDetails = $this->getSwappedShiftsDetails($monthStart, $monthEnd);
        $swappedShiftIds = array_keys($swappedShiftsDetails);

        $hasSectorFilter = $effectiveSectorId !== '';
        $calendarDays = $this->calendarDays($monthStart, $monthEnd, $shiftsByDate, $allShifts, $swappedShiftsDetails, $hasSectorFilter);
        $selectedShifts = $shiftsByDate->get($this->selectedDate, collect());

        return view('livewire.monthly-calendar', [
            'calendarDays' => $calendarDays,
            'monthLabel' => $monthStart->translatedFormat('F \d\e Y'),
            'selectedDateLabel' => CarbonImmutable::parse($this->selectedDate)->translatedFormat('l, d \d\e F \d\e Y'),
            'selectedShifts' => $selectedShifts,
            'swappedShiftIds' => $swappedShiftIds,
            'swappedShiftsDetails' => $swappedShiftsDetails,
            'assignedProfessionalsCount' => $selectedShifts->pluck('user_id')->filter()->unique()->count(),
            'canManageShifts' => $user?->can('create', Shift::class) ?? false,
            'hasActiveFilters' => $isProfessional ? ($this->categoryId !== '') : ($this->sectorId !== '' || $this->categoryId !== ''),
            'hasSectorFilter' => $hasSectorFilter,
            'isProfessional' => $isProfessional,
        ]);
    }

    /**
     * @return EloquentCollection<int, Shift>
     */
    private function getShiftsForMonth(CarbonImmutable $monthStart, CarbonImmutable $monthEnd): EloquentCollection
    {
        $user = auth()->user();
        $effectiveSectorId = ($user?->role === UserRole::Professional)
            ? (string) ($user->sector_id ?? 0)
            : $this->sectorId;

        return Shift::query()
            ->with(['category', 'sector', 'user'])
            ->whereBetween('date', [$monthStart->subDay(), $monthEnd])
            ->when($effectiveSectorId !== '', fn ($query) => $query->where('sector_id', $effectiveSectorId))
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
        $user = auth()->user();
        $effectiveSectorId = ($user?->role === UserRole::Professional)
            ? (string) ($user->sector_id ?? 0)
            : $this->sectorId;

        $swaps = ShiftSwap::query()
            ->where('status', ShiftSwapStatus::Approved)
            ->with(['requester', 'substitute'])
            ->where(function ($query) use ($monthStart, $monthEnd, $effectiveSectorId): void {
                $query->whereHas('shift', fn ($q) => $q
                    ->whereBetween('date', [$monthStart->subDay(), $monthEnd])
                    ->when($effectiveSectorId !== '', fn ($sq) => $sq->where('sector_id', $effectiveSectorId))
                )->orWhereHas('substituteShift', fn ($q) => $q
                    ->whereBetween('date', [$monthStart->subDay(), $monthEnd])
                    ->when($effectiveSectorId !== '', fn ($sq) => $sq->where('sector_id', $effectiveSectorId))
                );
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
     * Extrai exatamente duas letras iniciais para identificação visual do profissional.
     * Ex.: "Ana Silva" -> "AS", "Carlos" -> "CA", "Carla de Oliveira" -> "CO", "Alice Martins (Demo)" -> "AM".
     */
    public static function initialsForName(string $name): string
    {
        $cleaned = preg_replace('/\s*\([^)]*\)/u', '', $name);
        $cleaned = preg_replace('/[^\p{L}\s]/u', '', (string) $cleaned);

        $parts = preg_split('/\s+/u', trim((string) $cleaned)) ?: [];
        $meaningful = array_values(array_filter(
            $parts,
            fn (string $part) => ! in_array(mb_strtolower($part), ['de', 'da', 'do', 'dos', 'das', 'e', 'dr.', 'dra.', 'dr', 'dra'], true) && mb_strlen($part) > 0
        ));

        if (count($meaningful) >= 2) {
            $first = mb_substr($meaningful[0], 0, 1);
            $second = mb_substr(end($meaningful), 0, 1);

            return mb_strtoupper($first.$second);
        }

        if (count($meaningful) === 1) {
            return mb_strtoupper(mb_substr($meaningful[0], 0, 2));
        }

        return mb_strtoupper(mb_substr($name, 0, 2));
    }

    /**
     * @param  Collection<string, Collection<int, Shift>>  $shiftsByDate
     * @param  Collection<int, Shift>  $allShifts
     * @param  array<int, array{requester_name: string, substitute_name: string, is_mutual: bool, headline: string, tooltip: string}>  $swappedShiftsDetails
     * @return Collection<int, array{
     *     date: string,
     *     day: string,
     *     in_month: bool,
     *     is_today: bool,
     *     shifts_count: int,
     *     uncovered_count: int,
     *     covered_24h: bool,
     *     has_swap: bool,
     *     swap_tooltip: ?string,
     *     professionals: list<array{shift_id: int, name: string, initials: string, time: string, is_swapped: bool, is_uncovered: bool}>
     * }>
     */
    private function calendarDays(
        CarbonImmutable $monthStart,
        CarbonImmutable $monthEnd,
        Collection $shiftsByDate,
        Collection $allShifts,
        array $swappedShiftsDetails = [],
        bool $hasSectorFilter = false,
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

            $professionals = [];
            if ($hasSectorFilter && $inMonth && $dayShifts->isNotEmpty()) {
                foreach ($dayShifts as $shift) {
                    $time = substr((string) $shift->start_time, 0, 5).'–'.substr((string) $shift->end_time, 0, 5);
                    if ($shift->user) {
                        $professionals[] = [
                            'shift_id' => $shift->id,
                            'name' => $shift->user->name,
                            'initials' => self::initialsForName($shift->user->name),
                            'time' => $time,
                            'is_swapped' => isset($swappedShiftsDetails[$shift->id]),
                            'is_uncovered' => false,
                        ];
                    } else {
                        $professionals[] = [
                            'shift_id' => $shift->id,
                            'name' => 'Plantão sem profissional',
                            'initials' => '--',
                            'time' => $time,
                            'is_swapped' => false,
                            'is_uncovered' => true,
                        ];
                    }
                }
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
                'professionals' => $professionals,
            ]);

            $cursor = $cursor->addDay();
        }

        return $days;
    }
}
