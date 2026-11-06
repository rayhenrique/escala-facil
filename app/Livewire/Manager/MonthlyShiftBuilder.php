<?php

namespace App\Livewire\Manager;

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Livewire\Concerns\SavesShiftForm;
use App\Models\Shift;
use App\Models\User;
use App\Support\OperationalFilters;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Montar escala mensal'])]
class MonthlyShiftBuilder extends Component
{
    use SavesShiftForm;

    #[Url]
    public string $month = '';

    public string $professionalId = '';

    public string $professionalSearch = '';

    public function boot(): void
    {
        $this->authorize('viewAny', Shift::class);
    }

    public function mount(): void
    {
        $this->updatedMonth();
    }

    public function updatedMonth(): void
    {
        $this->month = $this->monthStart()->format('Y-m');
        $this->resetForm();
    }

    public function updatedProfessionalId(): void
    {
        unset($this->professional, $this->professionals);
        $this->resetForm();
    }

    public function previousMonth(): void
    {
        $this->month = $this->monthStart()->subMonth()->format('Y-m');
        $this->updatedMonth();
    }

    public function nextMonth(): void
    {
        $this->month = $this->monthStart()->addMonth()->format('Y-m');
        $this->updatedMonth();
    }

    public function currentMonth(): void
    {
        $this->month = now()->format('Y-m');
        $this->updatedMonth();
    }

    #[Computed]
    public function professionals(): Collection
    {
        $selected = $this->professional?->id;
        $term = mb_substr(trim($this->professionalSearch), 0, 100);

        return User::query()->where('role', UserRole::Professional)->with(['sector', 'category'])
            ->when($term !== '', fn (Builder $query) => $query->where(function (Builder $matches) use ($term, $selected): void {
                OperationalFilters::name($matches, $term);
                if ($selected) {
                    $matches->orWhereKey($selected);
                }
            }))
            ->when($selected, fn (Builder $query) => $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$selected]))
            ->orderBy('name')->orderBy('id')->limit(50)->get();
    }

    #[Computed]
    public function professional(): ?User
    {
        return ctype_digit($this->professionalId)
            ? User::query()->with(['sector', 'category'])->where('role', UserRole::Professional)->find($this->professionalId)
            : null;
    }

    public function openCreateModal(string $date): void
    {
        $this->authorize('create', Shift::class);
        $this->requireProfessional();
        abort_unless(str_starts_with($date, $this->month.'-'), 404);
        $this->resetForm();
        $this->date = $date;
        $this->showFormModal = true;
    }

    public function edit(int $shiftId): void
    {
        $this->requireProfessional();
        $shift = $this->shiftBeingEdited($shiftId);
        $this->authorize('update', $shift);
        $this->fillShiftForm($shift);
    }

    public function closeFormModal(): void
    {
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->reset(['editingShiftId', 'editingShiftSnapshot', 'shiftPreview', 'showFormModal', 'date', 'startTime', 'endTime', 'endDate']);
        $this->resetValidation();
    }

    protected function shiftBeingEdited(int $shiftId): Shift
    {
        return $this->monthlyShifts($this->requireProfessional())->findOrFail($shiftId);
    }

    protected function payload(?Shift $shift): array
    {
        abort_unless($this->showFormModal, 404);
        $professional = $this->requireProfessional();

        return [
            'date' => $this->date,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'end_date' => $this->endDate ?: null,
            'sector_id' => $shift?->sector_id ?? $professional->sector_id,
            'category_id' => $shift?->category_id ?? $professional->category_id,
            'user_id' => $professional->id,
            'status' => $shift?->status->value ?? ShiftStatus::Scheduled->value,
        ];
    }

    private function requireProfessional(): User
    {
        $professional = $this->professional;
        if (! $professional?->sector || ! $professional->category) {
            throw ValidationException::withMessages(['professionalId' => 'Selecione um profissional com setor e categoria vinculados.']);
        }

        return $professional;
    }

    private function monthStart(): CarbonImmutable
    {
        return preg_match('/^[1-9]\d{3}-(0[1-9]|1[0-2])$/D', $this->month)
            ? CarbonImmutable::createFromFormat('!Y-m', $this->month)
            : now()->toImmutable()->startOfMonth();
    }

    private function monthlyShifts(User $professional): Builder
    {
        $start = $this->monthStart();

        return $professional->shifts()->getQuery()
            ->where('date', '>=', $start->toDateString())
            ->where('date', '<', $start->addMonth()->toDateString());
    }

    public function render(): View
    {
        $professional = $this->professional;
        $start = $this->monthStart();
        $shifts = $professional ? $this->monthlyShifts($professional)->orderBy('date')->orderBy('start_time')->orderBy('id')->get() : collect();

        return view('livewire.manager.monthly-shift-builder', [
            'professional' => $professional,
            'monthStart' => $start,
            'shiftCount' => $shifts->count(),
            'shiftsByDay' => $shifts->groupBy(fn (Shift $shift) => $shift->date->day),
        ]);
    }
}
