<?php

namespace App\Livewire\Manager;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Models\Shift;
use App\Models\ShiftSwap;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Painel da gerência'])]
class Dashboard extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', Shift::class);
        $this->authorize('viewAny', ShiftSwap::class);
    }

    public function render(): View
    {
        $uncoveredQuery = Shift::query()
            ->where('status', ShiftStatus::Uncovered)
            ->whereDate('date', '>=', today());

        $pendingSwapQuery = ShiftSwap::query()
            ->where('status', ShiftSwapStatus::PendingManager);

        $uncoveredShifts = (clone $uncoveredQuery)
            ->with([
                'category',
                'sector',
                'absences' => fn ($query) => $query->with('user')->latest(),
            ])
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit(4)
            ->get();

        $pendingSwaps = (clone $pendingSwapQuery)
            ->with(['shift.category', 'shift.sector', 'requester', 'substitute'])
            ->oldest()
            ->limit(4)
            ->get();

        $monthStart = today()->startOfMonth();
        $monthEnd = today()->endOfMonth();

        return view('livewire.manager.dashboard', [
            'uncoveredShifts' => $uncoveredShifts,
            'pendingSwaps' => $pendingSwaps,
            'uncoveredCount' => (clone $uncoveredQuery)->count(),
            'pendingSwapCount' => (clone $pendingSwapQuery)->count(),
            'scheduledThisMonth' => Shift::query()
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->where('status', ShiftStatus::Scheduled)
                ->count(),
        ]);
    }
}
