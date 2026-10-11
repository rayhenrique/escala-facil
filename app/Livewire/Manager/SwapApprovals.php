<?php

namespace App\Livewire\Manager;

use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Actions\ShiftSwaps\RevertShiftSwapAction;
use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapEvent;
use App\Enums\ShiftSwapStatus;
use App\Livewire\Concerns\AnalyzesShiftSwaps;
use App\Livewire\Concerns\FiltersOperationalLists;
use App\Livewire\Concerns\HasOperationalContext;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Support\OperationalFilters;
use App\Support\OperationalNavigation;
use App\Support\ShiftCoverage;
use App\Support\ShiftSwapReceipt;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Aprovação de trocas'])]
class SwapApprovals extends Component
{
    use AnalyzesShiftSwaps;
    use FiltersOperationalLists;
    use HasOperationalContext;
    use WithPagination;

    #[Url]
    public $request = null;

    #[Url]
    public string $swapStatusFilter = 'pending_manager';

    public ?int $pendingRevertSwapId = null;

    /** @var array<string, string|null>|null */
    public ?array $pendingRevertSummary = null;

    #[Computed]
    public function sectors()
    {
        return Sector::query()->orderBy('name')->get();
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->orderBy('name')->get();
    }

    public function mount(): void
    {
        $this->authorize('viewAny', ShiftSwap::class);
    }

    public function updatedSwapStatusFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->clearListFilters();
        $this->swapStatusFilter = 'pending_manager';
        $this->request = null;
    }

    public function approve(int $shiftSwapId, DecideShiftSwapAction $action, ShiftSwapReceipt $receipts): void
    {
        $shiftSwap = ShiftSwap::query()->findOrFail($shiftSwapId);
        $this->authorize('approve', $shiftSwap);
        $shiftSwap = $action->approve(auth()->user(), $shiftSwapId);
        $this->swapReceipt = $receipts->make($shiftSwap, ShiftSwapEvent::Approved);

        session()->flash('status', 'Troca aprovada e plantão transferido ao substituto.');
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
                $shiftSwap->shift->period()->endLabel().' · '.$shiftSwap->shift->period()->durationLabel(),
            ),
            'location' => $shiftSwap->shift->sector->name.' · '.$shiftSwap->shift->category->name,
            'requester' => $shiftSwap->requester->name,
            'substitute' => $shiftSwap->substitute->name,
            'counterpart' => $shiftSwap->substituteShift ? sprintf(
                '%s, das %s às %s',
                $shiftSwap->substituteShift->date->format('d/m/Y'),
                substr((string) $shiftSwap->substituteShift->start_time, 0, 5),
                $shiftSwap->substituteShift->period()->endLabel().' · '.$shiftSwap->substituteShift->period()->durationLabel(),
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
        $this->authorize('viewAny', ShiftSwap::class);
        $this->request = OperationalNavigation::id($this->request);
        $validFilters = $this->validListFilters();
        $swaps = ShiftSwap::query()
            ->with([
                'shift.category',
                'shift.sector',
                'substituteShift.category',
                'substituteShift.sector',
                'requester',
                'substitute',
            ])
            ->when($this->request === null, function ($query) use ($validFilters): void {
                if (! $validFilters) {
                    $query->whereRaw('1 = 0');

                    return;
                }
                OperationalFilters::swaps($query, $this->listFilters());
                $query->when($this->swapStatusFilter !== '', fn ($q) => $q->where('status', $this->swapStatusFilter));
            })
            ->when($this->request !== null, fn ($query) => $query->whereKey($this->request))
            ->oldest()->orderBy('id');

        if ($this->request !== null) {
            $records = $swaps->get();
            $focusedSwap = $records->first();
            abort_unless($focusedSwap, 404);
            $this->authorize('view', $focusedSwap);
            $swaps = new LengthAwarePaginator($records, 1, 10);
        } else {
            $focusedSwap = null;
            $swaps = $swaps->paginate(10);
        }

        return view('livewire.manager.swap-approvals', [
            'swaps' => $swaps,
            'focusedSwap' => $focusedSwap,
            'doubleShifts' => $this->doubleShiftLabels($swaps->getCollection()->where('status', ShiftSwapStatus::PendingManager)),
            'approvedSwaps' => $this->request !== null || $this->swapStatusFilter !== 'pending_manager' || ! $validFilters ? collect() : OperationalFilters::swaps(ShiftSwap::query(), $this->listFilters())
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
                $swap->shift->date->addDay()->format('Y-m-d'),
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
