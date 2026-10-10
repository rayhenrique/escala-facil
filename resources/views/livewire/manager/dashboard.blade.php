<div class="manager-dashboard">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Visão da gerência</p>
            <h1 class="page-title">Olá, {{ auth()->user()->name }}.</h1>
            <p class="page-description">Acompanhe a cobertura dos setores e as tarefas que precisam da sua atenção.</p>
        </div>
        <p class="font-mono text-xs text-muted">{{ $now->translatedFormat('l, d \d\e F') }}</p>
    </div>

    <section class="coverage-board mt-8" aria-labelledby="coverage-summary-title">
        <div class="coverage-board-heading">
            <p class="eyebrow text-white/75">Pulso da escala</p>
            <h2 id="coverage-summary-title">Cobertura em tempo real</h2>
            <p>Visão geral. Selecione um indicador para abrir os registros correspondentes.</p>
        </div>
        <dl class="coverage-metrics">
            <div>
                <dt>Agendados no mês</dt>
                <dd><a href="{{ route('manager.shifts', ['month' => $now->format('Y-m'), 'filter' => 'scheduled']) }}" wire:navigate class="coverage-metric-link" aria-label="Ver {{ $scheduledThisMonth }} plantões agendados em {{ $now->format('m/Y') }}">{{ $scheduledThisMonth }}<span>Ver plantões de {{ $now->format('m/Y') }} →</span></a></dd>
            </div>
            <div class="coverage-metric-danger">
                <dt>Descobertos ativos ou futuros</dt>
                <dd><a href="{{ route('manager.shifts', ['filter' => 'uncovered']) }}" wire:navigate class="coverage-metric-link" aria-label="Ver {{ $uncoveredCount }} plantões descobertos ativos ou futuros">{{ $uncoveredCount }}<span>Ver plantões para realocar →</span></a></dd>
            </div>
            <div class="coverage-metric-pending">
                <dt>Trocas pendentes</dt>
                <dd><a href="{{ route('manager.swaps') }}" wire:navigate class="coverage-metric-link" aria-label="Ver {{ $pendingSwapCount }} trocas aguardando decisão">{{ $pendingSwapCount }}<span>Ver pedidos aguardando decisão →</span></a></dd>
            </div>
        </dl>
    </section>

    <section class="mt-8" aria-labelledby="attention-title">
        <div class="section-heading flex-wrap gap-4">
            <div>
                <p class="eyebrow">Foco operacional</p>
                <h2 id="attention-title">Fila de pendências</h2>
            </div>
            <div class="flex gap-2" role="group" aria-label="Período da fila">
                @foreach (['1' => 'Hoje', '3' => '3 dias', '7' => '7 dias'] as $value => $label)
                    <button type="button" wire:click="$set('period', '{{ $value }}')" class="{{ $period === (string) $value ? 'button-primary' : 'button-secondary' }}" aria-pressed="{{ $period === (string) $value ? 'true' : 'false' }}" aria-controls="attention-list" wire:loading.attr="disabled" wire:target="period">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <p class="mt-3 text-sm text-muted" id="attention-period">{{ $period === '1' ? 'Hoje' : $period.' dias de calendário, incluindo hoje' }}: {{ $now->format('d/m/Y') }} a {{ $calendarEnd->format('d/m/Y') }}. Trocas com plantão já iniciado continuam na fila.</p>
        <p class="mt-2 text-sm text-muted">Primeiro falta de cobertura, depois trocas. Em cada grupo: início do plantão e antiguidade do pedido.</p>
        <p class="mt-3 border-l-2 border-primary pl-3 text-sm"><strong>{{ $uncovered72Count }} {{ $uncovered72Count === 1 ? 'descoberto inicia' : 'descobertos iniciam' }} nas próximas 72 horas corridas</strong><span class="block text-xs text-muted">De {{ $now->format('d/m H:i') }} até {{ $rollingEnd->format('d/m H:i') }} (término do intervalo não incluído).</span></p>

        <div class="mt-4 text-sm text-muted" wire:loading wire:target="period, gotoPage, nextPage, previousPage" role="status">Atualizando pendências…</div>
        <div id="attention-list" aria-describedby="attention-period" class="mt-5">
            <p class="mb-3 text-sm font-semibold" role="status">{{ $queue->total() }} {{ $queue->total() === 1 ? 'pendência no período' : 'pendências no período' }}{{ $queue->total() > 8 ? ' · '.$queue->firstItem().'–'.$queue->lastItem().' nesta página' : '' }}</p>
            <ol class="space-y-3">
                @forelse ($queue as $item)
                    @php
                        $isUncovered = $item->kind === 'uncovered';
                        $record = $item->record;
                        $shift = $isUncovered ? $record : ($record->substituteShift && $record->substituteShift->period()->start->lt($record->shift->period()->start) ? $record->substituteShift : $record->shift);
                        $started = $shift->period()->start->lte($now);
                    @endphp
                    <li class="attention-row" wire:key="attention-{{ $item->kind }}-{{ $item->id }}" data-attention="{{ $item->kind }}-{{ $item->id }}">
                        <div>
                            <x-status-badge :status="$isUncovered ? 'uncovered' : 'pending_manager'" :label="$isUncovered ? 'Prioridade de cobertura' : 'Aguardando decisão'" />
                            <p class="mt-2 text-xs font-semibold {{ $started ? 'text-danger' : 'text-muted' }}">{{ $started ? 'Plantão já iniciado' : 'Plantão a iniciar' }}</p>
                        </div>
                        <div class="min-w-0">
                            <p class="meta-label">{{ $isUncovered ? 'Falta de cobertura · Plantão #'.$shift->id : ($record->substituteShift ? 'Troca mútua' : 'Troca de cobertura').' · Pedido #'.$record->id }}</p>
                            <h3 class="mt-1 text-base font-semibold">{{ $shift->date->format('d/m/Y') }} · {{ $shift->time_label }}</h3>
                            <p class="mt-1 text-sm text-muted">{{ $shift->sector->name }} · {{ $shift->category->name }}</p>
                            @if ($isUncovered && $shift->latestAbsence)
                                <p class="mt-2 text-sm">Ausência informada por <strong>{{ $shift->latestAbsence->user->name }}</strong>. {{ \Illuminate\Support\Str::limit($shift->latestAbsence->reason, 90) }}</p>
                            @elseif (! $isUncovered)
                                <p class="mt-2 text-sm"><strong>{{ $record->requester->name }}</strong> <span aria-hidden="true">→</span><span class="sr-only">solicita troca com</span> <strong>{{ $record->substitute->name }}</strong></p>
                                @if ($record->substituteShift)
                                    @php($other = $shift->is($record->shift) ? $record->substituteShift : $record->shift)
                                    <p class="mt-1 text-xs text-muted">Outro plantão: {{ $other->date->format('d/m/Y') }} · {{ $other->time_label }} · {{ $other->sector->name }}</p>
                                @endif
                            @endif
                            <p class="mt-2 text-xs text-muted">{{ $isUncovered ? ($shift->latestAbsence ? 'Ausência informada em' : 'Plantão registrado em') : 'Solicitada em' }} {{ ($isUncovered ? ($shift->latestAbsence?->created_at ?? $shift->created_at) : $record->created_at)->format('d/m/Y H:i') }}</p>
                        </div>
                        <a href="{{ $isUncovered ? \App\Support\OperationalNavigation::url('shifts', ['focus' => $shift->id], 'dashboard', $this->navigationContext()) : \App\Support\OperationalNavigation::url('swaps', ['request' => $record->id], 'dashboard', $this->navigationContext()) }}" wire:navigate class="button-secondary w-full sm:w-auto" aria-label="{{ $isUncovered ? 'Realocar profissional do plantão #'.$shift->id : 'Analisar solicitação #'.$record->id }}">{{ $isUncovered ? 'Realocar profissional' : 'Analisar solicitação' }}</a>
                    </li>
                @empty
                    <li class="empty-state compact-empty">
                        <p class="font-semibold text-ink">{{ $uncoveredCount === 0 && $pendingSwapCount === 0 ? 'Nenhum alerta operacional neste momento.' : 'Nenhuma pendência neste período.' }}</p>
                        <p class="mt-2 text-sm">{{ $uncoveredCount === 0 && $pendingSwapCount === 0 ? 'Não há plantões descobertos ativos ou futuros nem trocas aguardando a gerência.' : 'Escolha outro período ou abra os indicadores acima para consultar todas as pendências.' }}</p>
                    </li>
                @endforelse
            </ol>
            @if ($queue->hasPages())<div class="mt-5"><x-operational-pagination :paginator="$queue" /></div>@endif
        </div>
    </section>
    <nav class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-3 border-t border-line pt-5" aria-label="Atalhos da escala">
        <a href="{{ route('manager.calendar') }}" wire:navigate class="text-link inline-flex min-h-10 items-center">Abrir calendário</a>
        <a href="{{ route('manager.shifts') }}" wire:navigate class="text-link inline-flex min-h-10 items-center">Gerenciar escala</a>
    </nav>
</div>
