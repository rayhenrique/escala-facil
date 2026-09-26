<?php

namespace App\Livewire\Manager;

use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Enums\ShiftSwapStatus;
use App\Models\ShiftSwap;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Aprovação de trocas'])]
class SwapApprovals extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('viewAny', ShiftSwap::class);
    }

    public function approve(int $shiftSwapId, DecideShiftSwapAction $action): void
    {
        $shiftSwap = ShiftSwap::query()->findOrFail($shiftSwapId);
        $this->authorize('approve', $shiftSwap);
        $action->approve(auth()->user(), $shiftSwapId);

        session()->flash('status', 'Troca aprovada e plantão transferido ao substituto.');
    }

    public function reject(int $shiftSwapId, DecideShiftSwapAction $action): void
    {
        $shiftSwap = ShiftSwap::query()->findOrFail($shiftSwapId);
        $this->authorize('reject', $shiftSwap);
        $action->reject(auth()->user(), $shiftSwapId);

        session()->flash('status', 'Solicitação de troca rejeitada.');
    }

    public function render(): View
    {
        $swaps = ShiftSwap::query()
            ->with([
                'shift.category',
                'shift.sector',
                'requester',
                'substitute',
            ])
            ->where('status', ShiftSwapStatus::PendingManager)
            ->oldest()
            ->paginate(10);

        return view('livewire.manager.swap-approvals', compact('swaps'));
    }
}
