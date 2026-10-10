<div>
    <x-flash-notifications />
    <x-context-navigation :back="$isProfessional && $from === '' ? null : $this->backNavigation()" :links="array_filter([
        'Abrir escala deste mês' => $canManageShifts ? \App\Support\OperationalNavigation::url('shifts', ['month' => $month], 'calendar', $this->navigationContext()) : null,
        'Ver relatório' => \App\Support\OperationalNavigation::url('reports', ['month' => $month, 'sectorId' => $sectorId, 'categoryId' => $categoryId], 'calendar', $this->navigationContext()),
    ])" />
    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Visão mensal</p>
            <h1 class="page-title" tabindex="-1" data-decision-return>Calendário de plantões</h1>
            <p class="page-description">Selecione um dia para consultar todos os plantões e profissionais escalados.</p>
        </div>

        <div class="flex w-full flex-wrap items-end gap-2 sm:w-auto">
            <button type="button" wire:click="previousMonth" wire:loading.attr="disabled" class="button-secondary size-12 p-0" aria-label="Mês anterior">
                <span aria-hidden="true">‹</span>
            </button>
            <div class="min-w-0 flex-1 sm:w-48 sm:flex-none">
                <label for="calendar-month" class="field-label">Mês exibido</label>
                <input wire:model.live="month" id="calendar-month" type="month" class="field-input">
            </div>
            <button type="button" wire:click="nextMonth" wire:loading.attr="disabled" class="button-secondary size-12 p-0" aria-label="Próximo mês">
                <span aria-hidden="true">›</span>
            </button>
            <button type="button" wire:click="goToToday" wire:loading.attr="disabled" class="button-secondary min-h-12 px-4">Hoje</button>
        </div>
    </div>

    <section class="panel mt-8" aria-labelledby="calendar-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Agenda completa</p>
                <h2 id="calendar-title" class="capitalize">{{ $monthLabel }}</h2>
            </div>
            <div class="flex flex-wrap gap-3 text-xs text-muted" aria-label="Legenda do calendário">
                <span class="inline-flex items-center gap-2"><span class="size-2.5 rounded-full bg-coverage"></span>Com plantão</span>
                <span class="inline-flex items-center gap-2"><span class="size-2.5 rounded-full bg-danger"></span>Sem profissional</span>
                <span class="inline-flex items-center gap-2"><span class="font-bold text-coverage" aria-hidden="true">✓</span>24h cobertas</span>
                <span class="inline-flex items-center gap-2"><span class="size-2.5 rounded-full bg-amber-500"></span><span class="font-bold text-amber-600" aria-hidden="true">⇄</span>Troca realizada</span>
                @if ($hasSectorFilter)
                    <span class="inline-flex items-center gap-1.5"><span class="rounded bg-primary/10 px-1 py-0.5 font-mono text-[10px] font-bold text-primary border border-primary/20">AB</span>Iniciais do plantonista</span>
                @endif
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="calendar-sector" class="field-label">Setor</label>
                @if ($isProfessional)
                    <input type="text" id="calendar-sector" class="field-input bg-surface-muted text-muted font-medium cursor-not-allowed" value="{{ auth()->user()->sector?->name ?? 'Seu setor' }}" disabled readonly aria-label="Setor vinculado">
                @else
                    <select wire:model.live="sectorId" id="calendar-sector" class="field-input">
                        <option value="">Todos os setores</option>
                        @foreach ($this->sectors as $sector)
                            <option value="{{ $sector->id }}">{{ $sector->name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div>
                <label for="calendar-category" class="field-label">Categoria</label>
                <select wire:model.live="categoryId" id="calendar-category" class="field-input">
                    <option value="">Todas as categorias</option>
                    @foreach ($this->categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($hasActiveFilters)
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <p class="text-sm text-muted">Exibindo plantões filtrados.</p>
                <button type="button" wire:click="clearFilters" class="table-action">Limpar filtros</button>
            </div>
        @endif

        <p wire:loading.delay class="mt-4 text-sm text-muted" role="status">Atualizando calendário…</p>

        <div class="calendar-grid mt-6" role="grid" aria-label="Calendário de {{ $monthLabel }}">
            <div class="contents" role="row">
            @foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $weekday)
                <div class="calendar-weekday" role="columnheader">{{ $weekday }}</div>
            @endforeach
            </div>

            @foreach ($calendarDays->chunk(7) as $week)
            <div class="contents" role="row">
            @foreach ($week as $day)
                <button
                    type="button"
                    wire:click="selectDate('{{ $day['date'] }}')"
                    class="calendar-day {{ ! $day['in_month'] ? 'calendar-day-outside' : '' }} {{ $selectedDate === $day['date'] ? 'calendar-day-selected' : '' }}"
                    @disabled(! $day['in_month'])
                    role="gridcell"
                    @if ($selectedDate === $day['date']) aria-selected="true" @endif
                    aria-label="{{ \Carbon\CarbonImmutable::parse($day['date'])->format('d/m/Y') }}, {{ $day['shifts_count'] }} plantão(ões){{ $day['covered_24h'] ? ', 24h cobertas' : '' }}{{ $day['has_swap'] ? ', com troca de plantão' : '' }}"
                >
                    <span class="calendar-day-number {{ $day['is_today'] ? 'calendar-day-today' : '' }}">{{ $day['day'] }}</span>

                    @if ($day['in_month'] && $day['shifts_count'] > 0)
                        <span class="calendar-day-count">{{ $day['shifts_count'] }}</span>
                        <span class="sr-only">plantão(ões)</span>
                    @endif

                    @if ($day['in_month'] && $hasSectorFilter && ! empty($day['professionals']))
                        <div class="calendar-day-initials" aria-label="Profissionais escalados">
                            @foreach (array_slice($day['professionals'], 0, 3) as $prof)
                                <span
                                    class="calendar-initial-badge {{ $prof['is_uncovered'] ? 'calendar-initial-uncovered' : ($prof['is_swapped'] ? 'calendar-initial-swapped' : 'calendar-initial-default') }}"
                                    title="{{ $prof['name'] }} ({{ $prof['time'] }}){{ $prof['is_swapped'] ? ' · Troca realizada' : '' }}"
                                >
                                    {{ $prof['initials'] }}
                                </span>
                            @endforeach
                            @if (count($day['professionals']) > 3)
                                <span
                                    class="calendar-initial-badge calendar-initial-more"
                                    title="{{ count($day['professionals']) - 3 }} outro(s) profissional(is): {{ collect(array_slice($day['professionals'], 3))->pluck('name')->implode(', ') }}"
                                >
                                    +{{ count($day['professionals']) - 3 }}
                                </span>
                            @endif
                        </div>
                    @endif

                    @if ($day['in_month'] && $day['uncovered_count'] > 0)
                        <span class="calendar-day-alert" title="{{ $day['uncovered_count'] }} plantão(ões) sem profissional" aria-label="{{ $day['uncovered_count'] }} plantão(ões) sem profissional"></span>
                    @elseif ($day['covered_24h'])
                        <span class="calendar-day-full" title="24h cobertas" aria-label="24h cobertas">✓</span>
                    @elseif ($day['in_month'] && $day['shifts_count'] > 0)
                        <span class="calendar-day-covered" aria-hidden="true"></span>
                    @endif

                    @if ($day['in_month'] && $day['has_swap'])
                        <span class="calendar-day-swap" title="{{ $day['swap_tooltip'] ?? 'Dia com troca de plantão aprovada' }}" aria-label="{{ $day['swap_tooltip'] ?? 'Dia com troca de plantão' }}">⇄</span>
                    @endif
                </button>
            @endforeach
            </div>
            @endforeach
        </div>
    </section>

    <section class="mt-8" aria-labelledby="selected-day-title" aria-live="polite">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Plantões do dia</p>
                <h2 id="selected-day-title" class="capitalize">{{ $selectedDateLabel }}</h2>
            </div>
            <p class="text-sm text-muted">
                {{ $selectedShifts->count() }} plantão(ões) · {{ $assignedProfessionalsCount }} profissional(is)
            </p>
        </div>

        <div class="mt-5 grid gap-4 xl:grid-cols-2">
            @forelse ($selectedShifts as $shift)
                <article class="daily-shift-card {{ in_array($shift->id, $swappedShiftIds, true) ? 'daily-shift-card-swapped' : '' }}" wire:key="calendar-shift-{{ $shift->id }}">
                    <div class="professional-avatar {{ $shift->user ? '' : 'professional-avatar-empty' }}" aria-hidden="true">
                        {{ $shift->user ? mb_strtoupper(mb_substr($shift->user->name, 0, 1)) : '!' }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold">{{ $shift->user?->name ?? 'Plantão sem profissional' }}</h3>
                            <x-status-badge :status="$shift->status" />
                            @if (isset($swappedShiftsDetails[$shift->id]))
                                @php($swapInfo = $swappedShiftsDetails[$shift->id])
                                <div class="relative inline-flex items-center group">
                                    <span
                                        tabindex="0"
                                        role="note"
                                        aria-label="{{ $swapInfo['tooltip'] }}"
                                        title="{{ $swapInfo['tooltip'] }}"
                                        class="inline-flex items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800 cursor-help transition-all hover:bg-amber-100 hover:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400"
                                    >
                                        <span aria-hidden="true">⇄</span> Troca realizada
                                    </span>

                                    <!-- Tooltip flutuante com detalhes da troca -->
                                    <div
                                        role="tooltip"
                                        class="pointer-events-none absolute bottom-full left-0 sm:left-1/2 sm:-translate-x-1/2 mb-2 hidden group-hover:flex group-focus-within:flex flex-col items-start sm:items-center z-50 transition-all duration-150 drop-shadow-xl"
                                    >
                                        <div class="w-64 max-w-xs rounded-xl bg-slate-900/95 backdrop-blur p-3 text-xs text-white shadow-2xl ring-1 ring-white/10 text-left">
                                            <div class="flex items-center justify-between gap-2 border-b border-slate-700/80 pb-1.5 mb-2 font-semibold">
                                                <span class="inline-flex items-center gap-1.5 text-amber-300">
                                                    <span aria-hidden="true" class="text-sm">⇄</span>
                                                    Troca realizada
                                                </span>
                                                @if ($swapInfo['is_mutual'])
                                                    <span class="rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-medium text-amber-300">Mútua</span>
                                                @endif
                                            </div>
                                            <p class="font-medium text-slate-100 leading-snug">
                                                {{ $swapInfo['headline'] }}
                                            </p>
                                            <div class="mt-2 space-y-1 rounded-md bg-slate-800/80 p-2 text-[11px]">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-slate-400">Solicitante:</span>
                                                    <span class="font-medium text-slate-100 truncate text-right">{{ $swapInfo['requester_name'] }}</span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-slate-400">Substituto:</span>
                                                    <span class="font-medium text-slate-100 truncate text-right">{{ $swapInfo['substitute_name'] }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="size-2 -mt-1 rotate-45 bg-slate-900/95 ring-1 ring-white/10 border-r border-b border-slate-700 sm:self-center ml-4 sm:ml-0"></div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <p class="mt-2 text-sm font-semibold text-primary">{{ $shift->time_label }}</p>
                        <p class="mt-1 text-sm text-muted">{{ $shift->sector->name }} · {{ $shift->category->name }}</p>
                    </div>
                    @if ($canManageShifts)
                        @can('reallocate', $shift)
                            <button type="button" wire:click="openReallocation({{ $shift->id }})" wire:loading.attr="disabled" wire:target="openReallocation" class="button-primary self-start" aria-label="Preencher plantão #{{ $shift->id }}">Preencher plantão</button>
                        @endcan
                        @if (in_array($shift->id, $pendingSwapShiftIds, true))
                            <a href="{{ \App\Support\OperationalNavigation::url('swaps', ['request' => $pendingSwapsByShift[$shift->id]], 'calendar', $this->navigationContext()) }}" wire:navigate class="table-action self-start">Analisar troca</a>
                        @elseif (! $shift->user_id)
                            <a href="{{ \App\Support\OperationalNavigation::url('shifts', ['focus' => $shift->id], 'calendar', $this->navigationContext()) }}" wire:navigate class="table-action self-start">Alocar profissional</a>
                        @else
                            <a href="{{ \App\Support\OperationalNavigation::url('shifts', ['focus' => $shift->id], 'calendar', $this->navigationContext()) }}" wire:navigate class="table-action self-start">Abrir escala</a>
                        @endif
                    @endif
                </article>
            @empty
                <div class="empty-state xl:col-span-2">
                    <p class="font-semibold text-ink">{{ $hasActiveFilters ? 'Nenhum plantão encontrado para os filtros selecionados.' : 'Nenhum plantão cadastrado neste dia.' }}</p>
                    <p class="mt-2 text-sm">{{ $hasActiveFilters ? ($isProfessional ? 'Ajuste o filtro de categoria ou selecione outra data.' : 'Ajuste os filtros de setor e categoria ou selecione outra data.') : ($canManageShifts ? 'Selecione outra data ou cadastre um plantão na gestão de escalas.' : 'Selecione outra data para consultar a escala.') }}</p>
                    @if ($hasActiveFilters)
                        <button type="button" wire:click="clearFilters" class="button-secondary mt-5">Limpar filtros</button>
                    @elseif ($canManageShifts)
                        <a href="{{ route('manager.shifts', ['month' => $month]) }}" wire:navigate class="button-secondary mt-5">Gerenciar plantões</a>
                    @endif
                </div>
            @endforelse
        </div>
    </section>
    @include('livewire.manager.partials.shift-reallocation')
</div>
