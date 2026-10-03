<?php

namespace App\Services;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ShiftReportService
{
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
