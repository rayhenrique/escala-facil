<div>
    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Cadastros</p>
            <h1 class="page-title">Usuários</h1>
            <p class="page-description">Cadastre gerentes e profissionais com o perfil operacional correto.</p>
        </div>
        <div class="grid w-full gap-4 sm:grid-cols-2 lg:w-auto lg:grid-cols-[18rem_12rem_auto] lg:items-end">
            <div class="sm:w-72">
                <label for="user-search" class="field-label">Buscar usuário</label>
                <input wire:model.live.debounce.300ms="search" id="user-search" type="search" class="field-input" placeholder="Nome ou e-mail">
            </div>
            <div class="sm:w-48">
                <label for="role-filter" class="field-label">Filtrar por perfil</label>
                <select wire:model.live="roleFilter" id="role-filter" class="field-input">
                    <option value="">Todos</option>
                    @foreach (\App\Enums\UserRole::cases() as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" wire:click="openCreateModal" class="button-primary w-full sm:col-span-2 lg:col-span-1 lg:w-auto">Adicionar usuário</button>
        </div>
    </div>

    @if (session('status'))
        <div class="status-success mt-6" role="status">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="status-error mt-6" role="alert">{{ session('error') }}</div>
    @endif

    @if ($showFormModal)
        <div class="modal-overlay" x-data x-init="$nextTick(() => $refs.firstField?.focus())" @keydown.escape.window="$wire.closeFormModal()">
            <button type="button" wire:click="closeFormModal" class="modal-backdrop" aria-label="Fechar formulário de usuário"></button>
            <form method="post" wire:submit="save" class="modal-dialog max-w-4xl" role="dialog" aria-modal="true" aria-labelledby="user-form-title" novalidate>
                @csrf
                <header class="modal-header">
                    <div>
                        <p class="eyebrow">{{ $editingUserId ? 'Edição' : 'Novo cadastro' }}</p>
                        <h2 id="user-form-title">{{ $editingUserId ? 'Editar usuário' : 'Adicionar usuário' }}</h2>
                    </div>
                    <button type="button" wire:click="closeFormModal" class="modal-close-button" aria-label="Fechar formulário">
                        <x-nav-icon name="close" class="size-5" />
                    </button>
                </header>

                <div class="modal-body grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label for="user-name" class="field-label">Nome completo</label>
                        <input x-ref="firstField" wire:model="name" id="user-name" type="text" class="field-input" maxlength="255" autocomplete="name" @error('name') aria-invalid="true" aria-describedby="user-name-error" @enderror>
                        @error('name') <p id="user-name-error" class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="user-email" class="field-label">E-mail</label>
                        <input wire:model="email" id="user-email" type="email" class="field-input" maxlength="255" autocomplete="email" inputmode="email" @error('email') aria-invalid="true" aria-describedby="user-email-error" @enderror>
                        @error('email') <p id="user-email-error" class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="user-role" class="field-label">Perfil</label>
                        <select wire:model.live="role" id="user-role" class="field-input" @error('role') aria-invalid="true" aria-describedby="user-role-error" @enderror>
                            @foreach (\App\Enums\UserRole::cases() as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        @error('role') <p id="user-role-error" class="field-error">{{ $message }}</p> @enderror
                    </div>

                    @if ($role === \App\Enums\UserRole::Professional->value)
                        <div>
                            <label for="user-category" class="field-label">Categoria</label>
                            <select wire:model="categoryId" id="user-category" class="field-input" @error('category_id') aria-invalid="true" aria-describedby="user-category-error" @enderror>
                                <option value="">Selecione</option>
                                @foreach ($this->categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                            </select>
                            @error('category_id') <p id="user-category-error" class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="user-sector" class="field-label">Setor</label>
                            <select wire:model="sectorId" id="user-sector" class="field-input" @error('sector_id') aria-invalid="true" aria-describedby="user-sector-error" @enderror>
                                <option value="">Selecione</option>
                                @foreach ($this->sectors as $sector)<option value="{{ $sector->id }}">{{ $sector->name }}</option>@endforeach
                            </select>
                            @error('sector_id') <p id="user-sector-error" class="field-error">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <label for="user-password" class="field-label">{{ $editingUserId ? 'Nova senha (opcional)' : 'Senha' }}</label>
                        <input wire:model="password" id="user-password" type="password" class="field-input" autocomplete="new-password" @error('password') aria-invalid="true" aria-describedby="user-password-error" @enderror>
                        @error('password') <p id="user-password-error" class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="user-password-confirmation" class="field-label">Confirmar senha</label>
                        <input wire:model="passwordConfirmation" id="user-password-confirmation" type="password" class="field-input" autocomplete="new-password">
                    </div>
                </div>

                <footer class="modal-footer">
                    <button type="button" wire:click="closeFormModal" class="button-secondary w-full sm:w-auto">Cancelar</button>
                    <button type="submit" class="button-primary w-full sm:w-auto" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $editingUserId ? 'Salvar alterações' : 'Criar usuário' }}</span>
                        <span wire:loading wire:target="save">Salvando…</span>
                    </button>
                </footer>
            </form>
        </div>
    @endif

    <section class="panel mt-8" aria-labelledby="user-list-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Acessos cadastrados</p>
                <h2 id="user-list-title">Usuários do sistema</h2>
            </div>
            <span class="text-sm text-muted">{{ $users->total() }} registro(s)</span>
        </div>

        @if ($pendingDeleteId)
            <div class="confirmation-panel mt-5" role="alert">
                <p class="text-sm text-danger">Deseja excluir este usuário? Contas com histórico operacional serão preservadas.</p>
                <div class="grid grid-cols-2 gap-2 sm:flex">
                    <button type="button" wire:click="$set('pendingDeleteId', null)" class="button-secondary">Cancelar</button>
                    <button type="button" wire:click="delete" class="button-danger">Excluir</button>
                </div>
            </div>
        @endif

        <div class="mt-6 space-y-3 md:hidden" aria-label="Usuários cadastrados">
            @forelse ($users as $user)
                <article class="record-card" wire:key="user-card-{{ $user->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate font-semibold">{{ $user->name }}</h3>
                            <p class="mt-1 break-all text-sm text-muted">{{ $user->email }}</p>
                        </div>
                        <span class="status-badge {{ $user->role === \App\Enums\UserRole::Manager ? 'status-pending_manager' : 'status-scheduled' }}">{{ $user->role->label() }}</span>
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="meta-label">Categoria</dt><dd class="mt-1">{{ $user->category?->name ?? 'Não se aplica' }}</dd></div>
                        <div><dt class="meta-label">Setor</dt><dd class="mt-1">{{ $user->sector?->name ?? 'Não se aplica' }}</dd></div>
                    </dl>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <button type="button" wire:click="edit({{ $user->id }})" class="button-secondary">Editar</button>
                        <button type="button" wire:click="requestDelete({{ $user->id }})" class="button-secondary text-danger">Excluir</button>
                    </div>
                </article>
            @empty
                <div class="empty-state compact-empty"><p class="font-semibold text-ink">Nenhum usuário encontrado.</p><p class="mt-2 text-sm">Altere os filtros ou crie um novo usuário.</p></div>
            @endforelse
        </div>

        <div class="mt-6 hidden overflow-x-auto md:block">
            <table class="data-table">
                <thead><tr><th>Usuário</th><th>Perfil</th><th>Categoria</th><th>Setor</th><th class="text-right">Ações</th></tr></thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr wire:key="user-row-{{ $user->id }}">
                            <td><strong>{{ $user->name }}</strong><span>{{ $user->email }}</span></td>
                            <td><span class="status-badge {{ $user->role === \App\Enums\UserRole::Manager ? 'status-pending_manager' : 'status-scheduled' }}">{{ $user->role->label() }}</span></td>
                            <td>{{ $user->category?->name ?? 'Não se aplica' }}</td>
                            <td>{{ $user->sector?->name ?? 'Não se aplica' }}</td>
                            <td><div class="flex justify-end gap-2"><button type="button" wire:click="edit({{ $user->id }})" class="table-action">Editar</button><button type="button" wire:click="requestDelete({{ $user->id }})" class="table-action text-danger">Excluir</button></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-12 text-center text-muted">Nenhum usuário encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $users->links() }}</div>
    </section>
</div>
