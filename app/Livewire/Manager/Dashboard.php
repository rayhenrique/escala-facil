<?php

namespace App\Livewire\Manager;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Livewire\Concerns\AnalyzesShiftSwaps;
use App\Livewire\Concerns\HasOperationalContext;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Support\ManagerAttention;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Painel da gerência'])]
class Dashboard extends Component
{
    use AnalyzesShiftSwaps;
    use HasOperationalContext;
    use WithPagination;

    #[Url]
    public string $period = '3';

    public function mount(): void
    {
        $this->authorize('viewAny', Shift::class);
        $this->authorize('viewAny', ShiftSwap::class);
        $this->normalizePeriod();
    }

    public function updatedPeriod(): void
    {
        $this->normalizePeriod();
        $this->resetPage();
    }

    private function normalizePeriod(): void
    {
        if (! in_array($this->period, ['1', '3', '7'], true)) {
            $this->period = '3';
        }
    }

    public function render(): View
    {
        $this->authorize('viewAny', ShiftSwap::class);
        $this->normalizePeriod();
        $now = now()->toImmutable();
        $rollingEnd = $now->addHours(72);
        // Half-open interval [now, now + 72h), by shift start, not calendar dates.
        $coverage = ManagerAttention::uncovered($now)->selectRaw('COUNT(*) AS total, COALESCE(SUM(CASE WHEN
            (DATE(date) > ? OR (DATE(date) = ? AND TIME(start_time) >= ?)) AND
            (DATE(date) < ? OR (DATE(date) = ? AND TIME(start_time) < ?))
            THEN 1 ELSE 0 END), 0) AS rolling', [
            $now->toDateString(), $now->toDateString(), $now->format('H:i:s'),
            $rollingEnd->toDateString(), $rollingEnd->toDateString(), $rollingEnd->format('H:i:s'),
        ])->first();

        $queue = app(ManagerAttention::class)->queue($now, (int) $this->period);
        $this->paginators['page'] = $queue->currentPage();

        return view('livewire.manager.dashboard', [
            'queue' => $queue,
            'uncoveredCount' => (int) $coverage->total,
            'pendingSwapCount' => ShiftSwap::query()->where('status', ShiftSwapStatus::PendingManager)->count(),
            'scheduledThisMonth' => Shift::query()->whereBetween('date', [$now->startOfMonth(), $now->endOfMonth()])->where('status', ShiftStatus::Scheduled)->count(),
            'uncovered72Count' => (int) $coverage->rolling,
            'rollingEnd' => $rollingEnd,
            'now' => $now,
            'calendarEnd' => $now->startOfDay()->addDays((int) $this->period - 1),
        ]);
    }
}
