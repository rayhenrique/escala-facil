<div>
    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Visão mensal</p>
            <h1 class="page-title">Calendário de plantões</h1>
            <p class="page-description">Selecione um dia para consultar todos os plantões e profissionais escalados.</p>
        </div>

        <div class="grid w-full grid-cols-[3rem_minmax(0,1fr)_3rem] items-end gap-2 sm:w-auto">
            <button type="button" wire:click="previousMonth" class="button-secondary size-12 p-0" aria-label="Mês anterior">
                <span aria-hidden="true">‹</span>
            </button>
            <div class="min-w-0 sm:w-48">
                <label for="calendar-month" class="field-label">Mês exibido</label>
                <input wire:model.live="month" id="calendar-month" type="month" class="field-input">
            </div>
            <button type="button" wire:click="nextMonth" class="button-secondary size-12 p-0" aria-label="Próximo mês">
                <span aria-hidden="true">›</span>
            </button>
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
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="calendar-sector" class="field-label">Setor</label>
                <select wire:model.live="sectorId" id="calendar-sector" class="field-input">
                    <option value="">Todos os setores</option>
                    @foreach ($this->sectors as $sector)
                        <option value="{{ $sector->id }}">{{ $sector->name }}</option>
                    @endforeach
                </select>
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

        <div class="calendar-grid mt-6" role="grid" aria-label="Calendário de {{ $monthLabel }}">
            @foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $weekday)
                <div class="calendar-weekday" role="columnheader">{{ $weekday }}</div>
            @endforeach

            @foreach ($calendarDays as $day)
                <button
                    type="button"
                    wire:click="selectDate('{{ $day['date'] }}')"
                    class="calendar-day {{ ! $day['in_month'] ? 'calendar-day-outside' : '' }} {{ $selectedDate === $day['date'] ? 'calendar-day-selected' : '' }}"
                    @disabled(! $day['in_month'])
                    role="gridcell"
                    @if ($selectedDate === $day['date']) aria-selected="true" @endif
                    aria-label="{{ \Carbon\CarbonImmutable::parse($day['date'])->format('d/m/Y') }}, {{ $day['shifts_count'] }} plantão(ões)"
                >
                    <span class="calendar-day-number {{ $day['is_today'] ? 'calendar-day-today' : '' }}">{{ $day['day'] }}</span>

                    @if ($day['in_month'] && $day['shifts_count'] > 0)
                        <span class="calendar-day-count">{{ $day['shifts_count'] }}</span>
                        <span class="sr-only">plantão(ões)</span>
                    @endif

                    @if ($day['in_month'] && $day['uncovered_count'] > 0)
                        <span class="calendar-day-alert" title="{{ $day['uncovered_count'] }} plantão(ões) sem profissional" aria-label="{{ $day['uncovered_count'] }} plantão(ões) sem profissional"></span>
                    @elseif ($day['in_month'] && $day['shifts_count'] > 0)
                        <span class="calendar-day-covered" aria-hidden="true"></span>
                    @endif
                </button>
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
                <article class="daily-shift-card" wire:key="calendar-shift-{{ $shift->id }}">
                    <div class="professional-avatar {{ $shift->user ? '' : 'professional-avatar-empty' }}" aria-hidden="true">
                        {{ $shift->user ? mb_strtoupper(mb_substr($shift->user->name, 0, 1)) : '!' }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold">{{ $shift->user?->name ?? 'Plantão sem profissional' }}</h3>
                            <x-status-badge :status="$shift->status" />
                        </div>
                        <p class="mt-2 text-sm font-semibold text-primary">{{ substr($shift->start_time, 0, 5) }}–{{ substr($shift->end_time, 0, 5) }}</p>
                        <p class="mt-1 text-sm text-muted">{{ $shift->sector->name }} · {{ $shift->category->name }}</p>
                    </div>
                    @if ($canManageShifts)
                        <a href="{{ route('manager.shifts', ['month' => $month]) }}" wire:navigate class="table-action self-start">Abrir escala</a>
                    @endif
                </article>
            @empty
                <div class="empty-state xl:col-span-2">
                    <p class="font-semibold text-ink">{{ $hasActiveFilters ? 'Nenhum plantão encontrado para os filtros selecionados.' : 'Nenhum plantão cadastrado neste dia.' }}</p>
                    <p class="mt-2 text-sm">{{ $hasActiveFilters ? 'Ajuste os filtros de setor e categoria ou selecione outra data.' : ($canManageShifts ? 'Selecione outra data ou cadastre um plantão na gestão de escalas.' : 'Selecione outra data para consultar a escala.') }}</p>
                    @if ($hasActiveFilters)
                        <button type="button" wire:click="clearFilters" class="button-secondary mt-5">Limpar filtros</button>
                    @elseif ($canManageShifts)
                        <a href="{{ route('manager.shifts', ['month' => $month]) }}" wire:navigate class="button-secondary mt-5">Gerenciar plantões</a>
                    @endif
                </div>
            @endforelse
        </div>
    </section>
</div>
