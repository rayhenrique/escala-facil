<?php

namespace App\Livewire\Professional;

use App\Actions\Absences\ReportAbsenceAction;
use App\Actions\ShiftSwaps\AcceptShiftSwapAction;
use App\Actions\ShiftSwaps\CreateShiftSwapAction;
use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapEvent;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Http\Requests\ReportAbsenceRequest;
use App\Http\Requests\StoreShiftSwapRequest;
use App\Livewire\Concerns\FiltersOperationalLists;
use App\Models\Absence;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Support\OperationalFilters;
use App\Support\ShiftCoverage;
use App\Support\ShiftSwapReceipt;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Minha escala'])]
class MySchedule extends Component
{
    use FiltersOperationalLists;
    use WithFileUploads;
    use WithPagination;

    #[Url]
    public string $filter = '';

    #[Url]
    public string $swapStatusFilter = '';

    public function updatedFilter(): void
    {
        $this->resetPage('shiftsPage');
    }

    public function updatedSwapStatusFilter(): void
    {
        $this->resetPage('swapsPage');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['month', 'search', 'filterCategoryId', 'dateFrom', 'dateTo'], true)) {
            foreach (['shiftsPage', 'swapsPage', 'incomingPage'] as $page) {
                $this->resetPage($page);
            }
        }
    }

    public function clearFilters(): void
    {
        $this->clearListFilters();
        $this->filter = '';
        $this->swapStatusFilter = '';
        foreach (['shiftsPage', 'swapsPage', 'incomingPage'] as $page) {
            $this->resetPage($page);
        }
    }

    #[Computed]
    public function sectors(): Collection
    {
        return Sector::query()->whereKey(auth()->user()->sector_id ?? 0)->get();
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->orderBy('name')->get();
    }

    #[Url]
    public string $month = '';

    #[Locked]
    public ?int $detailShiftId = null;

    #[Computed]
    public function detailShift(): ?Shift
    {
        return $this->detailShiftId === null ? null : Shift::query()
            ->with(['sector', 'category'])
            ->where('user_id', auth()->id())->findOrFail($this->detailShiftId);
    }

    public function showShiftDetails(int $shiftId): void
    {
        $this->detailShiftId = Shift::query()->where('user_id', auth()->id())->findOrFail($shiftId)->id;
        unset($this->detailShift);
    }

    public function closeShiftDetails(): void
    {
        $this->detailShiftId = null;
        unset($this->detailShift);
    }

    public ?int $swapShiftId = null;

    public string $substituteId = '';

    public string $substituteShiftId = '';

    public ?int $absenceShiftId = null;

    public string $absenceReason = '';

    public $attachment = null;

    /** @var array<string, mixed>|null */
    public ?array $swapReceipt = null;

    public ?string $pendingConfirmation = null;

    public ?int $pendingAcceptSwapId = null;

    /** @var array<string, string|null>|null */
    public ?array $confirmationSummary = null;

    public function mount(): void
    {
        $this->month = $this->normalizeMonth($this->month);
    }

    public function updatedMonth(): void
    {
        $this->month = $this->normalizeMonth($this->month);
    }

    public function updatedSubstituteId(): void
    {
        $this->substituteShiftId = '';
    }

    #[Computed]
    public function eligibleSubstituteShifts(): Collection
    {
        if (! $this->swapShiftId || ! $this->substituteId) {
            return new Collection;
        }

        $shift = Shift::query()
            ->where('user_id', auth()->id())
            ->find($this->swapShiftId);

        if (! $shift) {
            return new Collection;
        }

        return Shift::query()
            ->with(['sector', 'category'])
            ->where('user_id', $this->substituteId)
            ->whereDate('date', '>', today())
            ->where('status', ShiftStatus::Scheduled)
            ->where('sector_id', $shift->sector_id)
            ->where('category_id', $shift->category_id)
            ->whereDoesntHave('swaps', fn ($query) => $query->whereIn('status', [
                ShiftSwapStatus::PendingProfessional,
                ShiftSwapStatus::PendingManager,
                ShiftSwapStatus::Approved,
            ]))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }

    #[Computed]
    public function eligibleSubstitutes(): Collection
    {
        if (! $this->swapShiftId) {
            return new Collection;
        }

        $user = auth()->user();
        $shift = Shift::query()
            ->where('user_id', $user->id)
            ->find($this->swapShiftId);

        if (! $shift) {
            return new Collection;
        }

        return User::query()
            ->where('role', UserRole::Professional)
            ->where('id', '!=', $user->id)
            ->where('category_id', $shift->category_id)
            ->where('sector_id', $shift->sector_id)
            ->withCount([
                'requestedSwaps as approved_requested_swaps_count' => fn ($query) => $query
                    ->where('status', ShiftSwapStatus::Approved)
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year),
                'substituteSwaps as approved_substitute_swaps_count' => fn ($query) => $query
                    ->where('status', ShiftSwapStatus::Approved)
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year),
            ])
            ->orderBy('name')
            ->get()
            ->filter(fn (User $professional): bool => $professional->approved_requested_swaps_count + $professional->approved_substitute_swaps_count
                < User::MONTHLY_SWAP_LIMIT
            )
            ->values();
    }

    public function openSwap(int $shiftId): void
    {
        $shift = Shift::query()->where('user_id', auth()->id())->findOrFail($shiftId);
        $this->authorize('create', ShiftSwap::class);

        $this->swapShiftId = $shift->id;
        $this->substituteId = '';
        $this->substituteShiftId = '';
        $this->absenceShiftId = null;
        $this->resetValidation();
        unset($this->eligibleSubstitutes);
        unset($this->eligibleSubstituteShifts);
        $this->dispatch('professional-form-opened', target: 'swap-form-title');
    }

    public function requestSwap(CreateShiftSwapAction $action, ShiftSwapReceipt $receipts): void
    {
        $this->authorize('create', ShiftSwap::class);
        $shiftSwap = $action->execute(auth()->user(), [
            'shift_id' => $this->swapShiftId,
            'substitute_id' => $this->substituteId,
            'substitute_shift_id' => $this->substituteShiftId ?: null,
        ]);
        $this->swapReceipt = $receipts->make($shiftSwap, ShiftSwapEvent::Requested);

        $this->swapShiftId = null;
        $this->substituteId = '';
        $this->substituteShiftId = '';
        $this->cancelConfirmation();
        unset($this->eligibleSubstitutes);
        unset($this->eligibleSubstituteShifts);
        session()->flash('status', 'Solicitação enviada ao profissional selecionado.');
    }

    public function reviewSwapRequest(): void
    {
        $this->authorize('create', ShiftSwap::class);
        $validated = (new StoreShiftSwapRequest)->validatePayload([
            'shift_id' => $this->swapShiftId,
            'substitute_id' => $this->substituteId,
            'substitute_shift_id' => $this->substituteShiftId ?: null,
        ], auth()->user());

        $shift = Shift::query()->with(['sector', 'category'])->findOrFail($validated['shift_id']);
        $substitute = User::query()->findOrFail($validated['substitute_id']);
        $counterpart = ! empty($validated['substitute_shift_id'])
            ? Shift::query()->with(['sector', 'category'])->find($validated['substitute_shift_id'])
            : null;

        $this->pendingConfirmation = 'request_swap';
        $this->confirmationSummary = $this->shiftSummary($shift) + [
            'substitute' => $substitute->name,
            'counterpart' => $counterpart ? $this->shiftSummary($counterpart)['shift'] : null,
        ];
    }

    public function confirmSwapRequest(CreateShiftSwapAction $action, ShiftSwapReceipt $receipts): void
    {
        abort_unless($this->pendingConfirmation === 'request_swap', 404);
        $this->requestSwap($action, $receipts);
    }

    public function acceptSwap(int $shiftSwapId, AcceptShiftSwapAction $action, ShiftSwapReceipt $receipts): void
    {
        $shiftSwap = ShiftSwap::query()->findOrFail($shiftSwapId);
        $this->authorize('accept', $shiftSwap);
        $shiftSwap = $action->execute(auth()->user(), $shiftSwapId);
        $this->swapReceipt = $receipts->make($shiftSwap, ShiftSwapEvent::Accepted);

        $this->cancelConfirmation();
        session()->flash('status', 'Troca aceita e encaminhada para aprovação da gerência.');
    }

    public function requestAcceptSwap(int $shiftSwapId): void
    {
        $shiftSwap = ShiftSwap::query()
            ->with(['shift.sector', 'shift.category', 'substituteShift.sector', 'substituteShift.category', 'requester'])
            ->findOrFail($shiftSwapId);
        $this->authorize('accept', $shiftSwap);

        $this->pendingAcceptSwapId = $shiftSwap->id;
        $this->pendingConfirmation = 'accept_swap';
        $this->confirmationSummary = $this->shiftSummary($shiftSwap->shift) + [
            'requester' => $shiftSwap->requester->name,
            'counterpart' => $shiftSwap->substituteShift ? $this->shiftSummary($shiftSwap->substituteShift)['shift'] : null,
        ];

        $doubleShift = ShiftCoverage::findDoubleShift(auth()->user(), $shiftSwap->shift);

        if ($doubleShift) {
            $this->confirmationSummary['double_shift'] = ShiftCoverage::doubleShiftLabel($doubleShift);
        }
    }

    public function confirmAcceptSwap(AcceptShiftSwapAction $action, ShiftSwapReceipt $receipts): void
    {
        abort_unless($this->pendingConfirmation === 'accept_swap' && $this->pendingAcceptSwapId, 404);
        $this->acceptSwap($this->pendingAcceptSwapId, $action, $receipts);
    }

    public function openAbsence(int $shiftId): void
    {
        $shift = Shift::query()->where('user_id', auth()->id())->findOrFail($shiftId);
        $this->authorize('create', [Absence::class, $shift]);

        $this->absenceShiftId = $shift->id;
        $this->swapShiftId = null;
        $this->absenceReason = '';
        $this->attachment = null;
        $this->resetValidation();
        $this->dispatch('professional-form-opened', target: 'absence-form-title');
    }

    public function reportAbsence(ReportAbsenceAction $action): void
    {
        $shift = Shift::query()->findOrFail($this->absenceShiftId);
        $this->authorize('create', [Absence::class, $shift]);

        $payload = [
            'shift_id' => $shift->id,
            'reason' => $this->absenceReason,
            'attachment' => $this->attachment,
        ];

        (new ReportAbsenceRequest)->validatePayload($payload, auth()->user());
        $action->execute(auth()->user(), $payload);

        $this->absenceShiftId = null;
        $this->absenceReason = '';
        $this->attachment = null;
        $this->cancelConfirmation();
        session()->flash('status', 'Ausência registrada. O plantão agora está descoberto.');
    }

    public function reviewAbsence(): void
    {
        $shift = Shift::query()->with(['sector', 'category'])->findOrFail($this->absenceShiftId);
        $this->authorize('create', [Absence::class, $shift]);

        (new ReportAbsenceRequest)->validatePayload([
            'shift_id' => $shift->id,
            'reason' => $this->absenceReason,
            'attachment' => $this->attachment,
        ], auth()->user());

        $this->pendingConfirmation = 'absence';
        $this->confirmationSummary = $this->shiftSummary($shift) + [
            'reason' => $this->absenceReason,
        ];
    }

    public function confirmAbsence(ReportAbsenceAction $action): void
    {
        abort_unless($this->pendingConfirmation === 'absence', 404);
        $this->reportAbsence($action);
    }

    public function cancelConfirmation(): void
    {
        $this->reset(['pendingConfirmation', 'pendingAcceptSwapId', 'confirmationSummary']);
    }

    public function cancelAction(): void
    {
        $this->reset(['swapShiftId', 'substituteId', 'substituteShiftId', 'absenceShiftId', 'absenceReason', 'attachment']);
        $this->cancelConfirmation();
        $this->resetValidation();
    }

    /** @return array<string, string> */
    private function shiftSummary(Shift $shift): array
    {
        return [
            'shift' => sprintf(
                '%s, das %s às %s',
                $shift->date->format('d/m/Y'),
                substr((string) $shift->start_time, 0, 5),
                $shift->period()->endLabel().' · '.$shift->period()->durationLabel(),
            ),
            'location' => ($shift->sector?->name ?? 'Setor não informado').' · '.($shift->category?->name ?? 'Categoria não informada'),
        ];
    }

    private function normalizeMonth(string $month): string
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return now()->format('Y-m');
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01');

        return $date !== false && $date->format('Y-m') === $month
            ? $month
            : now()->format('Y-m');
    }

    public function render(): View
    {
        $start = CarbonImmutable::parse($this->month.'-01');
        $this->filterSectorId = (string) (auth()->user()->sector_id ?? '');
        $filters = $this->listFilters();
        $validFilters = $this->validListFilters();
        $validShiftStatus = $this->filter === '' || ShiftStatus::tryFrom($this->filter);
        $validSwapStatus = $this->swapStatusFilter === '' || ShiftSwapStatus::tryFrom($this->swapStatusFilter);
        $swapLimitReached = auth()->user()->hasReachedSwapLimit(now()->month, now()->year);

        // Independent of history filters: bounded, current assignments only.
        $nextShift = Shift::query()->with(['sector', 'category'])
            ->where('user_id', auth()->id())->where('status', ShiftStatus::Scheduled)
            ->where(function ($query): void {
                $query->whereDate('date', '>', today()->toDateString())
                    ->orWhere(fn ($today) => $today->whereDate('date', today()->toDateString())
                        ->whereTime('start_time', '>=', now()->format('H:i:s')));
            })->orderBy('date')->orderBy('start_time')->orderBy('id')->first();
        $nextHasActiveSwap = $nextShift && ShiftSwap::query()
            ->where(fn ($query) => $query->where('shift_id', $nextShift->id)->orWhere('substitute_shift_id', $nextShift->id))
            ->whereIn('status', [ShiftSwapStatus::PendingProfessional, ShiftSwapStatus::PendingManager, ShiftSwapStatus::Approved])->exists();
        $nextSwapReason = null;
        if ($nextShift) {
            $nextSwapReason = match (true) {
                ! $nextShift->date->isAfter(today()) => 'Trocas só podem ser solicitadas para plantões após o dia de hoje.',
                $swapLimitReached => 'A cota mensal de quatro trocas aprovadas foi atingida.',
                ! auth()->user()->can('create', ShiftSwap::class) => 'Complete o setor e a categoria do seu cadastro com a gerência.',
                $nextShift->sector_id !== auth()->user()->sector_id || $nextShift->category_id !== auth()->user()->category_id => 'O setor ou a categoria do plantão difere do seu cadastro. Consulte a gerência antes de solicitar troca.',
                $nextHasActiveSwap => 'Este plantão já possui uma troca em andamento ou aprovada. Consulte suas solicitações.',
                default => null,
            };
        }
        $latestAbsence = Absence::query()->with('shift')->where('user_id', auth()->id())
            ->latest()->orderByDesc('id')->first();

        $shifts = Shift::query()
            ->with(['category', 'sector', 'swaps'])
            ->where('user_id', auth()->id())
            ->when(! $validFilters || ! $validShiftStatus, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($validFilters, fn ($q) => OperationalFilters::shifts($q, $filters))
            ->when($this->dateFrom === '' && $this->dateTo === '', fn ($q) => $q->where('date', '>=', $start->format('Y-m-d'))->where('date', '<', $start->addMonth()->format('Y-m-d')))
            ->when($this->filter !== '' && $validShiftStatus, fn ($q) => $q->where('status', $this->filter))
            ->orderBy('date')
            ->orderBy('start_time')->orderBy('id')
            ->paginate(10, ['*'], 'shiftsPage');

        $shiftIds = $shifts->pluck('id')->all();
        $approvedSwaps = ! empty($shiftIds)
            ? ShiftSwap::query()
                ->where('status', ShiftSwapStatus::Approved)
                ->with(['requester', 'substitute'])
                ->where(function ($query) use ($shiftIds): void {
                    $query->whereIn('shift_id', $shiftIds)
                        ->orWhereIn('substitute_shift_id', $shiftIds);
                })
                ->get()
            : collect();

        $swappedShiftsDetails = [];
        foreach ($approvedSwaps as $swap) {
            $requesterName = $swap->requester?->name ?? 'Profissional';
            $substituteName = $swap->substitute?->name ?? 'Profissional';
            $isMutual = $swap->substitute_shift_id !== null;

            if ($swap->shift_id && in_array($swap->shift_id, $shiftIds, true)) {
                $headline = $isMutual
                    ? "Troca mútua realizada entre {$requesterName} e {$substituteName}"
                    : "Troca realizada: {$requesterName} cedeu para {$substituteName}";

                $tooltip = $isMutual
                    ? "Troca mútua realizada entre {$requesterName} e {$substituteName} (Solicitante: {$requesterName} ⇄ Substituto: {$substituteName})"
                    : "Troca realizada: {$requesterName} trocou com {$substituteName} (Solicitante: {$requesterName} → Substituto: {$substituteName})";

                $swappedShiftsDetails[$swap->shift_id] = [
                    'requester_name' => $requesterName,
                    'substitute_name' => $substituteName,
                    'is_mutual' => $isMutual,
                    'headline' => $headline,
                    'tooltip' => $tooltip,
                ];
            }

            if ($swap->substitute_shift_id && in_array($swap->substitute_shift_id, $shiftIds, true)) {
                $headline = "Troca mútua realizada entre {$requesterName} e {$substituteName}";
                $tooltip = "Troca mútua realizada entre {$requesterName} e {$substituteName} (Solicitante: {$requesterName} ⇄ Substituto: {$substituteName})";

                $swappedShiftsDetails[$swap->substitute_shift_id] = [
                    'requester_name' => $requesterName,
                    'substitute_name' => $substituteName,
                    'is_mutual' => true,
                    'headline' => $headline,
                    'tooltip' => $tooltip,
                ];
            }
        }

        $incomingSwaps = ShiftSwap::query()
            ->with(['shift.category', 'shift.sector', 'substituteShift.category', 'substituteShift.sector', 'requester'])
            ->where('substitute_id', auth()->id())
            ->where('status', ShiftSwapStatus::PendingProfessional)
            ->when(! $validFilters, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($validFilters, fn ($q) => OperationalFilters::swaps($q, $filters))
            ->oldest()->orderBy('id')
            ->paginate(10, ['*'], 'incomingPage');

        $sentSwaps = ShiftSwap::query()->with(['shift.sector', 'shift.category', 'substituteShift', 'substitute'])
            ->where('requester_id', auth()->id())->whereIn('status', [ShiftSwapStatus::PendingProfessional, ShiftSwapStatus::PendingManager])
            ->latest()->orderByDesc('id')->limit(3)->get();

        $pendingResponses = ShiftSwap::query()->where('substitute_id', auth()->id())->where('status', ShiftSwapStatus::PendingProfessional)->count();

        $mySwaps = ShiftSwap::query()
            ->with(['shift.sector', 'shift.category', 'substituteShift.sector', 'substituteShift.category', 'substitute', 'requester'])
            ->where(function ($query) {
                $query->where('requester_id', auth()->id())
                    ->orWhere('substitute_id', auth()->id());
            })
            ->when(! $validFilters || ! $validSwapStatus, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($validFilters, fn ($q) => OperationalFilters::swaps($q, $filters))
            ->when($this->swapStatusFilter !== '' && $validSwapStatus, fn ($q) => $q->where('status', $this->swapStatusFilter))
            ->latest()->orderByDesc('id')
            ->paginate(8, ['*'], 'swapsPage');

        return view('livewire.professional.my-schedule', compact(
            'sentSwaps',
            'pendingResponses',
            'nextShift',
            'nextSwapReason',
            'latestAbsence',
            'shifts',
            'incomingSwaps',
            'mySwaps',
            'swapLimitReached',
            'swappedShiftsDetails',
        ));
    }
}
