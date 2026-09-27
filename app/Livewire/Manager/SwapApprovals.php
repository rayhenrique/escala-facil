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

    public ?int $pendingDecisionSwapId = null;

    public ?string $pendingDecision = null;

    /** @var array<string, string>|null */
    public ?array $pendingDecisionSummary = null;

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

    public function requestDecision(int $shiftSwapId, string $decision): void
    {
        abort_unless(in_array($decision, ['approve', 'reject'], true), 404);

        $shiftSwap = ShiftSwap::query()
            ->with(['shift.sector', 'shift.category', 'requester', 'substitute'])
            ->findOrFail($shiftSwapId);
        $this->authorize($decision, $shiftSwap);

        $this->pendingDecisionSwapId = $shiftSwap->id;
        $this->pendingDecision = $decision;
        $this->pendingDecisionSummary = [
            'shift' => sprintf(
                '%s, das %s às %s',
                $shiftSwap->shift->date->format('d/m/Y'),
                substr((string) $shiftSwap->shift->start_time, 0, 5),
                substr((string) $shiftSwap->shift->end_time, 0, 5),
            ),
            'location' => $shiftSwap->shift->sector->name.' · '.$shiftSwap->shift->category->name,
            'requester' => $shiftSwap->requester->name,
            'substitute' => $shiftSwap->substitute->name,
        ];
    }

    public function confirmDecision(DecideShiftSwapAction $action, ShiftSwapReceipt $receipts): void
    {
        abort_unless($this->pendingDecisionSwapId && in_array($this->pendingDecision, ['approve', 'reject'], true), 404);

        $shiftSwapId = $this->pendingDecisionSwapId;
        $decision = $this->pendingDecision;

        if ($decision === 'approve') {
            $this->approve($shiftSwapId, $action, $receipts);
        } else {
            $this->reject($shiftSwapId, $action, $receipts);
        }

        $this->cancelDecision();
    }

    public function cancelDecision(): void
    {
        $this->reset(['pendingDecisionSwapId', 'pendingDecision', 'pendingDecisionSummary']);
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
