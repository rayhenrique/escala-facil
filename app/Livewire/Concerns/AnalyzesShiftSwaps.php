<?php

namespace App\Livewire\Concerns;

use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Enums\ShiftSwapEvent;
use App\Models\ShiftSwap;
use App\Support\ShiftSwapAnalysis;
use App\Support\ShiftSwapReceipt;
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
        $this->authorize('reject', $swap);
        if (method_exists($this, 'closeReallocation')) {
            $this->closeReallocation();
        }
        $this->cancelDecision();
        $this->pendingDecisionSummary = app(ShiftSwapAnalysis::class)->make($swap, auth()->user());
        $this->decisionSnapshot = $this->pendingDecisionSummary['fingerprint'];
        unset($this->pendingDecisionSummary['fingerprint']);
        $this->pendingDecisionSwapId = $id;
    }

    public function requestDecision(int $shiftSwapId, string $decision): void
    {
        abort_unless(in_array($decision, ['approve', 'reject'], true), 404);
        // Keep the reviewed snapshot when choosing the decision, so changed alerts
        // cannot be silently accepted without another analysis.
        if ($this->pendingDecisionSwapId !== $shiftSwapId) {
            $this->openSwapAnalysis($shiftSwapId);
        }
        $this->authorize($decision, ShiftSwap::query()->findOrFail($shiftSwapId));
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
        } catch (AuthorizationException) {
            $this->authorize('viewAny', ShiftSwap::class);
            $this->addError('decision', 'A decisão não está mais disponível. Feche e abra a análise novamente.');

            return;
        }
        $event = $this->pendingDecision === 'approve' ? ShiftSwapEvent::Approved : ShiftSwapEvent::Rejected;
        $this->swapReceipt = $receipts->make($swap, $event);
        $this->cancelDecision();
        $this->dispatch('notifications-updated');
        session()->flash('status', $event === ShiftSwapEvent::Approved
            ? 'Troca aprovada e plantão transferido ao substituto.'
            : 'Solicitação de troca rejeitada e comprovante gerado.');
    }

    public function cancelDecision(): void
    {
        $this->reset(['pendingDecisionSwapId', 'pendingDecision', 'pendingDecisionSummary', 'decisionSnapshot', 'rejectionReason']);
        $this->resetValidation();
    }
}
