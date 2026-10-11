<?php

namespace App\Livewire\Manager;

use App\Enums\UserRole;
use App\Http\Requests\SaveUserRequest;
use App\Livewire\Concerns\HasOperationalContext;
use App\Models\Category;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Usuários'])]
class UserManager extends Component
{
    use HasOperationalContext;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $roleFilter = '';

    public ?int $editingUserId = null;

    public ?int $pendingDeleteId = null;

    public string $pendingDeleteName = '';

    public bool $showFormModal = false;

    public string $name = '';

    public string $email = '';

    public string $role = 'professional';

    public string $categoryId = '';

    public string $sectorId = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
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

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedRole(): void
    {
        if ($this->role === UserRole::Manager->value) {
            $this->categoryId = '';
            $this->sectorId = '';
        }
    }

    public function save(): void
    {
        $user = $this->editingUserId ? User::query()->findOrFail($this->editingUserId) : null;
        $this->authorize($user ? 'update' : 'create', $user ?? User::class);

        $validated = (new SaveUserRequest)->validatePayload($this->payload(), auth()->user());
        $newRole = UserRole::from($validated['role']);

        if ($user && $user->id === auth()->id() && $newRole !== UserRole::Manager) {
            $this->addError('role', 'Você não pode remover o perfil de gerente da própria conta.');

            return;
        }

        if ($user
            && $user->role === UserRole::Manager
            && $newRole !== UserRole::Manager
            && User::query()->where('role', UserRole::Manager)->count() === 1) {
            $this->addError('role', 'O sistema deve manter pelo menos um gerente.');

            return;
        }

        $categoryId = $newRole === UserRole::Professional ? (int) $validated['category_id'] : null;
        $sectorId = $newRole === UserRole::Professional ? (int) $validated['sector_id'] : null;

        if ($user && $this->hasIncompatibleFutureShift($user, $newRole, $categoryId, $sectorId)) {
            $this->addError('role', 'O usuário possui plantões futuros incompatíveis. Realoque esses plantões antes de alterar o perfil, a categoria ou o setor.');

            return;
        }

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $newRole,
            'category_id' => $categoryId,
            'sector_id' => $sectorId,
        ];

        if (! empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }

        if ($user) {
            $user->update($data);
            session()->flash('status', 'Usuário atualizado com sucesso.');
        } else {
            User::query()->create($data);
            session()->flash('status', 'Usuário criado com sucesso.');
        }

        $this->resetForm();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function edit(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('update', $user);

        $this->editingUserId = $user->id;
        $this->pendingDeleteId = null;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->categoryId = $user->category_id ? (string) $user->category_id : '';
        $this->sectorId = $user->sector_id ? (string) $user->sector_id : '';
        $this->password = '';
        $this->passwordConfirmation = '';
        $this->showFormModal = true;
        $this->resetValidation();
    }

    public function closeFormModal(): void
    {
        $this->resetForm();
    }

    public function requestDelete(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('delete', $user);
        $this->pendingDeleteId = $user->id;
        $this->pendingDeleteName = $user->name;
    }

    public function cancelDelete(): void
    {
        $this->reset(['pendingDeleteId', 'pendingDeleteName']);
    }

    public function delete(): void
    {
        $user = User::query()->findOrFail($this->pendingDeleteId);
        $this->authorize('delete', $user);

        if ($user->id === auth()->id()) {
            $this->denyDeletion('Você não pode excluir a própria conta.');

            return;
        }

        if ($user->role === UserRole::Manager
            && User::query()->where('role', UserRole::Manager)->count() === 1) {
            $this->denyDeletion('O último gerente do sistema não pode ser excluído.');

            return;
        }

        if ($user->shifts()->exists()
            || $user->requestedSwaps()->exists()
            || $user->substituteSwaps()->exists()
            || $user->managedSwaps()->exists()
            || $user->absences()->exists()) {
            $this->denyDeletion('O usuário não pode ser excluído porque possui histórico de plantões, trocas ou ausências.');

            return;
        }

        $user->delete();
        $this->cancelDelete();
        session()->flash('status', 'Usuário excluído.');
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingUserId',
            'pendingDeleteId',
            'pendingDeleteName',
            'showFormModal',
            'name',
            'email',
            'categoryId',
            'sectorId',
            'password',
            'passwordConfirmation',
        ]);
        $this->role = UserRole::Professional->value;
        $this->resetValidation();
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'user_id' => $this->editingUserId,
            'name' => trim($this->name),
            'email' => mb_strtolower(trim($this->email)),
            'role' => $this->role,
            'category_id' => $this->categoryId !== '' ? $this->categoryId : null,
            'sector_id' => $this->sectorId !== '' ? $this->sectorId : null,
            'password' => $this->password !== '' ? $this->password : null,
            'password_confirmation' => $this->passwordConfirmation !== '' ? $this->passwordConfirmation : null,
        ];
    }

    private function hasIncompatibleFutureShift(
        User $user,
        UserRole $role,
        ?int $categoryId,
        ?int $sectorId,
    ): bool {
        return $user->shifts()
            ->whereDate('date', '>=', today())
            ->when(
                $role === UserRole::Professional,
                fn ($query) => $query->where(function ($query) use ($categoryId, $sectorId) {
                    $query->where('category_id', '!=', $categoryId)
                        ->orWhere('sector_id', '!=', $sectorId);
                }),
                fn ($query) => $query,
            )
            ->exists();
    }

    private function denyDeletion(string $message): void
    {
        $this->cancelDelete();
        session()->flash('error', $message);
    }

    public function render(): View
    {
        $users = User::query()
            ->with(['category', 'sector'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->roleFilter !== '', fn ($query) => $query->where('role', $this->roleFilter))
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.manager.user-manager', compact('users'));
    }
}
