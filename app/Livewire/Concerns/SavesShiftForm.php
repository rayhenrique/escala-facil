<?php

namespace App\Livewire\Concerns;

use App\Actions\Shifts\CreateShiftAction;
use App\Actions\Shifts\UpdateShiftAction;
use App\Http\Requests\SaveShiftRequest;
use App\Models\Shift;
use App\Support\ShiftAssignmentAnalysis;
use App\Support\ShiftReallocation;
use App\Support\UiFeedback;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

trait SavesShiftForm
{
    #[Locked]
    public ?int $editingShiftId = null;

    #[Locked]
    public ?string $editingShiftSnapshot = null;

    #[Locked]
    public bool $showFormModal = false;

    #[Locked]
    public ?array $shiftPreview = null;

    public string $date = '';

    public string $startTime = '07:00';

    public string $endTime = '19:00';

    public string $endDate = '';

    abstract protected function payload(?Shift $shift): array;

    abstract protected function shiftBeingEdited(int $shiftId): Shift;

    abstract public function resetForm(): void;

    public function save(UpdateShiftAction $action): void
    {
        $shift = $this->editingShiftId ? $this->shiftBeingEdited($this->editingShiftId) : null;
        $this->authorize($shift ? 'update' : 'create', $shift ?? Shift::class);

        if ($shift && (! $this->editingShiftSnapshot || ! hash_equals($this->editingShiftSnapshot, app(ShiftReallocation::class)->shiftSignature($shift)))) {
            throw ValidationException::withMessages(['shift' => 'Este plantão foi alterado por outra operação. Feche o formulário e abra o registro novamente antes de salvar.']);
        }

        $payload = $this->payload($shift);
        $validated = (new SaveShiftRequest)->validatePayload($payload, auth()->user());
        $candidate = new Shift($validated);
        if ($shift) {
            $candidate->id = $shift->id;
        }
        $analysis = app(ShiftAssignmentAnalysis::class)->preview($candidate);
        $expected = $this->shiftPreview['fingerprint'] ?? null;
        if (($analysis['warnings'] !== [] && $expected === null) || ($expected !== null && ! hash_equals($expected, $analysis['fingerprint']))) {
            $this->shiftPreview = $analysis;
            $this->resetValidation();
            $this->dispatch('shift-alerts-ready');

            return;
        }

        if ($shift) {
            abort_unless($this->editingShiftSnapshot, 404);
            $action->execute(auth()->user(), $shift->id, $payload, $this->editingShiftSnapshot, $expected);
            UiFeedback::success('Plantão atualizado com sucesso.');
        } else {
            app(CreateShiftAction::class)->execute(auth()->user(), $payload, $expected);
            UiFeedback::success('Plantão criado com sucesso.');
        }

        $this->resetForm();
    }

    public function returnToShiftForm(): void
    {
        $this->shiftPreview = null;
        $this->resetValidation();
    }

    private function fillShiftForm(Shift $shift): void
    {
        $this->editingShiftId = $shift->id;
        $this->editingShiftSnapshot = app(ShiftReallocation::class)->shiftSignature($shift);
        $this->date = $shift->date->format('Y-m-d');
        $this->startTime = substr((string) $shift->start_time, 0, 5);
        $this->endTime = substr((string) $shift->end_time, 0, 5);
        $this->endDate = $shift->period()->end->format('Y-m-d');
        $this->showFormModal = true;
        $this->shiftPreview = null;
        $this->resetValidation();
    }
}
