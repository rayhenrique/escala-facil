<?php

namespace App\Livewire\Manager;

use App\Actions\Shifts\ImportShiftsAction;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Http\Requests\ImportShiftsRequest;
use App\Http\Requests\SaveShiftRequest;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Gestão de escalas'])]
class ShiftManager extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Url]
    public string $month = '';

    public ?int $editingShiftId = null;

    public ?int $pendingDeleteId = null;

    public string $pendingDeleteLabel = '';

    public bool $showFormModal = false;

    public string $date = '';

    public string $startTime = '07:00';

    public string $endTime = '19:00';

    public string $sectorId = '';

    public string $categoryId = '';

    public string $userId = '';

    public string $status = 'scheduled';

    public bool $showImportModal = false;

    public $importFile = null;

    /** @var list<string> */
    public array $importErrors = [];

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

    public function save(): void
    {
        $shift = $this->editingShiftId ? Shift::query()->findOrFail($this->editingShiftId) : null;
        $this->authorize($shift ? 'update' : 'create', $shift ?? Shift::class);

        $validated = (new SaveShiftRequest)->validatePayload($this->payload(), auth()->user());

        if ($shift) {
            $shift->update($validated);
            session()->flash('status', 'Plantão atualizado com sucesso.');
        } else {
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
        $this->pendingDeleteId = null;
        $this->date = $shift->date->format('Y-m-d');
        $this->startTime = substr((string) $shift->start_time, 0, 5);
        $this->endTime = substr((string) $shift->end_time, 0, 5);
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
            substr((string) $shift->end_time, 0, 5),
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

    public function resetForm(): void
    {
        $this->reset(['editingShiftId', 'pendingDeleteId', 'pendingDeleteLabel', 'showFormModal', 'sectorId', 'categoryId', 'userId']);
        $this->date = today()->addDay()->format('Y-m-d');
        $this->startTime = '07:00';
        $this->endTime = '19:00';
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
        [$year, $month] = array_pad(explode('-', $this->month), 2, null);

        $shifts = Shift::query()
            ->with(['category', 'sector', 'user'])
            ->when($year && $month, fn ($query) => $query->whereYear('date', $year)->whereMonth('date', $month))
            ->orderBy('date')
            ->orderBy('start_time')
            ->paginate(10);

        return view('livewire.manager.shift-manager', compact('shifts'));
    }
}
