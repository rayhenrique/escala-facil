<?php

namespace App\Livewire\Concerns;

use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Enums\ShiftSwapEvent;
use App\Models\ShiftSwap;
use App\Support\ShiftSwapAnalysis;
use App\Support\ShiftSwapReceipt;
use App\Support\UiFeedback;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Locked;

trait AnalyzesShiftSwaps
{
    #[Locked]
    public ?int $pendingDecisionSwapId = null;

    #[Locked]
    public ?string $pendingDecision = null;

    #[Locked]
    public ?array $pendingDecisionSummary = null;

    #[Locked]
    public ?string $decisionSnapshot = null;

    public string $rejectionReason = '';

    public ?array $swapReceipt = null;

    public function openSwapAnalysis(int $id): void
    {
        $this->authorize('viewAny', ShiftSwap::class);
        $swap = ShiftSwap::query()->findOrFail($id);
        $this->authorize('view', $swap);
        if (method_exists($this, 'closeReallocation')) {
            $this->closeReallocation();
        }
        if (method_exists($this, 'cancelRevert')) {
            $this->cancelRevert();
        }
        $this->cancelDecision();
        $this->pendingDecisionSummary = app(ShiftSwapAnalysis::class)->make($swap, auth()->user());
        $this->decisionSnapshot = $this->pendingDecisionSummary['fingerprint'];
        unset($this->pendingDecisionSummary['fingerprint']);
        $this->pendingDecisionSwapId = $id;
    }

    public function refreshSwapAnalysis(): void
    {
        abort_unless($this->pendingDecisionSwapId, 404);
        $reason = $this->rejectionReason;
        $this->openSwapAnalysis($this->pendingDecisionSwapId);
        $this->rejectionReason = $reason;
    }

    public function requestDecision(int $shiftSwapId, string $decision): void
    {
        abort_unless(in_array($decision, ['approve', 'reject'], true), 404);
        // Keep the reviewed snapshot when choosing the decision, so changed alerts
        // cannot be silently accepted without another analysis.
        if ($this->pendingDecisionSwapId !== $shiftSwapId) {
            $this->openSwapAnalysis($shiftSwapId);
        }
        try {
            $this->authorize($decision, ShiftSwap::query()->findOrFail($shiftSwapId));
        } catch (AuthorizationException $exception) {
            $this->authorize('viewAny', ShiftSwap::class);
            $this->addError('decision', $exception->getMessage());

            return;
        }
        $this->pendingDecision = $decision;
        $this->resetValidation();
    }

    public function returnToSwapAnalysis(): void
    {
        $this->pendingDecision = null;
        $this->resetValidation();
    }

    public function confirmDecision(DecideShiftSwapAction $action, ShiftSwapReceipt $receipts): void
    {
        abort_unless($this->pendingDecisionSwapId && $this->decisionSnapshot && in_array($this->pendingDecision, ['approve', 'reject'], true), 404);
        try {
            $swap = $this->pendingDecision === 'approve'
                ? $action->approve(auth()->user(), $this->pendingDecisionSwapId, $this->decisionSnapshot)
                : $action->reject(auth()->user(), $this->pendingDecisionSwapId, $this->rejectionReason, $this->decisionSnapshot);
        } catch (AuthorizationException $exception) {
            $this->authorize('viewAny', ShiftSwap::class);
            $this->addError('decision', $exception->getMessage());

            return;
        }
        $event = $this->pendingDecision === 'approve' ? ShiftSwapEvent::Approved : ShiftSwapEvent::Rejected;
        $this->swapReceipt = $receipts->make($swap, $event);
        $this->cancelDecision();
        $this->dispatch('notifications-updated');
        UiFeedback::success($event === ShiftSwapEvent::Approved
            ? 'Troca aprovada e plantão transferido ao substituto.'
            : 'Solicitação de troca rejeitada e comprovante gerado.');
    }

    public function cancelDecision(): void
    {
        $this->reset(['pendingDecisionSwapId', 'pendingDecision', 'pendingDecisionSummary', 'decisionSnapshot', 'rejectionReason']);
        $this->resetValidation();
    }
}
