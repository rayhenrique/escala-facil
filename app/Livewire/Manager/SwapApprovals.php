<?php

namespace App\Livewire\Manager;

use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Actions\ShiftSwaps\RevertShiftSwapAction;
use App\Enums\ShiftSwapEvent;
use App\Enums\ShiftSwapStatus;
use App\Http\Requests\ManagerSwapFiltersRequest;
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
use App\Support\ShiftSwapReversion;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Trocas'])]
class SwapApprovals extends Component
{
    use AnalyzesShiftSwaps;
    use FiltersOperationalLists;
    use HasOperationalContext;
    use WithPagination;

    #[Url]
    public $request = null;

    #[Url(keep: true)]
    public string $swapStatusFilter = 'pending_manager';

    #[Url]
    public string $listView = 'pending';

    #[Url(keep: true)]
    public string $sort = 'oldest';

    #[Url]
    public string $requesterSearch = '';

    #[Url]
    public string $substituteSearch = '';

    #[Locked]
    public ?int $pendingRevertSwapId = null;

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $pendingRevertSummary = null;

    #[Locked]
    public ?string $reversionSnapshot = null;

    public string $reversionReason = '';

    #[Locked]
    public bool $administrativeAnalysis = false;

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
        if ($this->listView === 'history' && ! request()->query->has('swapStatusFilter')) {
            $this->swapStatusFilter = '';
        } elseif ($this->listView === 'pending' && $this->swapStatusFilter !== 'pending_manager') {
            $this->listView = 'history';
        }
        if ($this->listView === 'history' && ! request()->query->has('sort')) {
            $this->sort = 'newest';
        }
    }

    public function updatedSwapStatusFilter(): void
    {
        if ($this->swapStatusFilter !== 'pending_manager') {
            if ($this->listView === 'pending') {
                $this->sort = 'newest';
            }
            $this->listView = 'history';
        }
        $this->resetPage();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['requesterSearch', 'substituteSearch', 'sort', 'listView'], true)) {
            $this->resetPage();
        }
    }

    public function showHistory(): void
    {
        $this->listView = 'history';
        $this->swapStatusFilter = '';
        $this->sort = 'newest';
        $this->request = null;
        $this->resetPage();
    }

    public function showPending(): void
    {
        $this->listView = 'pending';
        $this->swapStatusFilter = 'pending_manager';
        $this->sort = 'oldest';
        $this->request = null;
        $this->resetPage();
    }

    public function listFilters(): array
    {
        return $this->only(['search', 'filterSectorId', 'filterCategoryId', 'dateFrom', 'dateTo', 'requesterSearch', 'substituteSearch']);
    }

    protected function validListFilters(): bool
    {
        $request = new ManagerSwapFiltersRequest;
        $payload = $this->listFilters() + $this->only(['swapStatusFilter', 'listView', 'sort']);
        $request->replace($payload);
        $validator = Validator::make($payload, $request->rules(), $request->messages());
        $this->resetValidation(array_keys($payload));
        foreach ($validator->errors()->messages() as $key => $messages) {
            $this->addError($key, $messages[0]);
        }

        return ! $validator->fails();
    }

    public function clearFilters(): void
    {
        $this->clearListFilters();
        if (! in_array($this->listView, ['pending', 'history'], true)) {
            $this->listView = 'pending';
        }
        $this->swapStatusFilter = $this->listView === 'history' ? '' : 'pending_manager';
        $this->sort = $this->listView === 'history' ? 'newest' : 'oldest';
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
        $shiftSwap = ShiftSwap::query()->findOrFail($shiftSwapId);
        $this->authorize('reviewReversion', $shiftSwap);
        $review = app(ShiftSwapReversion::class)->inspect($shiftSwap);
        $this->pendingRevertSwapId = $shiftSwap->id;
        $this->pendingRevertSummary = array_intersect_key($review, array_flip(['rows', 'blocks', 'warnings', 'can_revert', 'later_events']));
        $this->reversionSnapshot = $review['fingerprint'];
        $this->administrativeAnalysis = false;
        $this->resetValidation(['reversion', 'reversionReason', 'reversionSnapshot']);
    }

    public function analyzeAdministrativeCorrection(): void
    {
        abort_unless($this->pendingRevertSwapId, 404);
        $this->authorize('reviewReversion', ShiftSwap::query()->findOrFail($this->pendingRevertSwapId));
        $this->administrativeAnalysis = true;
    }

    public function refreshReversion(): void
    {
        abort_unless($this->pendingRevertSwapId, 404);
        $this->requestRevert($this->pendingRevertSwapId);
    }

    public function cancelRevert(): void
    {
        $this->reset(['pendingRevertSwapId', 'pendingRevertSummary', 'reversionSnapshot', 'reversionReason', 'administrativeAnalysis']);
        $this->resetValidation(['reversion', 'reversionReason', 'reversionSnapshot']);
    }

    public function confirmRevert(RevertShiftSwapAction $action, ShiftSwapReceipt $receipts): void
    {
        abort_unless($this->pendingRevertSwapId, 404);
        $shiftSwap = $action->execute(auth()->user(), $this->pendingRevertSwapId, $this->reversionReason, $this->reversionSnapshot);
        $this->swapReceipt = $receipts->make($shiftSwap, ShiftSwapEvent::Reverted);
        $this->cancelRevert();
        $this->dispatch('notifications-updated');
        session()->flash('status', 'Troca desfeita. Os profissionais voltaram aos plantões originais e receberam um novo comprovante.');
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
                'manager',
                'shift.user',
                'substituteShift.user',
                'shift' => fn ($query) => $query->withExists([
                    'swaps as pending_swaps_exists' => fn ($q) => $q->whereIn('status', [ShiftSwapStatus::PendingProfessional, ShiftSwapStatus::PendingManager]),
                    'absences',
                ]),
            ])
            ->when($this->request === null, function ($query) use ($validFilters): void {
                if (! $validFilters) {
                    $query->whereRaw('1 = 0');

                    return;
                }
                OperationalFilters::swaps($query, $this->listFilters());
                $query->when(trim($this->requesterSearch) !== '', fn ($q) => $q->whereHas('requester', fn ($user) => OperationalFilters::name($user, $this->requesterSearch)));
                $query->when(trim($this->substituteSearch) !== '', fn ($q) => $q->whereHas('substitute', fn ($user) => OperationalFilters::name($user, $this->substituteSearch)));
                if ($this->listView === 'pending') {
                    $query->where('status', ShiftSwapStatus::PendingManager);
                }
                $query->when($this->swapStatusFilter !== '', fn ($q) => $q->where('status', $this->swapStatusFilter));
            })
            ->when($this->request !== null, fn ($query) => $query->whereKey($this->request))
            ->orderBy('created_at', $this->sort === 'newest' ? 'desc' : 'asc')
            ->orderBy('id', $this->sort === 'newest' ? 'desc' : 'asc');

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

        $revertPermissions = [];
        foreach ($swaps as $swap) {
            if ($swap->status === ShiftSwapStatus::Approved) {
                $permission = Gate::inspect('reviewReversion', $swap);
                $revertPermissions[$swap->id] = ['allowed' => $permission->allowed(), 'reason' => $permission->message()];
            }
        }

        return view('livewire.manager.swap-approvals', [
            'swaps' => $swaps,
            'focusedSwap' => $focusedSwap,
            'doubleShifts' => $this->doubleShiftLabels($swaps->getCollection()->where('status', ShiftSwapStatus::PendingManager)),
            'revertPermissions' => $revertPermissions,
            'focusedReceipt' => $focusedSwap ? app(ShiftSwapReceipt::class)->current($focusedSwap) : null,
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
