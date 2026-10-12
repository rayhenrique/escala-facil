<?php

namespace App\Livewire\Manager;

use App\Http\Requests\SaveSectorRequest;
use App\Livewire\Concerns\HasOperationalContext;
use App\Models\Sector;
use App\Support\UiFeedback;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Setores'])]
class SectorManager extends Component
{
    use HasOperationalContext;
    use WithPagination;

    #[Url]
    public string $search = '';

    public ?int $editingSectorId = null;

    public ?int $pendingDeleteId = null;

    public string $pendingDeleteName = '';

    public bool $showFormModal = false;

    public string $name = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Sector::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search');
        $this->resetPage();
    }

    public function save(): void
    {
        $sector = $this->editingSectorId
            ? Sector::query()->findOrFail($this->editingSectorId)
            : null;

        $this->authorize($sector ? 'update' : 'create', $sector ?? Sector::class);

        $validated = (new SaveSectorRequest)->validatePayload([
            'sector_id' => $sector?->id,
            'name' => trim($this->name),
        ], auth()->user());

        if ($sector) {
            $sector->update(['name' => $validated['name']]);
            UiFeedback::success('Setor atualizado com sucesso.');
        } else {
            Sector::query()->create(['name' => $validated['name']]);
            UiFeedback::success('Setor criado com sucesso.');
        }

        $this->resetForm();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function edit(int $sectorId): void
    {
        $sector = Sector::query()->findOrFail($sectorId);
        $this->authorize('update', $sector);

        $this->editingSectorId = $sector->id;
        $this->pendingDeleteId = null;
        $this->name = $sector->name;
        $this->showFormModal = true;
        $this->resetValidation();
    }

    public function closeFormModal(): void
    {
        $this->resetForm();
    }

    public function requestDelete(int $sectorId): void
    {
        $sector = Sector::query()->findOrFail($sectorId);
        $this->authorize('delete', $sector);
        $this->pendingDeleteId = $sector->id;
        $this->pendingDeleteName = $sector->name;
    }

    public function cancelDelete(): void
    {
        $this->reset(['pendingDeleteId', 'pendingDeleteName']);
    }

    public function delete(): void
    {
        $sector = Sector::query()->findOrFail($this->pendingDeleteId);
        $this->authorize('delete', $sector);

        if ($sector->users()->exists() || $sector->shifts()->exists()) {
            $this->cancelDelete();
            UiFeedback::error('O setor não pode ser excluído porque está vinculado a usuários ou plantões.');

            return;
        }

        $sector->delete();
        $this->cancelDelete();
        UiFeedback::success('Setor excluído.');
    }

    public function resetForm(): void
    {
        $this->reset(['editingSectorId', 'pendingDeleteId', 'pendingDeleteName', 'showFormModal', 'name']);
        $this->resetValidation();
    }

    public function render(): View
    {
        $sectors = Sector::query()
            ->withCount(['users', 'shifts'])
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.manager.sector-manager', compact('sectors'));
    }
}
