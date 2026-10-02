<?php

namespace App\Livewire\Manager;

use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Actions\ShiftSwaps\RevertShiftSwapAction;
use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapEvent;
use App\Enums\ShiftSwapStatus;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Support\ShiftCoverage;
use App\Support\ShiftSwapReceipt;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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

    public string $rejectionReason = '';

    /** @var array<string, string|null>|null */
    public ?array $pendingDecisionSummary = null;

    public ?int $pendingRevertSwapId = null;

    /** @var array<string, string|null>|null */
    public ?array $pendingRevertSummary = null;

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
            ->with(['shift.sector', 'shift.category', 'substituteShift.sector', 'substituteShift.category', 'requester', 'substitute'])
            ->findOrFail($shiftSwapId);
        $this->authorize($decision, $shiftSwap);

        $this->pendingDecisionSwapId = $shiftSwap->id;
        $this->pendingDecision = $decision;
        $this->rejectionReason = '';
        $this->resetValidation();

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
            'counterpart' => $shiftSwap->substituteShift ? sprintf(
                '%s, das %s às %s',
                $shiftSwap->substituteShift->date->format('d/m/Y'),
                substr((string) $shiftSwap->substituteShift->start_time, 0, 5),
                substr((string) $shiftSwap->substituteShift->end_time, 0, 5),
            ) : null,
            'counterpart_location' => $shiftSwap->substituteShift ? $shiftSwap->substituteShift->sector->name.' · '.$shiftSwap->substituteShift->category->name : null,
        ];

        $doubleShift = ShiftCoverage::findDoubleShift($shiftSwap->substitute, $shiftSwap->shift);

        if ($doubleShift) {
            $this->pendingDecisionSummary['double_shift'] = ShiftCoverage::doubleShiftLabel($doubleShift);
        }
    }

    public function confirmDecision(DecideShiftSwapAction $action, ShiftSwapReceipt $receipts): void
    {
        abort_unless($this->pendingDecisionSwapId && in_array($this->pendingDecision, ['approve', 'reject'], true), 404);

        $shiftSwapId = $this->pendingDecisionSwapId;
        $decision = $this->pendingDecision;

        if ($decision === 'approve') {
            $this->approve($shiftSwapId, $action, $receipts);
        } else {
            $this->validate([
                'rejectionReason' => ['required', 'string', 'min:3', 'max:1000'],
            ], [], [
                'rejectionReason' => 'justificativa',
            ]);

            $this->reject($shiftSwapId, $action, $receipts, $this->rejectionReason);
        }

        $this->cancelDecision();
    }

    public function cancelDecision(): void
    {
        $this->reset(['pendingDecisionSwapId', 'pendingDecision', 'pendingDecisionSummary', 'rejectionReason']);
        $this->resetValidation();
    }

    public function reject(int $shiftSwapId, DecideShiftSwapAction $action, ShiftSwapReceipt $receipts, ?string $reason = null): void
    {
        $shiftSwap = ShiftSwap::query()->findOrFail($shiftSwapId);
        $this->authorize('reject', $shiftSwap);
        $shiftSwap = $action->reject(auth()->user(), $shiftSwapId, $reason);
        $this->swapReceipt = $receipts->make($shiftSwap, ShiftSwapEvent::Rejected);

        session()->flash('status', 'Solicitação de troca rejeitada e comprovante gerado.');
    }

    public function requestRevert(int $shiftSwapId): void
    {
        $shiftSwap = ShiftSwap::query()
            ->with(['shift.sector', 'shift.category', 'substituteShift.sector', 'substituteShift.category', 'requester', 'substitute'])
            ->findOrFail($shiftSwapId);
        $this->authorize('revert', $shiftSwap);

        $this->pendingRevertSwapId = $shiftSwap->id;
        $this->pendingRevertSummary = [
            'shift' => sprintf(
                '%s, das %s às %s',
                $shiftSwap->shift->date->format('d/m/Y'),
                substr((string) $shiftSwap->shift->start_time, 0, 5),
                substr((string) $shiftSwap->shift->end_time, 0, 5),
            ),
            'location' => $shiftSwap->shift->sector->name.' · '.$shiftSwap->shift->category->name,
            'requester' => $shiftSwap->requester->name,
            'substitute' => $shiftSwap->substitute->name,
            'counterpart' => $shiftSwap->substituteShift ? sprintf(
                '%s, das %s às %s',
                $shiftSwap->substituteShift->date->format('d/m/Y'),
                substr((string) $shiftSwap->substituteShift->start_time, 0, 5),
                substr((string) $shiftSwap->substituteShift->end_time, 0, 5),
            ) : null,
        ];
    }

    public function cancelRevert(): void
    {
        $this->reset(['pendingRevertSwapId', 'pendingRevertSummary']);
    }

    public function confirmRevert(RevertShiftSwapAction $action, ShiftSwapReceipt $receipts): void
    {
        abort_unless($this->pendingRevertSwapId, 404);

        $shiftSwap = ShiftSwap::query()->findOrFail($this->pendingRevertSwapId);
        $this->authorize('revert', $shiftSwap);
        $shiftSwap = $action->execute(auth()->user(), $this->pendingRevertSwapId);
        $this->swapReceipt = $receipts->make($shiftSwap, ShiftSwapEvent::Reverted);

        $this->cancelRevert();

        session()->flash('status', 'Troca desfeita e plantão devolvido ao solicitante.');
    }

    public function render(): View
    {
        $swaps = ShiftSwap::query()
            ->with([
                'shift.category',
                'shift.sector',
                'substituteShift.category',
                'substituteShift.sector',
                'requester',
                'substitute',
            ])
            ->where('status', ShiftSwapStatus::PendingManager)
            ->oldest()
            ->paginate(10);

        return view('livewire.manager.swap-approvals', [
            'swaps' => $swaps,
            'doubleShifts' => $this->doubleShiftLabels($swaps->getCollection()),
            'approvedSwaps' => ShiftSwap::query()
                ->with([
                    'shift.category',
                    'shift.sector',
                    'substituteShift.category',
                    'substituteShift.sector',
                    'requester',
                    'substitute',
                ])
                ->where('status', ShiftSwapStatus::Approved)
                ->whereHas('shift', fn ($query) => $query
                    ->whereDate('date', '>=', today())
                    ->where('status', ShiftStatus::Scheduled))
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * @param  Collection<int, ShiftSwap>  $swaps
     * @return array<int, string>
     */
    private function doubleShiftLabels($swaps): array
    {
        if ($swaps->isEmpty()) {
            return [];
        }

        $dates = $swaps
            ->map(fn (ShiftSwap $swap): array => [
                $swap->shift->date->format('Y-m-d'),
                $swap->shift->date->subDay()->format('Y-m-d'),
            ])
            ->flatten()
            ->unique()
            ->all();

        $pool = Shift::query()
            ->whereIn('user_id', $swaps->pluck('substitute_id')->unique()->all())
            ->where(function ($query) use ($dates): void {
                foreach ($dates as $date) {
                    $query->orWhereDate('date', $date);
                }
            })
            ->get();

        $labels = [];

        foreach ($swaps as $swap) {
            $double = ShiftCoverage::findDoubleShiftIn($pool, $swap->substitute_id, $swap->shift);

            if ($double) {
                $labels[$swap->id] = ShiftCoverage::doubleShiftLabel($double);
            }
        }

        return $labels;
    }
}
