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
                <input wire:model.live="month" id="month" type="month" class="field-input" @disabled($filter === 'uncovered' || $focus !== null)>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <a href="{{ route('manager.shifts.template') }}" class="button-secondary w-full text-center sm:w-auto">Modelo XLSX</a>
                <a href="{{ route('manager.shifts.export', ['month' => $month]) }}" class="button-secondary w-full text-center sm:w-auto">Exportar</a>
                <button type="button" wire:click="openImportModal" class="button-secondary w-full sm:w-auto">Importar</button>
                <button type="button" wire:click="openCopyModal" class="button-secondary w-full sm:w-auto">Copiar escala</button>
                <button type="button" wire:click="openCreateModal" class="button-primary w-full sm:w-auto">Adicionar plantão</button>
            </div>
        </div>
    </div>

    <x-flash-notifications />

    @php
        $navigationMonth = $focusedShift?->date->format('Y-m') ?? $month;
        $calendarParameters = ['month' => $navigationMonth];
        if ($focusedShift) $calendarParameters += ['selectedDate' => $focusedShift->date->format('Y-m-d'), 'sectorId' => $focusedShift->sector_id, 'categoryId' => $focusedShift->category_id];
        $reportParameters = ['month' => $navigationMonth, 'sectorId' => $focusedShift?->sector_id ?? '', 'categoryId' => $focusedShift?->category_id ?? ''];
    @endphp
    <x-context-navigation :back="$this->backNavigation()" :label="$focusedShift ? 'Plantão #'.$focusedShift->id.' selecionado' : null" :links="[
        'Ver no calendário' => \App\Support\OperationalNavigation::url('calendar', $calendarParameters, 'shifts', $this->navigationContext()),
        'Ver relatório' => \App\Support\OperationalNavigation::url('reports', $reportParameters, 'shifts', $this->navigationContext()),
    ]" />

    <div class="mt-5 flex flex-wrap items-end gap-3">
        <div>
            <label for="shift-filter" class="field-label">Plantões exibidos</label>
            <select wire:model.live="filter" id="shift-filter" class="field-input" @disabled($focus !== null)>
                <option value="">Todos no mês</option>
                <option value="scheduled">Agendados no mês</option>
                <option value="uncovered">Descobertos ativos ou futuros (todos os meses)</option>
            </select>
        </div>
        @if ($filter !== '' || $focus !== null)
            <a href="{{ route('manager.shifts', array_filter(['month' => $month, 'filter' => $focus !== null ? $filter : ''])) }}" wire:navigate class="text-link inline-flex min-h-10 items-center">{{ $focus !== null ? 'Ver lista de plantões' : 'Limpar filtros' }}</a>
        @endif
    </div>

    @if ($focus !== null)<p class="mt-2 text-sm text-muted">A seleção mostra este registro, independentemente dos filtros da lista.</p>@endif

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
                        <label for="end-time" class="field-label">Horário de término</label>
                        <input wire:model="endTime" id="end-time" type="time" class="field-input" @error('end_time') aria-invalid="true" aria-describedby="end-time-error" @enderror>
                        @error('end_time') <p id="end-time-error" class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="end-date" class="field-label">Data de término (opcional)</label>
                        <input wire:model="endDate" id="end-date" type="date" class="field-input" aria-describedby="end-date-help{{ $errors->has('end_date') ? ' end-date-error' : '' }}" @error('end_date') aria-invalid="true" @enderror>
                        <p id="end-date-help" class="mt-2 text-xs leading-5 text-muted">Para 24h, use o mesmo horário e a data do dia seguinte. Sem data, um horário final menor termina no dia seguinte; um horário maior termina no mesmo dia.</p>
                        @error('end_date') <p id="end-date-error" class="field-error">{{ $message }}</p> @enderror
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
                <x-shift-card :shift="$shift" :show-professional="true" wire:key="mobile-shift-{{ $shift->id }}" class="{{ $focus === $shift->id ? 'context-record' : '' }}" data-context-record="{{ $focus === $shift->id ? 'shift-'.$shift->id : '' }}">
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
                        <tr wire:key="shift-{{ $shift->id }}" class="{{ $focus === $shift->id ? 'context-record' : '' }}" data-context-record="{{ $focus === $shift->id ? 'shift-'.$shift->id : '' }}">
                            <td><strong>{{ $shift->date->format('d/m/Y') }}</strong><span>{{ $shift->time_label }}</span></td>
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

    @if ($showCopyModal)
        <div class="modal-overlay" x-data x-init="$nextTick(() => $refs.copySource?.focus())" @keydown.escape.window="$wire.closeCopyModal()">
            <button type="button" wire:click="closeCopyModal" class="modal-backdrop" aria-label="Fechar cópia de escala"></button>
            <div class="modal-dialog max-w-4xl" role="dialog" aria-modal="true" aria-labelledby="shift-copy-title">
                <header class="modal-header">
                    <div>
                        <p class="eyebrow">Duplicação assistida</p>
                        <h2 id="shift-copy-title">Copiar escala de outro mês</h2>
                    </div>
                    <button type="button" wire:click="closeCopyModal" class="modal-close-button" aria-label="Fechar cópia">
                        <x-nav-icon name="close" class="size-5" />
                    </button>
                </header>

                <div class="modal-body grid gap-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="copy-source-month" class="field-label">Mês de origem</label>
                            <input wire:model="copySourceMonth" id="copy-source-month" x-ref="copySource" type="month" class="field-input" @error('copySourceMonth') aria-invalid="true" aria-describedby="copy-source-error" @enderror>
                            @error('copySourceMonth') <p id="copy-source-error" class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="copy-target-month" class="field-label">Mês de destino</label>
                            <input wire:model="copyTargetMonth" id="copy-target-month" type="month" class="field-input" @error('copyTargetMonth') aria-invalid="true" aria-describedby="copy-target-error" @enderror>
                            @error('copyTargetMonth') <p id="copy-target-error" class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <fieldset>
                        <legend class="field-label">Mapeamento das datas</legend>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="record-card flex cursor-pointer gap-3">
                                <input type="radio" wire:model.live="copyMode" value="day" class="mt-1">
                                <span>
                                    <strong class="block text-sm">Mesmo dia do mês</strong>
                                    <span class="mt-1 block text-xs text-muted">Dia 05 origina dia 05. Dias inexistentes no destino (ex.: 31/02) ficam de fora.</span>
                                </span>
                            </label>
                            <label class="record-card flex cursor-pointer gap-3">
                                <input type="radio" wire:model.live="copyMode" value="weekday" class="mt-1">
                                <span>
                                    <strong class="block text-sm">Mesmo dia da semana</strong>
                                    <span class="mt-1 block text-xs text-muted">2ª terça-feira origina a 2ª terça-feira. Ocorrências inexistentes ficam de fora.</span>
                                </span>
                            </label>
                        </div>
                        @error('copyMode') <p class="field-error">{{ $message }}</p> @enderror
                    </fieldset>

                    <p class="text-sm text-muted">Trocas, ausências, notificações e comprovantes nunca são copiados. Plantões idênticos já existentes no destino não são sobrescritos.</p>

                    <div>
                        <button type="button" wire:click="generateCopyPreview" wire:loading.attr="disabled" wire:target="generateCopyPreview" class="button-secondary w-full sm:w-auto">
                            <span wire:loading.remove wire:target="generateCopyPreview">Gerar prévia</span>
                            <span wire:loading wire:target="generateCopyPreview">Gerando prévia…</span>
                        </button>
                    </div>

                    @if ($copyError !== '')
                        <div class="rounded-lg border border-danger/30 bg-danger-soft p-4" role="alert">
                            <p class="font-semibold text-danger">{{ $copyError }}</p>
                        </div>
                    @endif

                    @if ($copyPreview)
                        <div class="rounded-xl border border-line bg-canvas p-5" aria-live="polite">
                            <p class="text-sm font-semibold text-ink">
                                Prévia: {{ $copyPreview['totals']['total'] }} plantão(ões) ·
                                {{ $copyPreview['totals']['valid'] }} pronto(s) ·
                                {{ $copyPreview['totals']['adjusted'] }} ajustado(s) ·
                                {{ $copyPreview['totals']['blocked'] }} bloqueado(s)
                            </p>
                            <p class="mt-1 text-xs text-muted">{{ count($copySelected) }} selecionado(s) para copiar. Desmarque itens para excluí-los da cópia.</p>
                        </div>

                        @forelse ($copyPreview['items'] as $item)
                            <label class="record-card flex gap-3 {{ $item['status'] === 'blocked' ? 'opacity-75' : 'cursor-pointer' }}" wire:key="copy-preview-{{ $item['source_id'] }}">
                                <input
                                    type="checkbox"
                                    wire:model="copySelected"
                                    value="{{ $item['source_id'] }}"
                                    @disabled($item['status'] === 'blocked')
                                    class="mt-1"
                                    aria-label="Incluir plantão de {{ $item['date_label'] }}"
                                >
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <strong class="text-sm">{{ $item['date_label'] }} · {{ $item['time_label'] }}</strong>
                                        @if ($item['status'] === 'valid')
                                            <span class="status-badge status-scheduled">Pronto</span>
                                        @elseif ($item['status'] === 'adjusted')
                                            <span class="status-badge status-pending_manager">Ajustado</span>
                                        @else
                                            <span class="status-badge status-rejected">Bloqueado</span>
                                        @endif
                                    </span>
                                    <span class="mt-1 block text-xs text-muted">{{ $item['sector'] }} · {{ $item['category'] }} · {{ $item['professional'] }}</span>
                                    @foreach ($item['reasons'] as $reason)
                                        <span class="mt-1 block text-xs {{ $item['status'] === 'blocked' ? 'text-danger' : 'text-muted' }}">{{ $reason }}</span>
                                    @endforeach
                                </span>
                            </label>
                        @empty
                            <div class="empty-state compact-empty">
                                <p class="font-semibold text-ink">O mês de origem não possui plantões.</p>
                                <p class="mt-2 text-sm">Escolha outro mês de origem.</p>
                            </div>
                        @endforelse
                    @endif
                </div>

                <footer class="modal-footer">
                    <button type="button" wire:click="closeCopyModal" class="button-secondary w-full sm:w-auto">Cancelar</button>
                    <button type="button" wire:click="confirmCopy" wire:loading.attr="disabled" wire:target="confirmCopy" class="button-primary w-full sm:w-auto" @disabled($copyPreview === null || $copySelected === [])>
                        <span wire:loading.remove wire:target="confirmCopy">Confirmar cópia ({{ count($copySelected) }})</span>
                        <span wire:loading wire:target="confirmCopy">Copiando…</span>
                    </button>
                </footer>
            </div>
        </div>
    @endif

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
