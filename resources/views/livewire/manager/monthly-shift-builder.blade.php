<div data-shift-assignment-flow>
    <a href="{{ route('manager.shifts', ['month' => $month]) }}" wire:navigate class="text-link mb-3 inline-flex min-h-10 items-center">Voltar à Gestão de escalas</a>
    <p class="eyebrow">Gerência</p>
    <h1 class="page-title" tabindex="-1" data-decision-return>Montar escala mensal</h1>
    <p class="page-description">Selecione um profissional e organize seus plantões por dia.</p>

    <x-flash-notifications />

    <section class="panel mt-6 grid min-w-0 gap-6 md:grid-cols-2" aria-label="Profissional da escala">
        <div class="min-w-0">
            <label for="builder-professional" class="field-label">Selecione o profissional</label>
            <x-searchable-select id="builder-professional" label="Profissional" search-model="professionalSearch" model="professionalId" :options="$this->professionals" live-selection
                :option-label="fn ($option) => $option->name.' · '.($option->sector?->name ?? 'Sem setor').' · '.($option->category?->name ?? 'Sem categoria').' · #'.$option->id"
                :invalid="$errors->has('professionalId')" described-by="builder-professional-error" />
            @error('professionalId')<p id="builder-professional-error" class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>
        @if ($professional)
            <div class="min-w-0 self-center">
                <p class="eyebrow">Profissional selecionado</p>
                <p class="mt-1 break-words font-semibold">{{ $professional->name }}</p>
                <p class="break-words text-sm text-muted">{{ $professional->sector?->name ?? 'Sem setor' }} · {{ $professional->category?->name ?? 'Sem categoria' }}</p>
                @if (! $professional->sector || ! $professional->category)
                    <p class="mt-2 text-sm text-muted" role="status">Vincule setor e categoria em <a href="{{ route('manager.users') }}" wire:navigate class="text-link">Usuários</a> para cadastrar ou editar plantões.</p>
                @endif
            </div>
        @elseif ($professionalId !== '')
            <p class="self-center text-sm text-muted" role="status">Selecione um profissional disponível na lista.</p>
        @endif
    </section>

    <x-loading-state wire:loading.delay wire:target="professionalId,month,previousMonth,nextMonth,currentMonth,edit,openCreateModal" class="mt-4" label="Carregando escala…" />

    @if ($professional)
        @php
            $ready = $professional->sector && $professional->category;
            $weekdays = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
        @endphp
        <section class="panel mt-6 min-w-0" aria-labelledby="builder-calendar-title">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="eyebrow">Agenda mensal</p>
                    <h2 id="builder-calendar-title" class="mt-2 text-xl font-semibold capitalize">{{ $monthStart->translatedFormat('F Y') }}</h2>
                    <p class="mt-1 text-sm text-muted" role="status">{{ $shiftCount }} {{ $shiftCount === 1 ? 'plantão no mês' : 'plantões no mês' }}</p>
                </div>
                <div class="grid w-full gap-3 sm:w-auto sm:grid-cols-[1fr_auto] sm:items-end">
                    <div class="min-w-0">
                        <label for="builder-month" class="field-label">Mês e ano</label>
                        <input id="builder-month" type="month" wire:model.live="month" class="field-input min-w-0">
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="previousMonth" class="button-secondary flex-1" aria-label="Mês anterior">Anterior</button>
                        <button type="button" wire:click="currentMonth" class="button-secondary flex-1">Mês atual</button>
                        <button type="button" wire:click="nextMonth" class="button-secondary flex-1" aria-label="Próximo mês">Próximo</button>
                    </div>
                </div>
            </div>

            <div wire:key="builder-calendar-{{ $professional->id }}-{{ $month }}" class="mt-6 grid grid-cols-1 gap-2 md:grid-cols-7">
                @foreach ($weekdays as $weekday)
                    <div class="hidden text-center text-xs font-semibold text-muted md:block" aria-hidden="true">{{ $weekday }}</div>
                @endforeach
                @for ($blank = 1; $blank < $monthStart->dayOfWeekIso; $blank++)
                    <div class="hidden md:block" aria-hidden="true"></div>
                @endfor
                @for ($day = 1; $day <= $monthStart->daysInMonth; $day++)
                    @php
                        $date = $monthStart->setDay($day);
                        $shifts = $shiftsByDay->get($day, collect());
                    @endphp
                    <section wire:key="builder-day-{{ $date->toDateString() }}" class="min-w-0 rounded-lg border border-line p-2 {{ $shifts->isNotEmpty() ? 'bg-primary-soft' : 'bg-canvas' }}" aria-labelledby="builder-day-{{ $day }}">
                        <h3 id="builder-day-{{ $day }}" class="font-semibold" @if ($date->isToday()) aria-current="date" @endif>
                            <span class="md:hidden">{{ $date->translatedFormat('D') }} · </span><time datetime="{{ $date->toDateString() }}">{{ $date->format('d') }}</time>
                        </h3>
                        <p class="mb-1 text-xs text-muted">{{ $shifts->isEmpty() ? 'Livre' : $shifts->count().' '.($shifts->count() === 1 ? 'plantão' : 'plantões') }}</p>
                        @foreach ($shifts as $shift)
                            @php $period = $shift->period(); @endphp
                            <div wire:key="builder-shift-{{ $shift->id }}" class="mt-1">
                                @if ($ready)
                                    <button type="button" wire:click="edit({{ $shift->id }})" wire:loading.attr="disabled" wire:target="edit,openCreateModal" class="button-secondary min-h-11 w-full justify-start px-2 text-left" aria-label="Editar plantão {{ $period->datedLabel() }}">
                                        <span class="min-w-0">
                                            <span class="block">{{ $period->start->format('H:i') }}–{{ $period->endLabel() }}</span>
                                            <span class="block text-xs font-normal text-muted">{{ $shift->status->label() }}</span>
                                        </span>
                                    </button>
                                @else
                                    <p class="text-sm">{{ $period->start->format('H:i') }}–{{ $period->endLabel() }} · {{ $shift->status->label() }}</p>
                                @endif
                            </div>
                        @endforeach
                        @if ($ready && ! $date->isBefore(today()))
                            <button type="button" wire:click="openCreateModal('{{ $date->toDateString() }}')" wire:loading.attr="disabled" wire:target="edit,openCreateModal" class="table-action mt-1 flex min-h-11 w-full items-center justify-start text-left" aria-label="Adicionar plantão em {{ $date->format('d/m/Y') }}">+ Plantão</button>
                        @endif
                    </section>
                @endfor
            </div>
        </section>
    @else
        <x-empty-state class="mt-6" title="Selecione um profissional para abrir o calendário" description="Pesquise pelo nome e confira o setor e a categoria antes de selecionar." />
    @endif

    @if ($showFormModal)
        <x-action-modal :show="$showFormModal" id="monthly-shift-form" :title="$editingShiftId ? 'Editar plantão' : 'Adicionar plantão'" close-action="closeFormModal" submit-action="save" max-width="2xl" track-changes fit-viewport @shift-alerts-ready.window="$nextTick(() => $refs.alerts?.focus())">
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="col-span-full min-w-0">
                    <p class="break-words font-semibold">{{ $professional?->name }}</p>
                    <p class="break-words text-sm text-muted">{{ $professional?->sector?->name }} · {{ $professional?->category?->name }}</p>
                </div>
                @foreach (['shift', 'user_id', 'sector_id', 'category_id', 'professionalId'] as $field)
                    @error($field)<p class="field-error col-span-full" tabindex="-1" aria-invalid="true" role="alert">{{ $message }}</p>@enderror
                @endforeach
                @if ($shiftPreview)
                    @include('livewire.manager.partials.shift-preview')
                @else
                    @include('livewire.manager.partials.shift-timing-fields')
                @endif
            </div>
            <x-slot:actions>
                <button type="button" @click="requestClose()" :disabled="busy" class="button-secondary w-full sm:w-auto">Cancelar</button>
                <button type="submit" :disabled="busy" class="button-primary w-full sm:w-auto" wire:target="save">
                    <span wire:loading.remove wire:target="save">{{ $shiftPreview ? 'Confirmar com os avisos' : ($editingShiftId ? 'Salvar alterações' : 'Criar plantão') }}</span>
                    <span wire:loading wire:target="save">Salvando…</span>
                </button>
            </x-slot:actions>
        </x-action-modal>
    @endif
</div>
