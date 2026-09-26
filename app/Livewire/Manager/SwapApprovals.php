<?php

namespace App\Livewire\Manager;

use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Enums\ShiftSwapEvent;
use App\Enums\ShiftSwapStatus;
use App\Models\ShiftSwap;
use App\Support\ShiftSwapReceipt;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Aprovação de trocas'])]
class SwapApprovals extends Component
{
    use WithPagination;

    /** @var array<string, mixed>|null */
    public ?array $swapReceipt = null;

    public function mount(): void
    {
        $this->authorize('viewAny', ShiftSwap::class);
    }

    public function approve(int $shiftSwapId, DecideShiftSwapAction $action, ShiftSwapReceipt $receipts): void
    {
        $shiftSwap = ShiftSwap::query()->findOrFail($shiftSwapId);
        $this->authorize('approve', $shiftSwap);
        $shiftSwap = $action->approve(auth()->user(), $shiftSwapId);
        $this->swapReceipt = $receipts->make($shiftSwap, ShiftSwapEvent::Approved);

        session()->flash('status', 'Troca aprovada e plantão transferido ao substituto.');
    }

    public function reject(int $shiftSwapId, DecideShiftSwapAction $action, ShiftSwapReceipt $receipts): void
    {
        $shiftSwap = ShiftSwap::query()->findOrFail($shiftSwapId);
        $this->authorize('reject', $shiftSwap);
        $shiftSwap = $action->reject(auth()->user(), $shiftSwapId);
        $this->swapReceipt = $receipts->make($shiftSwap, ShiftSwapEvent::Rejected);

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
