<?php

namespace App\Livewire\Professional;

use App\Actions\Absences\ReportAbsenceAction;
use App\Actions\ShiftSwaps\AcceptShiftSwapAction;
use App\Actions\ShiftSwaps\CreateShiftSwapAction;
use App\Enums\ShiftSwapEvent;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Http\Requests\ReportAbsenceRequest;
use App\Models\Absence;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Support\ShiftSwapReceipt;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

#[Layout('layouts.app', ['title' => 'Minha escala'])]
class MySchedule extends Component
{
    use WithFileUploads;

    #[Url]
    public string $month = '';

    public ?int $swapShiftId = null;

    public string $substituteId = '';

    public ?int $absenceShiftId = null;

    public string $absenceReason = '';

    public $attachment = null;

    /** @var array<string, mixed>|null */
    public ?array $swapReceipt = null;

    public function mount(): void
    {
        $this->month = $this->normalizeMonth($this->month);
    }

    public function updatedMonth(): void
    {
        $this->month = $this->normalizeMonth($this->month);
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
        $this->absenceShiftId = null;
        $this->resetValidation();
        unset($this->eligibleSubstitutes);
    }

    public function requestSwap(CreateShiftSwapAction $action, ShiftSwapReceipt $receipts): void
    {
        $this->authorize('create', ShiftSwap::class);
        $shiftSwap = $action->execute(auth()->user(), [
            'shift_id' => $this->swapShiftId,
            'substitute_id' => $this->substituteId,
        ]);
        $this->swapReceipt = $receipts->make($shiftSwap, ShiftSwapEvent::Requested);

        $this->swapShiftId = null;
        $this->substituteId = '';
        unset($this->eligibleSubstitutes);
        session()->flash('status', 'Solicitação enviada ao profissional selecionado.');
    }

    public function acceptSwap(int $shiftSwapId, AcceptShiftSwapAction $action, ShiftSwapReceipt $receipts): void
    {
        $shiftSwap = ShiftSwap::query()->findOrFail($shiftSwapId);
        $this->authorize('accept', $shiftSwap);
        $shiftSwap = $action->execute(auth()->user(), $shiftSwapId);
        $this->swapReceipt = $receipts->make($shiftSwap, ShiftSwapEvent::Accepted);

        session()->flash('status', 'Troca aceita e encaminhada para aprovação da gerência.');
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
        session()->flash('status', 'Ausência registrada. O plantão agora está descoberto.');
    }

    public function cancelAction(): void
    {
        $this->reset(['swapShiftId', 'substituteId', 'absenceShiftId', 'absenceReason', 'attachment']);
        $this->resetValidation();
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
        [$year, $month] = array_pad(explode('-', $this->month), 2, null);
        $swapLimitReached = auth()->user()->hasReachedSwapLimit(now()->month, now()->year);

        $shifts = Shift::query()
            ->with(['category', 'sector', 'swaps'])
            ->where('user_id', auth()->id())
            ->when($year && $month, fn ($query) => $query->whereYear('date', $year)->whereMonth('date', $month))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $incomingSwaps = ShiftSwap::query()
            ->with(['shift.category', 'shift.sector', 'requester'])
            ->where('substitute_id', auth()->id())
            ->where('status', ShiftSwapStatus::PendingProfessional)
            ->oldest()
            ->get();

        $mySwaps = ShiftSwap::query()
            ->with(['shift', 'substitute'])
            ->where('requester_id', auth()->id())
            ->latest()
            ->limit(8)
            ->get();

        return view('livewire.professional.my-schedule', compact(
            'shifts',
            'incomingSwaps',
            'mySwaps',
            'swapLimitReached',
        ));
    }
}
