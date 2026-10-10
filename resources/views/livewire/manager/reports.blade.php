<div>
    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">{{ $isProfessional ? 'Profissional' : 'Gerência' }}</p>
            <h1 class="page-title">Relatórios</h1>
            <p class="page-description">Painel analítico modular de cobertura, produtividade individual, trocas e escalas oficiais.</p>
        </div>
        <div class="grid w-full gap-3 sm:w-auto sm:grid-cols-3 sm:items-end">
            <div>
                <label for="report-month" class="field-label">Mês exibido</label>
                <input wire:model.live="month" id="report-month" type="month" class="field-input">
            </div>
            <a href="{{ route($routePrefix.'reports.pdf', ['month' => $month, 'sector_id' => $sectorId, 'category_id' => $categoryId]) }}" class="button-secondary w-full text-center sm:w-auto" title="Baixar relatório analítico consolidado em PDF">Exportar PDF</a>
            <a href="{{ route($routePrefix.'reports.wall', ['month' => $month, 'sector_id' => $sectorId, 'category_id' => $categoryId]) }}" class="button-secondary w-full text-center sm:w-auto" title="Baixar grade mensal oficial em PDF para mural">PDF para afixar</a>
        </div>
    </div>

    {{-- Navegação de Submódulos de Relatório --}}
    <div class="mt-8 border-b border-line pb-4">
        <div class="flex items-center justify-between gap-3 mb-2.5">
            <span class="meta-label">Submódulos de Relatório</span>
            <span class="text-xs text-muted hidden sm:inline">Selecione o tipo de relatório desejado</span>
        </div>
        <nav class="flex flex-wrap items-center gap-2" aria-label="Submódulos de relatório">
            <button
                type="button"
                wire:click="setTab('all')"
                @class([
                    'inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold transition-all shadow-xs cursor-pointer',
                    'bg-primary text-white shadow-sm ring-1 ring-primary' => $tab === 'all',
                    'bg-white text-muted border border-line hover:text-ink hover:bg-canvas' => $tab !== 'all',
                ])
                aria-current="{{ $tab === 'all' ? 'page' : 'false' }}"
            >
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                </svg>
                <span>Visão Consolidada</span>
                <span @class(['ml-1 rounded-full px-1.5 py-0.5 font-mono text-[0.6875rem]', 'bg-white/20 text-white' => $tab === 'all', 'bg-line/60 text-muted' => $tab !== 'all'])>Todos</span>
            </button>

            <button
                type="button"
                wire:click="setTab('coverage')"
                @class([
                    'inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold transition-all shadow-xs cursor-pointer',
                    'bg-primary text-white shadow-sm ring-1 ring-primary' => $tab === 'coverage',
                    'bg-white text-muted border border-line hover:text-ink hover:bg-canvas' => $tab !== 'coverage',
                ])
                aria-current="{{ $tab === 'coverage' ? 'page' : 'false' }}"
            >
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
                </svg>
                <span>Cobertura e Indicadores</span>
                @if ($this->report['summary']['coverageRate'] !== null)
                    <span @class(['ml-1 rounded-full px-1.5 py-0.5 font-mono text-[0.6875rem]', 'bg-white/20 text-white' => $tab === 'coverage', 'bg-coverage/15 text-coverage font-bold' => $tab !== 'coverage'])>{{ $this->report['summary']['coverageRate'] }}%</span>
                @endif
            </button>

            <button
                type="button"
                wire:click="setTab('professionals')"
                @class([
                    'inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold transition-all shadow-xs cursor-pointer',
                    'bg-primary text-white shadow-sm ring-1 ring-primary' => $tab === 'professionals',
                    'bg-white text-muted border border-line hover:text-ink hover:bg-canvas' => $tab !== 'professionals',
                ])
                aria-current="{{ $tab === 'professionals' ? 'page' : 'false' }}"
            >
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>
                <span>Por Profissional</span>
                <span @class(['ml-1 rounded-full px-1.5 py-0.5 font-mono text-[0.6875rem]', 'bg-white/20 text-white' => $tab === 'professionals', 'bg-line/60 text-muted' => $tab !== 'professionals'])>{{ $this->report['byProfessional']->count() }}</span>
            </button>

            <button
                type="button"
                wire:click="setTab('swaps')"
                @class([
                    'inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold transition-all shadow-xs cursor-pointer',
                    'bg-primary text-white shadow-sm ring-1 ring-primary' => $tab === 'swaps',
                    'bg-white text-muted border border-line hover:text-ink hover:bg-canvas' => $tab !== 'swaps',
                ])
                aria-current="{{ $tab === 'swaps' ? 'page' : 'false' }}"
            >
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                </svg>
                <span>Trocas de Plantão</span>
                <span @class(['ml-1 rounded-full px-1.5 py-0.5 font-mono text-[0.6875rem]', 'bg-white/20 text-white' => $tab === 'swaps', 'bg-amber-100 text-amber-800 font-semibold' => $tab !== 'swaps'])>{{ $this->report['swaps']->count() }}</span>
            </button>

            <button
                type="button"
                wire:click="setTab('absences')"
                @class([
                    'inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold transition-all shadow-xs cursor-pointer',
                    'bg-primary text-white shadow-sm ring-1 ring-primary' => $tab === 'absences',
                    'bg-white text-muted border border-line hover:text-ink hover:bg-canvas' => $tab !== 'absences',
                ])
                aria-current="{{ $tab === 'absences' ? 'page' : 'false' }}"
            >
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <span>Ausências</span>
                <span @class(['ml-1 rounded-full px-1.5 py-0.5 font-mono text-[0.6875rem]', 'bg-white/20 text-white' => $tab === 'absences', 'bg-red-100 text-danger font-semibold' => $tab !== 'absences'])>{{ $this->report['summary']['absences'] }}</span>
            </button>

            <button
                type="button"
                wire:click="setTab('wall')"
                @class([
                    'inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold transition-all shadow-xs cursor-pointer',
                    'bg-primary text-white shadow-sm ring-1 ring-primary' => $tab === 'wall',
                    'bg-white text-muted border border-line hover:text-ink hover:bg-canvas' => $tab !== 'wall',
                ])
                aria-current="{{ $tab === 'wall' ? 'page' : 'false' }}"
            >
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                </svg>
                <span>Escala de Mural</span>
                <span @class(['ml-1 rounded-full px-1.5 py-0.5 font-mono text-[0.6875rem]', 'bg-white/20 text-white' => $tab === 'wall', 'bg-line/60 text-muted' => $tab !== 'wall'])>Afixar</span>
            </button>
        </nav>
    </div>

    {{-- Filtros do relatório --}}
    <section class="panel mt-6" aria-label="Filtros do relatório">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="report-sector" class="field-label">Setor</label>
                @if ($isProfessional)
                    <input type="text" id="report-sector" class="field-input bg-surface-muted text-muted font-medium cursor-not-allowed" value="{{ auth()->user()->sector?->name ?? 'Seu setor' }}" disabled readonly aria-label="Setor vinculado">
                @else
                    <select wire:model.live="sectorId" id="report-sector" class="field-input">
                        <option value="">Todos os setores</option>
                        @foreach ($this->sectors as $sector)
                            <option value="{{ $sector->id }}">{{ $sector->name }}</option>
                        @endforeach
                    </select>
                @endif
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
        @if ($this->report['hasActiveFilters'] || $searchProfessional !== '' || $swapStatusFilter !== '')
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <p class="text-sm text-muted">Exibindo dados com filtros ativos.</p>
                <button type="button" wire:click="clearFilters" class="table-action">Limpar filtros</button>
            </div>
        @endif
    </section>

    {{-- SUBMÓDULO 1: Cobertura & Indicadores Gerais --}}
    @if ($tab === 'all' || $tab === 'coverage')
        <div class="mt-8">
            @if ($tab === 'coverage')
                <div class="mb-4">
                    <p class="eyebrow">Submódulo</p>
                    <h2 class="text-xl font-bold text-ink">Cobertura e Indicadores Operacionais</h2>
                    <p class="text-sm text-muted">Análise de taxa de preenchimento de plantões, desfalques e distribuição por setores e categorias.</p>
                </div>
            @endif

            <section class="coverage-board" aria-labelledby="report-summary-title">
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

            <div class="mt-8 grid gap-8 xl:grid-cols-2">
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
        </div>
    @endif

    {{-- SUBMÓDULO 2: Plantões por Profissional --}}
    @if ($tab === 'all' || $tab === 'professionals')
        <section class="mt-10 pt-8 {{ $tab === 'all' ? 'border-t border-line' : '' }}" aria-labelledby="report-professionals-title">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="eyebrow">Desempenho</p>
                    <h2 id="report-professionals-title" class="text-xl font-bold">Plantões por profissional</h2>
                    <p class="mt-1 text-sm text-muted">Acompanhamento de alocação de plantões, cota mensal de trocas (máx. 4) e ausências.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-sm font-semibold text-muted">{{ $this->report['byProfessional']->count() }} profissional(is)</span>
                    <input wire:model.live.debounce.250ms="searchProfessional" type="search" placeholder="Filtrar por nome..." class="field-input min-h-10 text-xs sm:w-56" aria-label="Filtrar profissionais por nome">
                </div>
            </div>

            <div class="mt-4 hidden overflow-x-auto md:block">
                <table class="data-table">
                    <thead><tr><th>Profissional</th><th>Setor / categoria</th><th class="text-right">Plantões</th><th class="text-right">Trocas aprovadas</th><th class="text-right">Ausências</th></tr></thead>
                    <tbody>
                        @forelse ($this->report['byProfessional'] as $row)
                            <tr>
                                <td>
                                    <strong>{{ $row['name'] }}</strong>
                                </td>
                                <td>{{ $row['team'] }}</td>
                                <td class="text-right font-semibold">{{ $row['shifts'] }}</td>
                                <td class="text-right">
                                    <span @class([
                                        'inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold',
                                        'bg-emerald-50 text-emerald-700' => $row['approvedSwaps'] < 3,
                                        'bg-amber-100 text-amber-800' => $row['approvedSwaps'] === 3,
                                        'bg-red-100 text-danger font-bold' => $row['approvedSwaps'] >= 4,
                                    ])>
                                        {{ $row['approvedSwaps'] }} de 4
                                        @if ($row['approvedSwaps'] >= 4)
                                            <span class="ml-1 text-[0.625rem] uppercase tracking-wider">(Limite)</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="text-right">
                                    @if ($row['absences'] > 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-red-50 text-danger font-semibold text-xs">{{ $row['absences'] }}</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
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
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="font-semibold">{{ $row['name'] }}</h3>
                            <span @class([
                                'px-2 py-0.5 rounded text-xs font-semibold',
                                'bg-emerald-50 text-emerald-700' => $row['approvedSwaps'] < 3,
                                'bg-amber-100 text-amber-800' => $row['approvedSwaps'] === 3,
                                'bg-red-100 text-danger' => $row['approvedSwaps'] >= 4,
                            ])>{{ $row['approvedSwaps'] }}/4 trocas</span>
                        </div>
                        <p class="mt-1 text-sm text-muted">{{ $row['team'] }}</p>
                        <p class="mt-1 text-sm text-muted">{{ $row['shifts'] }} plantão(ões) · {{ $row['approvedSwaps'] }} de 4 trocas · {{ $row['absences'] }} ausência(s)</p>
                    </article>
                @empty
                    <div class="empty-state compact-empty"><p class="font-semibold text-ink">Nenhum profissional alocado no período.</p></div>
                @endforelse
            </div>
        </section>
    @endif

    {{-- SUBMÓDULO 3: Movimentações de Trocas --}}
    @if ($tab === 'all' || $tab === 'swaps')
        <section class="mt-10 pt-8 {{ $tab === 'all' ? 'border-t border-line' : '' }}" aria-labelledby="report-swaps-title">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="eyebrow">Movimentações</p>
                    <h2 id="report-swaps-title" class="text-xl font-bold">{{ ($this->report['isManager'] ?? true) ? 'Trocas criadas no mês' : 'Minhas trocas no mês' }}</h2>
                    <p class="mt-1 text-sm text-muted">Auditoria de solicitações, permutas aceitas, aprovações gerenciais e comprovantes.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-semibold text-muted">{{ $this->report['swaps']->count() }} troca(s)</span>
                    @if ($tab === 'swaps')
                        <select wire:model.live="swapStatusFilter" class="field-input min-h-10 text-xs sm:w-48" aria-label="Filtrar trocas por situação">
                            <option value="">Todas as situações</option>
                            <option value="approved">Aprovadas</option>
                            <option value="pending_manager">Aguardando Gerência</option>
                            <option value="pending_professional">Aguardando Profissional</option>
                            <option value="rejected">Rejeitadas</option>
                            <option value="reverted">Desfeitas</option>
                        </select>
                    @endif
                </div>
            </div>

            <div class="mt-4 hidden overflow-x-auto md:block">
                <table class="data-table">
                    <thead><tr><th>Plantão</th><th>Solicitante → substituto</th><th>Status</th><th class="text-right">Ação</th></tr></thead>
                    <tbody>
                        @forelse ($this->report['swaps'] as $swap)
                            <tr>
                                <td>
                                    <strong>{{ $swap->shift->date->format('d/m/Y') }}</strong>
                                    <span>{{ $swap->shift->time_label }} · {{ $swap->shift->sector->name }}</span>
                                </td>
                                <td>
                                    <div class="font-medium text-ink">{{ $swap->requester->name }} → {{ $swap->substitute->name }}</div>
                                    <small class="text-xs text-muted">{{ $swap->substitute_shift_id ? '⇄ Troca mútua' : '→ Cedência simples' }}</small>
                                </td>
                                <td><x-status-badge :status="$swap->status" /></td>
                                <td class="text-right">
                                    <a href="{{ route('swaps.receipt.pdf', $swap->id) }}" class="table-action text-xs" title="Baixar comprovante oficial em PDF">
                                        Salvar PDF
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-12 text-center text-muted">{{ ($this->report['isManager'] ?? true) ? 'Nenhuma troca criada neste mês.' : 'Você não possui trocas neste mês.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4 space-y-4 md:hidden">
                @forelse ($this->report['swaps'] as $swap)
                    <article class="record-card">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="font-semibold">{{ $swap->shift->date->format('d/m/Y') }}</h3>
                            <x-status-badge :status="$swap->status" />
                        </div>
                        <p class="mt-1 text-sm text-muted">{{ $swap->requester->name }} → {{ $swap->substitute->name }} · {{ $swap->shift->sector->name }}</p>
                        <div class="mt-3 flex items-center justify-between border-t border-line/60 pt-2 text-xs">
                            <span class="text-muted">{{ $swap->substitute_shift_id ? 'Troca mútua' : 'Cedência simples' }}</span>
                            <a href="{{ route('swaps.receipt.pdf', $swap->id) }}" class="text-link font-semibold">Salvar PDF</a>
                        </div>
                    </article>
                @empty
                    <div class="empty-state compact-empty"><p class="font-semibold text-ink">{{ ($this->report['isManager'] ?? true) ? 'Nenhuma troca criada neste mês.' : 'Você não possui trocas neste mês.' }}</p></div>
                @endforelse
            </div>
        </section>
    @endif

    {{-- SUBMÓDULO 4: Ausências & Justificativas --}}
    @if ($tab === 'all' || $tab === 'absences')
        <section class="mt-10 pt-8 {{ $tab === 'all' ? 'border-t border-line' : '' }}" aria-labelledby="report-absences-title">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="eyebrow">Justificativas</p>
                    <h2 id="report-absences-title" class="text-xl font-bold">Ausências e justificativas</h2>
                    <p class="mt-1 text-sm text-muted">Histórico de ausências notificadas por plantonistas e motivos registrados.</p>
                </div>
                <span class="text-sm font-semibold text-muted">{{ count($this->report['absencesList'] ?? []) }} registro(s)</span>
            </div>

            <div class="mt-4 hidden overflow-x-auto md:block">
                <table class="data-table">
                    <thead><tr><th>Plantão</th><th>Profissional</th><th>Setor / categoria</th><th>Motivo registrado</th><th>Comprovante</th></tr></thead>
                    <tbody>
                        @forelse ($this->report['absencesList'] ?? [] as $absence)
                            <tr>
                                <td>
                                    <strong>{{ $absence->shift->date->format('d/m/Y') }}</strong>
                                    <span>{{ $absence->shift->time_label }}</span>
                                </td>
                                <td><strong>{{ $absence->user->name }}</strong></td>
                                <td>{{ $absence->shift->sector->name }} · {{ $absence->shift->category->name }}</td>
                                <td class="max-w-xs break-words text-sm">{{ $absence->reason }}</td>
                                <td>
                                    @if ($absence->attachment_path)
                                        <a href="{{ \Illuminate\Support\Facades\Storage::url($absence->attachment_path) }}" target="_blank" rel="noopener noreferrer" class="text-link text-xs">Ver anexo</a>
                                    @else
                                        <span class="text-xs text-muted">Sem anexo</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-12 text-center text-muted">Nenhuma ausência registrada no período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4 space-y-4 md:hidden">
                @forelse ($this->report['absencesList'] ?? [] as $absence)
                    <article class="record-card">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="font-semibold">{{ $absence->user->name }}</h3>
                            <span class="text-xs font-mono text-muted">{{ $absence->shift->date->format('d/m/Y') }}</span>
                        </div>
                        <p class="mt-1 text-sm text-muted">{{ $absence->shift->sector->name }} · {{ $absence->shift->category->name }}</p>
                        <p class="mt-2 text-sm text-ink italic">"{{ $absence->reason }}"</p>
                        @if ($absence->attachment_path)
                            <div class="mt-2 pt-2 border-t border-line text-xs">
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($absence->attachment_path) }}" target="_blank" rel="noopener noreferrer" class="text-link">Ver comprovante anexo</a>
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="empty-state compact-empty"><p class="font-semibold text-ink">Nenhuma ausência registrada no período.</p></div>
                @endforelse
            </div>
        </section>
    @endif

    {{-- SUBMÓDULO 5: Escala Oficial de Mural (Afixação) --}}
    @if ($tab === 'all' || $tab === 'wall')
        <section class="mt-10 pt-8 {{ $tab === 'all' ? 'border-t border-line' : '' }}" aria-labelledby="report-wall-title">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="eyebrow">Afixação</p>
                    <h2 id="report-wall-title" class="text-xl font-bold">Escala de mural (afixação oficial)</h2>
                    <p class="mt-1 text-sm text-muted">Grade organizada por dia e turno, pronta para impressão e afixação física nos postos de saúde.</p>
                </div>
                <a href="{{ route($routePrefix.'reports.wall', ['month' => $month, 'sector_id' => $sectorId, 'category_id' => $categoryId]) }}" class="button-primary inline-flex items-center gap-2" title="Gerar PDF formatado A4 horizontal para mural">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span>Baixar PDF para afixar</span>
                </a>
            </div>

            <div class="mt-6 rounded-xl border border-line bg-white p-5 shadow-soft">
                <div class="flex items-center justify-between border-b border-line pb-4">
                    <div>
                        <h3 class="font-semibold text-ink">{{ $this->wallRoster['monthLabel'] }}</h3>
                        <p class="text-xs text-muted">{{ $this->wallRoster['total'] }} plantão(ões) previstos</p>
                    </div>
                    <span class="rounded-full bg-primary-soft px-3 py-1 font-mono text-xs font-bold text-primary">A4 Horizontal</span>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 max-h-[32rem] overflow-y-auto pr-1">
                    @forelse ($this->wallRoster['days'] as $dateStr => $dayShifts)
                        <div class="rounded-lg border border-line bg-canvas p-3 text-xs">
                            <div class="flex items-center justify-between border-b border-line/60 pb-1.5 font-bold text-primary">
                                <span>{{ \Carbon\CarbonImmutable::parse($dateStr)->format('d/m/Y') }}</span>
                                <span class="uppercase tracking-wider text-[0.625rem] text-muted">{{ \Carbon\CarbonImmutable::parse($dateStr)->translatedFormat('l') }}</span>
                            </div>
                            <div class="mt-2 space-y-2">
                                @foreach ($dayShifts as $shift)
                                    <div class="rounded bg-white p-2 border border-line/80">
                                        <div class="flex items-center justify-between text-muted text-[0.6875rem]">
                                            <span>{{ $shift->time_label }}</span>
                                            <span>{{ $shift->sector->name }}</span>
                                        </div>
                                        <div class="mt-1 font-semibold text-ink">
                                            @if ($shift->user)
                                                <span>{{ $shift->user->name }}</span>
                                            @else
                                                <span class="text-danger italic">Sem plantonista</span>
                                            @endif
                                        </div>
                                        @if (in_array($shift->id, $this->wallRoster['swappedShiftIds'], true))
                                            <span class="mt-1 inline-flex items-center gap-1 rounded bg-amber-50 px-1.5 py-0.5 text-[0.625rem] font-bold text-amber-800">
                                                ⇄ Troca realizada
                                            </span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full py-8 text-center text-muted text-sm">Nenhum plantão encontrado para a escala de mural neste período.</div>
                    @endforelse
                </div>
            </div>
        </section>
    @endif
</div>
