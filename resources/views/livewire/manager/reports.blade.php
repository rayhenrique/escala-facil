<div>
    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Gerência</p>
            <h1 class="page-title">Relatórios</h1>
            <p class="page-description">Cobertura, trocas, ausências e desempenho por profissional no período.</p>
        </div>
        <div class="grid w-full gap-3 sm:w-auto sm:items-end">
            <div>
                <label for="report-month" class="field-label">Mês exibido</label>
                <input wire:model.live="month" id="report-month" type="month" class="field-input">
            </div>
            <a href="{{ route('manager.reports.pdf', ['month' => $month, 'sector_id' => $sectorId, 'category_id' => $categoryId]) }}" class="button-secondary w-full text-center sm:w-auto">Exportar PDF</a>
            <a href="{{ route('manager.reports.wall', ['month' => $month, 'sector_id' => $sectorId, 'category_id' => $categoryId]) }}" class="button-secondary w-full text-center sm:w-auto">PDF para afixar</a>
        </div>
    </div>

    <section class="panel mt-8" aria-label="Filtros do relatório">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="report-sector" class="field-label">Setor</label>
                <select wire:model.live="sectorId" id="report-sector" class="field-input">
                    <option value="">Todos os setores</option>
                    @foreach ($this->sectors as $sector)
                        <option value="{{ $sector->id }}">{{ $sector->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="report-category" class="field-label">Categoria</label>
                <select wire:model.live="categoryId" id="report-category" class="field-input">
                    <option value="">Todas as categorias</option>
                    @foreach ($this->categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @if ($this->report['hasActiveFilters'])
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <p class="text-sm text-muted">Exibindo dados filtrados.</p>
                <button type="button" wire:click="clearFilters" class="table-action">Limpar filtros</button>
            </div>
        @endif
    </section>

    <section class="coverage-board mt-8" aria-labelledby="report-summary-title">
        <div class="coverage-board-heading">
            <p class="eyebrow text-white/75">Resumo do mês</p>
            <h2 id="report-summary-title" class="capitalize">{{ $this->report['monthLabel'] }}</h2>
            <p>{{ $this->report['summary']['total'] }} plantão(ões) no período selecionado.</p>
        </div>
        <dl class="coverage-metrics">
            <div>
                <dt>Cobertura</dt>
                <dd>{{ $this->report['summary']['coverageRate'] === null ? '—' : $this->report['summary']['coverageRate'].'%' }}</dd>
                <span>{{ $this->report['summary']['covered'] }} de {{ $this->report['summary']['total'] }} cobertos</span>
            </div>
            <div class="coverage-metric-danger">
                <dt>Descobertos</dt>
                <dd>{{ $this->report['summary']['uncovered'] }}</dd>
                <span>Sem profissional</span>
            </div>
            <div class="coverage-metric-pending">
                <dt>Trocas aprovadas</dt>
                <dd>{{ $this->report['summary']['swapsApproved'] }}</dd>
                <span>No mês de criação</span>
            </div>
            <div>
                <dt>Ausências</dt>
                <dd>{{ $this->report['summary']['absences'] }}</dd>
                <span>Justificativas no mês</span>
            </div>
        </dl>
    </section>

    <div class="mt-10 grid gap-8 xl:grid-cols-2">
        <section aria-labelledby="report-sector-title">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Distribuição</p>
                    <h2 id="report-sector-title">Plantões por setor</h2>
                </div>
            </div>
            <div class="mt-4 hidden overflow-x-auto md:block">
                <table class="data-table">
                    <thead><tr><th>Setor</th><th class="text-right">Plantões</th><th class="text-right">Descobertos</th></tr></thead>
                    <tbody>
                        @forelse ($this->report['bySector'] as $row)
                            <tr><td><strong>{{ $row['name'] }}</strong></td><td class="text-right">{{ $row['total'] }}</td><td class="text-right">{{ $row['uncovered'] }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="py-12 text-center text-muted">Nenhum plantão no período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4 space-y-4 md:hidden">
                @forelse ($this->report['bySector'] as $row)
                    <article class="record-card">
                        <h3 class="font-semibold">{{ $row['name'] }}</h3>
                        <p class="mt-1 text-sm text-muted">{{ $row['total'] }} plantão(ões) · {{ $row['uncovered'] }} descoberto(s)</p>
                    </article>
                @empty
                    <div class="empty-state compact-empty"><p class="font-semibold text-ink">Nenhum plantão no período.</p></div>
                @endforelse
            </div>
        </section>

        <section aria-labelledby="report-category-title">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Distribuição</p>
                    <h2 id="report-category-title">Plantões por categoria</h2>
                </div>
            </div>
            <div class="mt-4 hidden overflow-x-auto md:block">
                <table class="data-table">
                    <thead><tr><th>Categoria</th><th class="text-right">Plantões</th><th class="text-right">Descobertos</th></tr></thead>
                    <tbody>
                        @forelse ($this->report['byCategory'] as $row)
                            <tr><td><strong>{{ $row['name'] }}</strong></td><td class="text-right">{{ $row['total'] }}</td><td class="text-right">{{ $row['uncovered'] }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="py-12 text-center text-muted">Nenhum plantão no período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4 space-y-4 md:hidden">
                @forelse ($this->report['byCategory'] as $row)
                    <article class="record-card">
                        <h3 class="font-semibold">{{ $row['name'] }}</h3>
                        <p class="mt-1 text-sm text-muted">{{ $row['total'] }} plantão(ões) · {{ $row['uncovered'] }} descoberto(s)</p>
                    </article>
                @empty
                    <div class="empty-state compact-empty"><p class="font-semibold text-ink">Nenhum plantão no período.</p></div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="mt-10" aria-labelledby="report-professionals-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Desempenho</p>
                <h2 id="report-professionals-title">Plantões por profissional</h2>
            </div>
            <span class="text-sm text-muted">{{ $this->report['byProfessional']->count() }} profissional(is)</span>
        </div>
        <div class="mt-4 hidden overflow-x-auto md:block">
            <table class="data-table">
                <thead><tr><th>Profissional</th><th>Setor / categoria</th><th class="text-right">Plantões</th><th class="text-right">Trocas aprovadas</th><th class="text-right">Ausências</th></tr></thead>
                <tbody>
                    @forelse ($this->report['byProfessional'] as $row)
                        <tr>
                            <td><strong>{{ $row['name'] }}</strong></td>
                            <td>{{ $row['team'] }}</td>
                            <td class="text-right">{{ $row['shifts'] }}</td>
                            <td class="text-right">{{ $row['approvedSwaps'] }} de 4</td>
                            <td class="text-right">{{ $row['absences'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-12 text-center text-muted">Nenhum profissional alocado no período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 space-y-4 md:hidden">
            @forelse ($this->report['byProfessional'] as $row)
                <article class="record-card">
                    <h3 class="font-semibold">{{ $row['name'] }}</h3>
                    <p class="mt-1 text-sm text-muted">{{ $row['team'] }}</p>
                    <p class="mt-1 text-sm text-muted">{{ $row['shifts'] }} plantão(ões) · {{ $row['approvedSwaps'] }} de 4 trocas · {{ $row['absences'] }} ausência(s)</p>
                </article>
            @empty
                <div class="empty-state compact-empty"><p class="font-semibold text-ink">Nenhum profissional alocado no período.</p></div>
            @endforelse
        </div>
    </section>

    <section class="mt-10" aria-labelledby="report-swaps-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Movimentações</p>
                <h2 id="report-swaps-title">Trocas criadas no mês</h2>
            </div>
            <span class="text-sm text-muted">{{ $this->report['swaps']->count() }} troca(s)</span>
        </div>
        <div class="mt-4 hidden overflow-x-auto md:block">
            <table class="data-table">
                <thead><tr><th>Plantão</th><th>Solicitante → substituto</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($this->report['swaps'] as $swap)
                        <tr>
                            <td><strong>{{ $swap->shift->date->format('d/m/Y') }}</strong><span>{{ substr((string) $swap->shift->start_time, 0, 5) }}–{{ substr((string) $swap->shift->end_time, 0, 5) }} · {{ $swap->shift->sector->name }}</span></td>
                            <td>{{ $swap->requester->name }} → {{ $swap->substitute->name }}</td>
                            <td><x-status-badge :status="$swap->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-12 text-center text-muted">Nenhuma troca criada neste mês.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 space-y-4 md:hidden">
            @forelse ($this->report['swaps'] as $swap)
                <article class="record-card">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-semibold">{{ $swap->shift->date->format('d/m/Y') }}</h3>
                        <x-status-badge :status="$swap->status" />
                    </div>
                    <p class="mt-1 text-sm text-muted">{{ $swap->requester->name }} → {{ $swap->substitute->name }} · {{ $swap->shift->sector->name }}</p>
                </article>
            @empty
                <div class="empty-state compact-empty"><p class="font-semibold text-ink">Nenhuma troca criada neste mês.</p></div>
            @endforelse
        </div>
    </section>
</div>
