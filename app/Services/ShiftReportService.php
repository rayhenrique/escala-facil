<?php

namespace App\Services;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Support\OperationalFilters;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ShiftReportService
{
    /** Screen lists are paginated; aggregates retain the same scope as the PDF. */
    public function forScreen(string $month, string $sectorId, string $categoryId, User $forUser, string $search = '', string $status = '', bool $validSearch = true): array
    {
        $isManager = $forUser->role === UserRole::Manager;
        $sectorId = $isManager ? $sectorId : (string) ($forUser->sector_id ?? 0);
        $start = CarbonImmutable::parse($month.'-01');
        $next = $start->addMonth();
        $scope = fn ($q) => $q
            ->where('date', '>=', $start->format('Y-m-d'))->where('date', '<', $next->format('Y-m-d'))
            ->when($sectorId !== '', fn ($q) => $q->where('sector_id', $sectorId))
            ->when($categoryId !== '', fn ($q) => $q->where('category_id', $categoryId));
        $shifts = $scope(Shift::query());
        $swaps = ShiftSwap::query()->where('created_at', '>=', $start)->where('created_at', '<', $next)
            ->whereHas('shift', fn ($q) => $q
                ->when($sectorId !== '', fn ($q) => $q->where('sector_id', $sectorId))
                ->when($categoryId !== '', fn ($q) => $q->where('category_id', $categoryId)));
        $absences = Absence::query()->whereHas('shift', $scope);
        $visibleSwaps = (clone $swaps)->when(! $isManager, fn ($q) => $q->where(fn ($q) => $q
            ->where('requester_id', $forUser->id)->orWhere('substitute_id', $forUser->id)));
        $counts = (clone $shifts)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $swapCounts = (clone $visibleSwaps)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $total = (int) $counts->sum();
        $covered = (int) $counts->get(ShiftStatus::Scheduled->value, 0);
        $groups = function (string $table, string $foreignKey) use ($shifts) {
            return (clone $shifts)->join($table, $table.'.id', '=', 'shifts.'.$foreignKey)
                ->selectRaw($table.'.name AS name, COUNT(*) AS total, SUM(CASE WHEN shifts.user_id IS NULL THEN 1 ELSE 0 END) AS uncovered')
                ->groupBy($table.'.name')->orderBy($table.'.name')->get()->map(fn ($row) => [
                    'name' => $row->name, 'total' => (int) $row->total, 'uncovered' => (int) $row->uncovered,
                ]);
        };
        $professionals = User::query()->with(['sector', 'category'])->when(! $validSearch, fn ($q) => $q->whereRaw('1 = 0'))
            ->whereIn('id', (clone $shifts)->select('user_id')->whereNotNull('user_id'))
            ->when(trim($search) !== '', fn ($q) => $q->where(fn ($names) => $names
                ->where(fn ($name) => OperationalFilters::name($name, $search))
                ->orWhereHas('sector', fn ($name) => OperationalFilters::name($name, $search))
                ->orWhereHas('category', fn ($name) => OperationalFilters::name($name, $search))))
            ->addSelect([
                'shift_count' => (clone $shifts)->selectRaw('COUNT(*)')->whereColumn('shifts.user_id', 'users.id'),
                'requested_count' => (clone $swaps)->selectRaw('COUNT(*)')->where('status', ShiftSwapStatus::Approved)->whereColumn('requester_id', 'users.id'),
                'substituted_count' => (clone $swaps)->selectRaw('COUNT(*)')->where('status', ShiftSwapStatus::Approved)->whereColumn('substitute_id', 'users.id'),
                'absence_count' => (clone $absences)->selectRaw('COUNT(*)')->whereColumn('user_id', 'users.id'),
            ])->orderBy('name')->orderBy('id')->paginate(10, ['*'], 'professionalsPage')
            ->through(fn (User $user) => [
                'name' => $user->name, 'team' => ($user->sector?->name ?? 'Sem setor').' · '.($user->category?->name ?? 'Sem categoria'),
                'shifts' => (int) $user->shift_count,
                'approvedSwaps' => (int) $user->requested_count + (int) $user->substituted_count,
                'absences' => (int) $user->absence_count,
            ]);

        return [
            'monthLabel' => $start->translatedFormat('F \d\e Y'),
            'summary' => [
                'total' => $total, 'covered' => $covered, 'coverageRate' => $total > 0 ? (int) round($covered / $total * 100) : null,
                'uncovered' => (int) $counts->get(ShiftStatus::Uncovered->value, 0),
                'absenceReported' => (int) $counts->get(ShiftStatus::AbsenceReported->value, 0),
                'absences' => (clone $absences)->count(),
                'swapsPendingProfessional' => (int) $swapCounts->get(ShiftSwapStatus::PendingProfessional->value, 0),
                'swapsPendingManager' => (int) $swapCounts->get(ShiftSwapStatus::PendingManager->value, 0),
                'swapsApproved' => (int) $swapCounts->get(ShiftSwapStatus::Approved->value, 0),
                'swapsRejected' => (int) $swapCounts->get(ShiftSwapStatus::Rejected->value, 0),
            ],
            'bySector' => $groups('sectors', 'sector_id'), 'byCategory' => $groups('categories', 'category_id'),
            'byProfessional' => $professionals,
            'swaps' => $visibleSwaps->when($status !== '', fn ($q) => $q->where('status', $status))
                ->with(['shift.sector', 'shift.category', 'requester', 'substitute'])->oldest()->orderBy('id')->paginate(10, ['*'], 'swapsPage'),
            'absencesList' => (clone $absences)->when(! $isManager, fn ($q) => $q->where('user_id', $forUser->id))
                ->with(['user', 'shift.sector', 'shift.category'])->oldest()->orderBy('id')->paginate(10, ['*'], 'absencesPage'),
            'isManager' => $isManager,
            'hasActiveFilters' => $isManager ? ($sectorId !== '' || $categoryId !== '') : ($categoryId !== ''),
        ];
    }

    /** @return array<string, mixed> */
    public function wallRoster(string $month, string $sectorId = '', string $categoryId = '', ?User $forUser = null): array
    {
        $isManager = ! $forUser || $forUser->role === UserRole::Manager;
        if (! $isManager) {
            $sectorId = (string) ($forUser->sector_id ?? 0);
        }

        [$year, $monthNumber] = array_pad(explode('-', $month), 2, null);
        $monthStart = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01')->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();

        $shifts = Shift::query()
            ->with(['sector', 'category', 'user'])
            ->when($year && $monthNumber, fn ($query) => $query->whereYear('date', $year)->whereMonth('date', $monthNumber))
            ->when($sectorId !== '', fn ($query) => $query->where('sector_id', $sectorId))
            ->when($categoryId !== '', fn ($query) => $query->where('category_id', $categoryId))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $swappedShiftIds = ShiftSwap::query()
            ->where('status', ShiftSwapStatus::Approved)
            ->whereHas('shift', fn ($query) => $query
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->when($sectorId !== '', fn ($shift) => $shift->where('sector_id', $sectorId))
                ->when($categoryId !== '', fn ($shift) => $shift->where('category_id', $categoryId)))
            ->pluck('shift_id')
            ->unique()
            ->all();

        return [
            'monthLabel' => $monthStart->translatedFormat('F \d\e Y'),
            'days' => $shifts->groupBy(fn (Shift $shift): string => $shift->date->format('Y-m-d')),
            'swappedShiftIds' => $swappedShiftIds,
            'total' => $shifts->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function forPeriod(string $month, string $sectorId = '', string $categoryId = '', ?User $forUser = null): array
    {
        $isManager = ! $forUser || $forUser->role === UserRole::Manager;
        if (! $isManager) {
            $sectorId = (string) ($forUser->sector_id ?? 0);
        }

        [$year, $monthNumber] = array_pad(explode('-', $month), 2, null);
        $monthStart = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01')->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();

        $shifts = Shift::query()
            ->with(['sector', 'category', 'user'])
            ->when($year && $monthNumber, fn ($query) => $query->whereYear('date', $year)->whereMonth('date', $monthNumber))
            ->when($sectorId !== '', fn ($query) => $query->where('sector_id', $sectorId))
            ->when($categoryId !== '', fn ($query) => $query->where('category_id', $categoryId))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $allSwaps = ShiftSwap::query()
            ->with(['shift.sector', 'shift.category', 'requester', 'substitute'])
            ->whereYear('created_at', $monthStart->year)
            ->whereMonth('created_at', $monthStart->month)
            ->when($sectorId !== '', fn ($query) => $query->whereHas('shift', fn ($shift) => $shift->where('sector_id', $sectorId)))
            ->when($categoryId !== '', fn ($query) => $query->whereHas('shift', fn ($shift) => $shift->where('category_id', $categoryId)))
            ->oldest()
            ->get();

        $visibleSwaps = $isManager
            ? $allSwaps
            : $allSwaps->filter(fn (ShiftSwap $swap) => $swap->requester_id === $forUser->id || $swap->substitute_id === $forUser->id)->values();

        $absences = Absence::query()
            ->with(['user', 'shift.sector', 'shift.category'])
            ->whereHas('shift', fn ($query) => $query
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->when($sectorId !== '', fn ($shift) => $shift->where('sector_id', $sectorId))
                ->when($categoryId !== '', fn ($shift) => $shift->where('category_id', $categoryId)))
            ->oldest()
            ->get();

        $total = $shifts->count();
        $covered = $shifts->where('status', ShiftStatus::Scheduled)->count();

        return [
            'monthLabel' => $monthStart->translatedFormat('F \d\e Y'),
            'summary' => [
                'total' => $total,
                'covered' => $covered,
                'uncovered' => $shifts->where('status', ShiftStatus::Uncovered)->count(),
                'absenceReported' => $shifts->where('status', ShiftStatus::AbsenceReported)->count(),
                'coverageRate' => $total > 0 ? (int) round($covered / $total * 100) : null,
                'absences' => $absences->count(),
                'swapsPendingProfessional' => $visibleSwaps->where('status', ShiftSwapStatus::PendingProfessional)->count(),
                'swapsPendingManager' => $visibleSwaps->where('status', ShiftSwapStatus::PendingManager)->count(),
                'swapsApproved' => $visibleSwaps->where('status', ShiftSwapStatus::Approved)->count(),
                'swapsRejected' => $visibleSwaps->where('status', ShiftSwapStatus::Rejected)->count(),
            ],
            'bySector' => $this->groupByRelation($shifts, 'sector'),
            'byCategory' => $this->groupByRelation($shifts, 'category'),
            'byProfessional' => $this->professionalRows($shifts, $allSwaps, $absences),
            'swaps' => $visibleSwaps,
            'absencesList' => $isManager
                ? $absences
                : $absences->filter(fn (Absence $absence) => $absence->user_id === $forUser?->id)->values(),
            'isManager' => $isManager,
            'hasActiveFilters' => $isManager ? ($sectorId !== '' || $categoryId !== '') : ($categoryId !== ''),
        ];
    }

    /**
     * @param  Collection<int, Shift>  $shifts
     * @return Collection<int, array{name: string, total: int, uncovered: int}>
     */
    private function groupByRelation(Collection $shifts, string $relation): Collection
    {
        return $shifts
            ->groupBy(fn (Shift $shift): string => $shift->{$relation}->name)
            ->map(fn (Collection $group, string $name): array => [
                'name' => $name,
                'total' => $group->count(),
                'uncovered' => $group->whereNull('user_id')->count(),
            ])
            ->sortBy('name')
            ->values();
    }

    /**
     * @param  Collection<int, Shift>  $shifts
     * @param  Collection<int, ShiftSwap>  $swaps
     * @param  Collection<int, Absence>  $absences
     * @return Collection<int, array{name: string, team: string, shifts: int, approvedSwaps: int, absences: int}>
     */
    private function professionalRows(Collection $shifts, Collection $swaps, Collection $absences): Collection
    {
        $approvedByUser = [];

        foreach ($swaps->where('status', ShiftSwapStatus::Approved) as $swap) {
            $approvedByUser[$swap->requester_id] = ($approvedByUser[$swap->requester_id] ?? 0) + 1;
            $approvedByUser[$swap->substitute_id] = ($approvedByUser[$swap->substitute_id] ?? 0) + 1;
        }

        $absencesByUser = $absences->groupBy(fn (Absence $absence): int => $absence->user_id);

        return User::query()
            ->with(['sector', 'category'])
            ->whereIn('id', $shifts->pluck('user_id')->filter()->unique()->all())
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'name' => $user->name,
                'team' => $user->sector->name.' · '.$user->category->name,
                'shifts' => $shifts->where('user_id', $user->id)->count(),
                'approvedSwaps' => $approvedByUser[$user->id] ?? 0,
                'absences' => $absencesByUser->get($user->id, collect())->count(),
            ]);
    }
}
