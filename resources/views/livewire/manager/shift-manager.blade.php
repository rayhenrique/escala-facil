<div>
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Gerência</p>
            <h1 class="page-title">Gestão de escalas</h1>
            <p class="page-description">Crie plantões e mantenha setor, categoria e profissional compatíveis.</p>
        </div>
        <div class="grid w-full gap-3 sm:w-auto sm:items-end">
            <div>
                <label for="month" class="field-label">Mês exibido</label>
                <input wire:model.live="month" id="month" type="month" class="field-input">
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <a href="{{ route('manager.shifts.template') }}" class="button-secondary w-full text-center sm:w-auto">Modelo XLSX</a>
                <a href="{{ route('manager.shifts.export', ['month' => $month]) }}" class="button-secondary w-full text-center sm:w-auto">Exportar</a>
                <button type="button" wire:click="openImportModal" class="button-secondary w-full sm:w-auto">Importar</button>
                <button type="button" wire:click="openCreateModal" class="button-primary w-full sm:w-auto">Adicionar plantão</button>
            </div>
        </div>
    </div>

    <x-flash-notifications />

    @if ($showFormModal)
        <div class="modal-overlay" x-data x-init="$nextTick(() => $refs.firstField?.focus())" @keydown.escape.window="$wire.closeFormModal()">
            <button type="button" wire:click="closeFormModal" class="modal-backdrop" aria-label="Fechar formulário de plantão"></button>
            <form method="post" wire:submit="save" class="modal-dialog max-w-4xl" role="dialog" aria-modal="true" aria-labelledby="shift-form-title" novalidate>
                @csrf
                <header class="modal-header">
                    <div>
                        <p class="eyebrow">{{ $editingShiftId ? 'Edição' : 'Novo registro' }}</p>
                        <h2 id="shift-form-title">{{ $editingShiftId ? 'Editar plantão' : 'Adicionar plantão' }}</h2>
                    </div>
                    <button type="button" wire:click="closeFormModal" class="modal-close-button" aria-label="Fechar formulário">
                        <x-nav-icon name="close" class="size-5" />
                    </button>
                </header>

                <div class="modal-body grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label for="date" class="field-label">Data</label>
                        <input x-ref="firstField" wire:model="date" id="date" type="date" class="field-input" @error('date') aria-invalid="true" aria-describedby="date-error" @enderror>
                        @error('date') <p id="date-error" class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="start-time" class="field-label">Início</label>
                        <input wire:model="startTime" id="start-time" type="time" class="field-input" @error('start_time') aria-invalid="true" aria-describedby="start-time-error" @enderror>
                        @error('start_time') <p id="start-time-error" class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="end-time" class="field-label">Fim</label>
                        <input wire:model="endTime" id="end-time" type="time" class="field-input" @error('end_time') aria-invalid="true" aria-describedby="end-time-error" @enderror>
                        @error('end_time') <p id="end-time-error" class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="sector" class="field-label">Setor</label>
                        <select wire:model.live="sectorId" id="sector" class="field-input" @error('sector_id') aria-invalid="true" aria-describedby="sector-error" @enderror>
                            <option value="">Selecione</option>
                            @foreach ($this->sectors as $sector)<option value="{{ $sector->id }}">{{ $sector->name }}</option>@endforeach
                        </select>
                        @error('sector_id') <p id="sector-error" class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="category" class="field-label">Categoria</label>
                        <select wire:model.live="categoryId" id="category" class="field-input" @error('category_id') aria-invalid="true" aria-describedby="category-error" @enderror>
                            <option value="">Selecione</option>
                            @foreach ($this->categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                        </select>
                        @error('category_id') <p id="category-error" class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="professional" class="field-label">Profissional</label>
                        <select wire:model="userId" id="professional" class="field-input" @error('user_id') aria-invalid="true" aria-describedby="professional-error" @enderror>
                            <option value="">Sem profissional alocado</option>
                            @foreach ($this->professionals as $professional)<option value="{{ $professional->id }}">{{ $professional->name }}</option>@endforeach
                        </select>
                        @error('user_id') <p id="professional-error" class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="status" class="field-label">Status</label>
                        <select wire:model="status" id="status" class="field-input">
                            @foreach (\App\Enums\ShiftStatus::cases() as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
                        </select>
                    </div>
                </div>

                <footer class="modal-footer">
                    <button type="button" wire:click="closeFormModal" class="button-secondary w-full sm:w-auto">Cancelar</button>
                    <button type="submit" class="button-primary w-full sm:w-auto" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $editingShiftId ? 'Salvar alterações' : 'Criar plantão' }}</span>
                        <span wire:loading wire:target="save">Salvando…</span>
                    </button>
                </footer>
            </form>
        </div>
    @endif

    <section class="panel mt-8" aria-labelledby="shift-list-title">
        <div class="section-heading">
            <div><p class="eyebrow">Agenda mensal</p><h2 id="shift-list-title" class="mt-2 text-xl font-semibold">Plantões cadastrados</h2></div>
            <span class="text-sm text-muted">{{ $shifts->total() }} registro(s)</span>
        </div>

        <div class="mt-6 space-y-4 md:hidden" aria-label="Plantões cadastrados">
            @forelse ($shifts as $shift)
                <x-shift-card :shift="$shift" :show-professional="true" wire:key="mobile-shift-{{ $shift->id }}">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="edit({{ $shift->id }})" class="button-secondary">Editar</button>
                        <button type="button" wire:click="requestDelete({{ $shift->id }})" class="table-action min-h-10 text-danger">Excluir</button>
                    </div>
                </x-shift-card>
            @empty
                <div class="empty-state compact-empty"><p class="font-semibold text-ink">Nenhum plantão encontrado.</p><p class="mt-2 text-sm">Altere o mês ou crie um novo plantão.</p></div>
            @endforelse
        </div>

        <div class="mt-6 hidden overflow-x-auto md:block">
            <table class="data-table">
                <thead><tr><th>Data e horário</th><th>Setor / categoria</th><th>Profissional</th><th>Status</th><th class="text-right">Ações</th></tr></thead>
                <tbody>
                    @forelse ($shifts as $shift)
                        <tr wire:key="shift-{{ $shift->id }}">
                            <td><strong>{{ $shift->date->format('d/m/Y') }}</strong><span>{{ substr($shift->start_time, 0, 5) }}–{{ substr($shift->end_time, 0, 5) }}</span></td>
                            <td><strong>{{ $shift->sector->name }}</strong><span>{{ $shift->category->name }}</span></td>
                            <td>{{ $shift->user?->name ?? 'Não alocado' }}</td>
                            <td><x-status-badge :status="$shift->status" /></td>
                            <td><div class="flex justify-end gap-2"><button type="button" wire:click="edit({{ $shift->id }})" class="table-action">Editar</button><button type="button" wire:click="requestDelete({{ $shift->id }})" class="table-action text-danger">Excluir</button></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-12 text-center text-muted">Nenhum plantão encontrado para este mês.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $shifts->links() }}</div>
    </section>

    <x-confirmation-modal :show="$pendingDeleteId !== null" id="delete-shift" title="Excluir plantão?" close-action="cancelDelete">
        <p><strong>{{ $pendingDeleteLabel }}</strong></p>
        <p>A exclusão também removerá as trocas e ausências associadas a este plantão.</p>
        <x-slot:actions>
            <button type="button" wire:click="cancelDelete" class="button-secondary w-full sm:w-auto" data-confirm-cancel>Manter plantão</button>
            <button type="button" wire:click="delete" wire:loading.attr="disabled" wire:target="delete" class="button-danger w-full sm:w-auto">
                <span wire:loading.remove wire:target="delete">Excluir plantão</span><span wire:loading wire:target="delete">Excluindo…</span>
            </button>
        </x-slot:actions>
    </x-confirmation-modal>

    @if ($showImportModal)
        <div class="modal-overlay" x-data x-init="$nextTick(() => $refs.importField?.focus())" @keydown.escape.window="$wire.closeImportModal()">
            <button type="button" wire:click="closeImportModal" class="modal-backdrop" aria-label="Fechar importação de plantões"></button>
            <form method="post" wire:submit="confirmImport" class="modal-dialog max-w-2xl" role="dialog" aria-modal="true" aria-labelledby="shift-import-title" novalidate enctype="multipart/form-data">
                @csrf
                <header class="modal-header">
                    <div>
                        <p class="eyebrow">Planilha XLSX</p>
                        <h2 id="shift-import-title">Importar plantões</h2>
                    </div>
                    <button type="button" wire:click="closeImportModal" class="modal-close-button" aria-label="Fechar importação">
                        <x-nav-icon name="close" class="size-5" />
                    </button>
                </header>

                <div class="modal-body grid gap-5">
                    <p class="text-sm text-muted">Use o <a href="{{ route('manager.shifts.template') }}" class="table-action">modelo padrão</a> com os setores e categorias cadastrados. Linhas duplicadas são ignoradas; qualquer erro em uma linha cancela toda a importação.</p>
                    <div>
                        <label for="import-file" class="field-label">Arquivo XLSX (máximo 5 MB)</label>
                        <input x-ref="importField" wire:model="importFile" id="import-file" type="file" accept=".xlsx" class="field-input" @error('file') aria-invalid="true" aria-describedby="import-file-error" @enderror>
                        @error('file') <p id="import-file-error" class="field-error">{{ $message }}</p> @enderror
                    </div>

                    @if ($importErrors !== [])
                        <div class="rounded-lg border border-danger/30 bg-danger-soft p-4" role="alert" aria-label="Erros da importação">
                            <p class="font-semibold text-danger">A importação foi cancelada. Nenhum plantão foi criado.</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-danger">
                                @foreach (array_slice($importErrors, 0, 20) as $importError)
                                    <li>{{ $importError }}</li>
                                @endforeach
                            </ul>
                            @if (count($importErrors) > 20)
                                <p class="mt-2 text-sm text-danger">…e mais {{ count($importErrors) - 20 }} erro(s).</p>
                            @endif
                        </div>
                    @endif
                </div>

                <footer class="modal-footer">
                    <button type="button" wire:click="closeImportModal" class="button-secondary w-full sm:w-auto">Cancelar</button>
                    <button type="submit" class="button-primary w-full sm:w-auto" wire:loading.attr="disabled" wire:target="confirmImport,importFile">
                        <span wire:loading.remove wire:target="confirmImport,importFile">Revisar e importar</span>
                        <span wire:loading wire:target="confirmImport,importFile">Importando…</span>
                    </button>
                </footer>
            </form>
        </div>
    @endif
</div>
