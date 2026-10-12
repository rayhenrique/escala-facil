<div>
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Cadastros</p>
            <h1 class="page-title">Categorias profissionais</h1>
            <p class="page-description">Organize as especialidades usadas para validar alocações e trocas de plantão.</p>
        </div>
        <div class="grid w-full gap-3 sm:w-auto sm:grid-cols-[18rem_auto] sm:items-end">
            <div>
                <label for="category-search" class="field-label">Buscar categoria</label>
                <input wire:model.live.debounce.300ms="search" id="category-search" type="search" maxlength="100" autocomplete="off" enterkeyhint="search" class="field-input" placeholder="Digite o nome">
            </div>
            <button type="button" wire:click="openCreateModal" class="button-primary w-full sm:w-auto">Adicionar categoria</button>
        </div>
    </div>

    <x-filter-summary focus-id="category-search" :filters="['search' => ['label' => 'Pesquisa', 'value' => $search]]" />

    <x-flash-notifications />

    @if ($showFormModal)
    <x-action-modal :show="$showFormModal" id="category-manager-form" :title="$editingCategoryId ? 'Editar categoria' : 'Adicionar categoria'" :eyebrow="$editingCategoryId ? 'Edição' : 'Novo cadastro'" close-action="closeFormModal" submit-action="save" max-width="xl" track-changes fit-viewport>
                <div>
                    <label for="category-name" class="field-label">Nome da categoria</label>
                    <input x-ref="firstField" wire:model="name" id="category-name" type="text" class="field-input" maxlength="255" autocomplete="off" placeholder="Ex.: Médico Clínico" @error('name') aria-invalid="true" aria-describedby="category-name-error" @enderror>
                    @error('name') <p id="category-name-error" class="field-error">{{ $message }}</p> @enderror
                </div>
                <x-slot:actions>
                    <button type="button" @click="requestClose()" class="button-secondary w-full sm:w-auto">Cancelar</button>
                    <button type="submit" class="button-primary w-full sm:w-auto" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $editingCategoryId ? 'Salvar alterações' : 'Criar categoria' }}</span>
                        <span wire:loading wire:target="save">Salvando…</span>
                    </button>
                </x-slot:actions>
    </x-action-modal>
    @endif

    <section class="panel mt-8" aria-labelledby="category-list-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Cadastros disponíveis</p>
                <h2 id="category-list-title">Categorias</h2>
            </div>
            <span class="text-sm text-muted">{{ $categories->total() }} registro(s)</span>
        </div>

        <div class="mt-6 space-y-3 md:hidden">
            @forelse ($categories as $category)
                <article class="record-card" wire:key="category-card-{{ $category->id }}">
                    <div class="min-w-0">
                        <h3 class="font-semibold">{{ $category->name }}</h3>
                        <p class="mt-1 text-sm text-muted">{{ $category->users_count }} usuário(s) · {{ $category->shifts_count }} plantão(ões)</p>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <x-operational-audit-link entity="category" :record="$category->id" from="categories" :context="$this->navigationContext()" />
<button type="button" wire:click="edit({{ $category->id }})" class="button-secondary">Editar</button>
                        <button type="button" wire:click="requestDelete({{ $category->id }})" class="button-secondary text-danger">Excluir</button>
                    </div>
                </article>
            @empty
                <x-empty-state class="compact-empty" title="Nenhuma categoria encontrada." description="Altere a busca ou crie uma nova categoria." />
            @endforelse
        </div>

        <div class="mt-6 hidden overflow-x-auto md:block">
            <table class="data-table">
                <thead><tr><th>Categoria</th><th>Usuários</th><th>Plantões</th><th class="text-right">Ações</th></tr></thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr wire:key="category-row-{{ $category->id }}">
                            <td><strong>{{ $category->name }}</strong></td>
                            <td>{{ $category->users_count }}</td>
                            <td>{{ $category->shifts_count }}</td>
                            <td><div class="flex justify-end gap-2"><x-operational-audit-link entity="category" :record="$category->id" from="categories" :context="$this->navigationContext()" />
<button type="button" wire:click="edit({{ $category->id }})" class="table-action">Editar</button><button type="button" wire:click="requestDelete({{ $category->id }})" class="table-action text-danger">Excluir</button></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-12 text-center text-muted">Nenhuma categoria encontrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $categories->links() }}</div>
    </section>

    <x-confirmation-modal :show="$pendingDeleteId !== null" id="delete-category" title="Excluir categoria?" close-action="cancelDelete">
        <p>Você está prestes a excluir <strong>{{ $pendingDeleteName }}</strong>.</p>
        <p>A exclusão será bloqueada se a categoria estiver vinculada a usuários ou plantões.</p>
        <x-slot:actions>
            <button type="button" wire:click="cancelDelete" class="button-secondary w-full sm:w-auto" data-confirm-cancel>Manter categoria</button>
            <button type="button" wire:click="delete" wire:loading.attr="disabled" wire:target="delete" class="button-danger w-full sm:w-auto">
                <span wire:loading.remove wire:target="delete">Excluir categoria</span><span wire:loading wire:target="delete">Excluindo…</span>
            </button>
        </x-slot:actions>
    </x-confirmation-modal>
</div>
