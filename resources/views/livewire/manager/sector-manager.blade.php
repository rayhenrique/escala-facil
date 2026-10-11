<div>
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Cadastros</p>
            <h1 class="page-title">Setores da unidade</h1>
            <p class="page-description">Mantenha os setores usados para organizar cobertura, profissionais e plantões.</p>
        </div>
        <div class="grid w-full gap-3 sm:w-auto sm:grid-cols-[18rem_auto] sm:items-end">
            <div>
                <label for="sector-search" class="field-label">Buscar setor</label>
                <input wire:model.live.debounce.300ms="search" id="sector-search" type="search" class="field-input" placeholder="Digite o nome">
            </div>
            <button type="button" wire:click="openCreateModal" class="button-primary w-full sm:w-auto">Adicionar setor</button>
        </div>
    </div>

    <x-flash-notifications />

    @if ($showFormModal)
        <div class="modal-overlay" x-data x-init="$nextTick(() => $refs.firstField?.focus())" @keydown.escape.window="$wire.closeFormModal()">
            <button type="button" wire:click="closeFormModal" class="modal-backdrop" aria-label="Fechar formulário de setor"></button>
            <form method="post" wire:submit="save" class="modal-dialog max-w-xl" role="dialog" aria-modal="true" aria-labelledby="sector-form-title" novalidate>
                @csrf
                <header class="modal-header">
                    <div>
                        <p class="eyebrow">{{ $editingSectorId ? 'Edição' : 'Novo cadastro' }}</p>
                        <h2 id="sector-form-title">{{ $editingSectorId ? 'Editar setor' : 'Adicionar setor' }}</h2>
                    </div>
                    <button type="button" wire:click="closeFormModal" class="modal-close-button" aria-label="Fechar formulário"><x-nav-icon name="close" class="size-5" /></button>
                </header>
                <div class="modal-body">
                    <label for="sector-name" class="field-label">Nome do setor</label>
                    <input x-ref="firstField" wire:model="name" id="sector-name" type="text" class="field-input" maxlength="255" autocomplete="off" placeholder="Ex.: Unidade de Terapia Intensiva" @error('name') aria-invalid="true" aria-describedby="sector-name-error" @enderror>
                    @error('name') <p id="sector-name-error" class="field-error">{{ $message }}</p> @enderror
                </div>
                <footer class="modal-footer">
                    <button type="button" wire:click="closeFormModal" class="button-secondary w-full sm:w-auto">Cancelar</button>
                    <button type="submit" class="button-primary w-full sm:w-auto" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $editingSectorId ? 'Salvar alterações' : 'Criar setor' }}</span>
                        <span wire:loading wire:target="save">Salvando…</span>
                    </button>
                </footer>
            </form>
        </div>
    @endif

    <section class="panel mt-8" aria-labelledby="sector-list-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Cadastros disponíveis</p>
                <h2 id="sector-list-title">Setores</h2>
            </div>
            <span class="text-sm text-muted">{{ $sectors->total() }} registro(s)</span>
        </div>

        <div class="mt-6 space-y-3 md:hidden">
            @forelse ($sectors as $sector)
                <article class="record-card" wire:key="sector-card-{{ $sector->id }}">
                    <div class="min-w-0">
                        <h3 class="font-semibold">{{ $sector->name }}</h3>
                        <p class="mt-1 text-sm text-muted">{{ $sector->users_count }} usuário(s) · {{ $sector->shifts_count }} plantão(ões)</p>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <x-operational-audit-link entity="sector" :record="$sector->id" from="sectors" :context="$this->navigationContext()" />
<button type="button" wire:click="edit({{ $sector->id }})" class="button-secondary">Editar</button>
                        <button type="button" wire:click="requestDelete({{ $sector->id }})" class="button-secondary text-danger">Excluir</button>
                    </div>
                </article>
            @empty
                <div class="empty-state compact-empty"><p class="font-semibold text-ink">Nenhum setor encontrado.</p><p class="mt-2 text-sm">Altere a busca ou crie um novo setor.</p></div>
            @endforelse
        </div>

        <div class="mt-6 hidden overflow-x-auto md:block">
            <table class="data-table">
                <thead><tr><th>Setor</th><th>Usuários</th><th>Plantões</th><th class="text-right">Ações</th></tr></thead>
                <tbody>
                    @forelse ($sectors as $sector)
                        <tr wire:key="sector-row-{{ $sector->id }}">
                            <td><strong>{{ $sector->name }}</strong></td>
                            <td>{{ $sector->users_count }}</td>
                            <td>{{ $sector->shifts_count }}</td>
                            <td><div class="flex justify-end gap-2"><x-operational-audit-link entity="sector" :record="$sector->id" from="sectors" :context="$this->navigationContext()" />
<button type="button" wire:click="edit({{ $sector->id }})" class="table-action">Editar</button><button type="button" wire:click="requestDelete({{ $sector->id }})" class="table-action text-danger">Excluir</button></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-12 text-center text-muted">Nenhum setor encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $sectors->links() }}</div>
    </section>

    <x-confirmation-modal :show="$pendingDeleteId !== null" id="delete-sector" title="Excluir setor?" close-action="cancelDelete">
        <p>Você está prestes a excluir <strong>{{ $pendingDeleteName }}</strong>.</p>
        <p>A exclusão será bloqueada se o setor estiver vinculado a usuários ou plantões.</p>
        <x-slot:actions>
            <button type="button" wire:click="cancelDelete" class="button-secondary w-full sm:w-auto" data-confirm-cancel>Manter setor</button>
            <button type="button" wire:click="delete" wire:loading.attr="disabled" wire:target="delete" class="button-danger w-full sm:w-auto">
                <span wire:loading.remove wire:target="delete">Excluir setor</span><span wire:loading wire:target="delete">Excluindo…</span>
            </button>
        </x-slot:actions>
    </x-confirmation-modal>
</div>
