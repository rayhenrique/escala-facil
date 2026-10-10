<?php

namespace App\Livewire\Manager;

use App\Actions\Shifts\DuplicateMonthAction;
use App\Actions\Shifts\ImportShiftsAction;
use App\Actions\Shifts\UpdateShiftAction;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Http\Requests\ImportShiftsRequest;
use App\Http\Requests\SaveShiftRequest;
use App\Livewire\Concerns\HasOperationalContext;
use App\Livewire\Concerns\ReallocatesShifts;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftReallocation;
use App\Models\User;
use App\Support\ManagerAttention;
use App\Support\OperationalNavigation;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Gestão de escalas'])]
class ShiftManager extends Component
{
    use HasOperationalContext;
    use ReallocatesShifts;
    use WithFileUploads;
    use WithPagination;

    #[Url]
    public string $month = '';

    #[Url]
    public string $filter = '';

    #[Url]
    public $focus = null;

    #[Locked]
    public ?int $editingShiftId = null;

    #[Locked]
    public ?string $editingShiftSnapshot = null;

    public ?int $pendingDeleteId = null;

    public string $pendingDeleteLabel = '';

    public bool $showFormModal = false;

    public string $date = '';

    public string $startTime = '07:00';

    public string $endTime = '19:00';

    public string $endDate = '';

    public string $sectorId = '';

    public string $categoryId = '';

    public string $userId = '';

    public string $status = 'scheduled';

    public bool $showImportModal = false;

    public $importFile = null;

    /** @var list<string> */
    public array $importErrors = [];

    public bool $showCopyModal = false;

    public string $copySourceMonth = '';

    public string $copyTargetMonth = '';

    public string $copyMode = DuplicateMonthAction::MODE_DAY;

    /** @var array<string, mixed>|null */
    public ?array $copyPreview = null;

    /** @var list<int> */
    public array $copySelected = [];

    public string $copyError = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Shift::class);
        $this->month = $this->normalizeMonth($this->month);
        $this->date = today()->addDay()->format('Y-m-d');
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->orderBy('name')->get();
    }

    #[Computed]
    public function sectors(): Collection
    {
        return Sector::query()->orderBy('name')->get();
    }

    #[Computed]
    public function professionals(): Collection
    {
        return User::query()
            ->where('role', UserRole::Professional)
            ->when($this->categoryId !== '', fn ($query) => $query->where('category_id', $this->categoryId))
            ->when($this->sectorId !== '', fn ($query) => $query->where('sector_id', $this->sectorId))
            ->orderBy('name')
            ->get();
    }

    public function updatedMonth(): void
    {
        $this->month = $this->normalizeMonth($this->month);
        $this->focus = null;
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->focus = null;
        $this->resetPage();
    }

    public function updatedFocus(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryId(): void
    {
        $this->userId = '';
        unset($this->professionals);
    }

    public function updatedSectorId(): void
    {
        $this->userId = '';
        unset($this->professionals);
    }

    public function save(UpdateShiftAction $action): void
    {
        $shift = $this->editingShiftId ? Shift::query()->findOrFail($this->editingShiftId) : null;
        $this->authorize($shift ? 'update' : 'create', $shift ?? Shift::class);

        if ($shift) {
            abort_unless($this->editingShiftSnapshot, 404);
            $action->execute(auth()->user(), $shift->id, $this->payload(), $this->editingShiftSnapshot);
            session()->flash('status', 'Plantão atualizado com sucesso.');
        } else {
            $validated = (new SaveShiftRequest)->validatePayload($this->payload(), auth()->user());
            Shift::query()->create($validated);
            session()->flash('status', 'Plantão criado com sucesso.');
        }

        $this->resetForm();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function edit(int $shiftId): void
    {
        $shift = Shift::query()->findOrFail($shiftId);
        $this->authorize('update', $shift);

        $this->editingShiftId = $shift->id;
        $this->editingShiftSnapshot = app(\App\Support\ShiftReallocation::class)->shiftSignature($shift);
        $this->pendingDeleteId = null;
        $this->date = $shift->date->format('Y-m-d');
        $this->startTime = substr((string) $shift->start_time, 0, 5);
        $this->endTime = substr((string) $shift->end_time, 0, 5);
        $this->endDate = $shift->period()->end->format('Y-m-d');
        $this->sectorId = (string) $shift->sector_id;
        $this->categoryId = (string) $shift->category_id;
        $this->userId = $shift->user_id ? (string) $shift->user_id : '';
        $this->status = $shift->status->value;
        $this->showFormModal = true;
        $this->resetValidation();
    }

    public function closeFormModal(): void
    {
        $this->resetForm();
    }

    public function requestDelete(int $shiftId): void
    {
        $shift = Shift::query()->with(['sector', 'category'])->findOrFail($shiftId);
        $this->authorize('delete', $shift);
        $this->pendingDeleteId = $shift->id;
        $this->pendingDeleteLabel = sprintf(
            '%s, das %s às %s · %s · %s',
            $shift->date->format('d/m/Y'),
            substr((string) $shift->start_time, 0, 5),
            $shift->period()->endLabel().' · '.$shift->period()->durationLabel(),
            $shift->sector->name,
            $shift->category->name,
        );
    }

    public function cancelDelete(): void
    {
        $this->reset(['pendingDeleteId', 'pendingDeleteLabel']);
    }

    public function delete(): void
    {
        $shift = Shift::query()->findOrFail($this->pendingDeleteId);
        $this->authorize('delete', $shift);
        $shift->delete();
        $this->focus = null;

        $this->cancelDelete();
        session()->flash('status', 'Plantão excluído.');
    }

    public function openImportModal(): void
    {
        $this->authorize('create', Shift::class);
        $this->reset(['importFile', 'importErrors']);
        $this->showImportModal = true;
    }

    public function closeImportModal(): void
    {
        $this->reset(['showImportModal', 'importFile', 'importErrors']);
    }

    public function confirmImport(ImportShiftsAction $import): void
    {
        $this->authorize('create', Shift::class);
        $this->reset('importErrors');

        $validated = (new ImportShiftsRequest)->validatePayload(
            ['file' => $this->importFile],
            auth()->user(),
        );

        $storedPath = $validated['file']->store('shift-imports', 'local');
        $absolutePath = Storage::disk('local')->path($storedPath);

        try {
            $result = $import->execute(auth()->user(), $absolutePath);
        } catch (ValidationException $exception) {
            $this->importErrors = collect($exception->errors())->flatten()->values()->all();

            return;
        } finally {
            Storage::disk('local')->delete($storedPath);
        }

        $this->closeImportModal();
        $this->resetPage();

        session()->flash('status', sprintf(
            'Importação concluída: %d plantão(ões) criado(s)%s.',
            $result['created'],
            $result['skipped'] > 0 ? " e {$result['skipped']} duplicata(s) ignorada(s)" : '',
        ));
    }

    public function openCopyModal(): void
    {
        $this->authorize('create', Shift::class);
        $this->reset(['copyTargetMonth', 'copyPreview', 'copySelected', 'copyError']);
        $this->copySourceMonth = $this->month;
        $this->copyMode = DuplicateMonthAction::MODE_DAY;
        $this->showCopyModal = true;
    }

    public function closeCopyModal(): void
    {
        $this->reset(['showCopyModal', 'copySourceMonth', 'copyTargetMonth', 'copyPreview', 'copySelected', 'copyError']);
        $this->copyMode = DuplicateMonthAction::MODE_DAY;
    }

    public function generateCopyPreview(DuplicateMonthAction $duplicate): void
    {
        $this->authorize('create', Shift::class);
        $this->reset(['copyPreview', 'copySelected', 'copyError']);

        $this->validate([
            'copySourceMonth' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'copyTargetMonth' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'copyMode' => ['required', 'in:day,weekday'],
        ], [], [
            'copySourceMonth' => 'mês de origem',
            'copyTargetMonth' => 'mês de destino',
            'copyMode' => 'mapeamento',
        ]);

        try {
            $preview = $duplicate->preview(auth()->user(), $this->copySourceMonth, $this->copyTargetMonth, $this->copyMode);
        } catch (ValidationException $exception) {
            $this->copyError = collect($exception->errors())->flatten()->first() ?? 'Não foi possível gerar a prévia.';

            return;
        }

        $this->copyPreview = $preview;
        $this->copySelected = collect($preview['items'])
            ->whereIn('status', ['valid', 'adjusted'])
            ->pluck('source_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function confirmCopy(DuplicateMonthAction $duplicate): void
    {
        $this->authorize('create', Shift::class);

        $selected = collect($this->copySelected)->map(fn ($id) => (int) $id)->unique()->values()->all();

        try {
            $result = $duplicate->execute(auth()->user(), $this->copySourceMonth, $this->copyTargetMonth, $this->copyMode, $selected);
        } catch (ValidationException $exception) {
            $this->copyError = collect($exception->errors())->flatten()->first() ?? 'Não foi possível concluir a cópia.';

            return;
        }

        $targetMonth = $this->copyTargetMonth;
        $this->closeCopyModal();
        $this->month = $this->normalizeMonth($targetMonth);
        $this->resetPage();

        session()->flash('status', sprintf(
            'Cópia concluída: %d plantão(ões) criado(s) e %d ignorado(s).',
            $result['created'],
            $result['ignored'],
        ));
    }

    public function resetForm(): void
    {
        $this->reset(['editingShiftId', 'editingShiftSnapshot', 'pendingDeleteId', 'pendingDeleteLabel', 'showFormModal', 'sectorId', 'categoryId', 'userId']);
        $this->date = today()->addDay()->format('Y-m-d');
        $this->startTime = '07:00';
        $this->endTime = '19:00';
        $this->endDate = '';
        $this->status = ShiftStatus::Scheduled->value;
        $this->resetValidation();
        unset($this->professionals);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'date' => $this->date,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'end_date' => $this->endDate !== '' ? $this->endDate : null,
            'sector_id' => $this->sectorId,
            'category_id' => $this->categoryId,
            'user_id' => $this->userId !== '' ? $this->userId : null,
            'status' => $this->status,
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
        $this->authorize('viewAny', Shift::class);
        $this->focus = OperationalNavigation::id($this->focus);
        [$year, $month] = array_pad(explode('-', $this->month), 2, null);

        $this->filter = in_array($this->filter, ['scheduled', 'uncovered'], true) ? $this->filter : '';
        $shifts = ($this->focus === null && $this->filter === 'uncovered' ? ManagerAttention::uncovered(now()) : Shift::query())
            ->with(['category', 'sector', 'user'])
            ->when($this->focus === null && $this->filter !== 'uncovered' && $year && $month, fn ($query) => $query->whereYear('date', $year)->whereMonth('date', $month))
            ->when($this->focus === null && $this->filter === 'scheduled', fn ($query) => $query->where('status', ShiftStatus::Scheduled))
            ->when($this->focus !== null, fn ($query) => $query->whereKey($this->focus))
            ->orderBy('date')
            ->orderBy('start_time');

        if ($this->focus !== null) {
            $records = $shifts->get();
            $focusedShift = $records->first();
            abort_unless($focusedShift, 404);
            $this->authorize('view', $focusedShift);
            $shifts = new LengthAwarePaginator($records, 1, 10);
        } else {
            $focusedShift = null;
            $shifts = $shifts->paginate(10);
        }

        return view('livewire.manager.shift-manager', [
            'shifts' => $shifts, 'focusedShift' => $focusedShift,
            'reallocations' => $focusedShift ? ShiftReallocation::query()->where('shift_id', $focusedShift->id)->latest('id')->limit(10)->get() : collect(),
        ]);
    }
}
