<?php

namespace App\Livewire\Manager;

use App\Http\Requests\SaveCategoryRequest;
use App\Livewire\Concerns\HasOperationalContext;
use App\Models\Category;
use App\Support\UiFeedback;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Categorias'])]
class CategoryManager extends Component
{
    use HasOperationalContext;
    use WithPagination;

    #[Url]
    public string $search = '';

    public ?int $editingCategoryId = null;

    public ?int $pendingDeleteId = null;

    public string $pendingDeleteName = '';

    public bool $showFormModal = false;

    public string $name = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Category::class);
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
        $category = $this->editingCategoryId
            ? Category::query()->findOrFail($this->editingCategoryId)
            : null;

        $this->authorize($category ? 'update' : 'create', $category ?? Category::class);

        $validated = (new SaveCategoryRequest)->validatePayload([
            'category_id' => $category?->id,
            'name' => trim($this->name),
        ], auth()->user());

        if ($category) {
            $category->update(['name' => $validated['name']]);
            UiFeedback::success('Categoria atualizada com sucesso.');
        } else {
            Category::query()->create(['name' => $validated['name']]);
            UiFeedback::success('Categoria criada com sucesso.');
        }

        $this->resetForm();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function edit(int $categoryId): void
    {
        $category = Category::query()->findOrFail($categoryId);
        $this->authorize('update', $category);

        $this->editingCategoryId = $category->id;
        $this->pendingDeleteId = null;
        $this->name = $category->name;
        $this->showFormModal = true;
        $this->resetValidation();
    }

    public function closeFormModal(): void
    {
        $this->resetForm();
    }

    public function requestDelete(int $categoryId): void
    {
        $category = Category::query()->findOrFail($categoryId);
        $this->authorize('delete', $category);
        $this->pendingDeleteId = $category->id;
        $this->pendingDeleteName = $category->name;
    }

    public function cancelDelete(): void
    {
        $this->reset(['pendingDeleteId', 'pendingDeleteName']);
    }

    public function delete(): void
    {
        $category = Category::query()->findOrFail($this->pendingDeleteId);
        $this->authorize('delete', $category);

        if ($category->users()->exists() || $category->shifts()->exists()) {
            $this->cancelDelete();
            UiFeedback::error('A categoria não pode ser excluída porque está vinculada a usuários ou plantões.');

            return;
        }

        $category->delete();
        $this->cancelDelete();
        UiFeedback::success('Categoria excluída.');
    }

    public function resetForm(): void
    {
        $this->reset(['editingCategoryId', 'pendingDeleteId', 'pendingDeleteName', 'showFormModal', 'name']);
        $this->resetValidation();
    }

    public function render(): View
    {
        $categories = Category::query()
            ->withCount(['users', 'shifts'])
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.manager.category-manager', compact('categories'));
    }
}
