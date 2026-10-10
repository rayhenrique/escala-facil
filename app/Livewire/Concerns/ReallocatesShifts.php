<?php

namespace App\Livewire\Concerns;

use App\Actions\Shifts\ReallocateShiftAction;
use App\Http\Requests\ReallocateShiftRequest;
use App\Models\Shift;
use App\Models\User;
use App\Support\ShiftReallocation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

trait ReallocatesShifts
{
    #[Locked]
    public ?int $reallocationShiftId = null;

    #[Locked]
    public ?array $reallocationSummary = null;

    #[Locked]
    public array $reallocationCandidates = [];

    #[Locked]
    public ?array $reallocationPreview = null;

    #[Locked]
    public ?int $reallocationPreviewUserId = null;

    public string $reallocationUserId = '';

    public function openReallocation(int $id): void
    {
        $shift = Shift::query()->with(['sector', 'category', 'user'])->findOrFail($id);
        $this->authorize('reallocate', $shift);
        if (method_exists($this, 'cancelDecision')) {
            $this->cancelDecision();
        }
        $this->closeReallocation();
        $this->reallocationShiftId = $id;
        $candidates = app(ShiftReallocation::class)->candidates($shift);
        $this->reallocationCandidates = $candidates['professionals'];
        $this->reallocationSummary = [
            'period' => $shift->period()->datedLabel(),
            'location' => $shift->sector->name.' · '.$shift->category->name,
            'previous' => $shift->user?->name ?? 'Sem profissional',
            'unavailable' => $candidates['unavailable'],
            'signature' => app(ShiftReallocation::class)->shiftSignature($shift),
        ];
    }

    public function reviewReallocation(): void
    {
        abort_unless($this->reallocationShiftId, 404);
        $shift = Shift::query()->findOrFail($this->reallocationShiftId);
        $this->authorize('reallocate', $shift);
        if (! hash_equals($this->reallocationSummary['signature'], app(ShiftReallocation::class)->shiftSignature($shift))) {
            throw ValidationException::withMessages(['reallocation' => 'O plantão mudou. Feche e abra a realocação novamente para conferir os dados.']);
        }
        $payload = (new ReallocateShiftRequest)->validatePayload(['reallocationUserId' => $this->reallocationUserId], auth()->user());
        $professional = User::query()->findOrFail($payload['reallocationUserId']);
        $this->reallocationPreview = app(ShiftReallocation::class)->preview($shift, $professional);
        $this->reallocationPreviewUserId = $professional->id;
        $this->resetValidation();
    }

    public function returnToReallocation(): void
    {
        $this->reset(['reallocationPreview', 'reallocationPreviewUserId']);
        $this->resetValidation();
    }

    public function confirmReallocation(ReallocateShiftAction $action): void
    {
        abort_unless($this->reallocationShiftId && $this->reallocationPreview && $this->reallocationPreviewUserId, 404);
        try {
            $audit = $action->execute(auth()->user(), $this->reallocationShiftId,
                $this->reallocationPreviewUserId, $this->reallocationPreview['fingerprint']);
        } catch (AuthorizationException) {
            $this->authorize('viewAny', Shift::class);
            $this->addError('reallocation', 'Este plantão não está mais disponível para realocação. Feche e consulte a escala novamente.');

            return;
        }
        $this->closeReallocation();
        $this->dispatch('shift-reallocated', shiftId: $audit->shift_id);
        session()->flash('status', 'Plantão preenchido por '.$audit->after['professional'].'. Realocação registrada em '.$audit->performed_at->format('d/m/Y H:i').' por '.$audit->manager_name.'.');
    }

    public function closeReallocation(): void
    {
        $this->reset(['reallocationShiftId', 'reallocationSummary', 'reallocationCandidates', 'reallocationPreview', 'reallocationPreviewUserId', 'reallocationUserId']);
        $this->resetValidation();
    }
}
