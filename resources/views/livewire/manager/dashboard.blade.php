<div>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Visão da gerência</p>
            <h1 class="page-title">Olá, {{ auth()->user()->name }}.</h1>
            <p class="page-description">Acompanhe a cobertura dos setores e resolva as pendências que exigem decisão.</p>
        </div>
        <p class="font-mono text-xs uppercase tracking-wider text-muted">{{ now()->translatedFormat('l, d \d\e F') }}</p>
    </div>

    <section class="coverage-board mt-8" aria-labelledby="coverage-summary-title">
        <div class="coverage-board-heading">
            <p class="eyebrow text-white/75">Pulso da escala</p>
            <h2 id="coverage-summary-title">Cobertura em tempo real</h2>
            <p>Os números abaixo refletem o estado atual dos plantões e pedidos.</p>
        </div>
        <dl class="coverage-metrics">
            <div>
                <dt>Agendados no mês</dt>
                <dd>{{ $scheduledThisMonth }}</dd>
                <span>Plantões programados</span>
            </div>
            <div class="coverage-metric-danger">
                <dt>Descobertos futuros</dt>
                <dd>{{ $uncoveredCount }}</dd>
                <span>{{ $uncoveredCount === 1 ? 'Exige realocação' : 'Exigem realocação' }}</span>
            </div>
            <div class="coverage-metric-pending">
                <dt>Trocas pendentes</dt>
                <dd>{{ $pendingSwapCount }}</dd>
                <span>{{ $pendingSwapCount === 1 ? 'Aguarda decisão' : 'Aguardam decisão' }}</span>
            </div>
        </dl>
    </section>

    @if ($uncoveredCount === 0 && $pendingSwapCount === 0)
        <div class="status-success mt-6" role="status">
            Nenhum alerta operacional neste momento. A escala está sem plantões descobertos ou trocas aguardando a gerência.
        </div>
    @endif

    <section class="mt-10" aria-labelledby="action-focus-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Foco operacional</p>
                <h2 id="action-focus-title">Próximas 72 horas</h2>
            </div>
            <x-status-badge status="pending_manager" :label="$uncovered72Count.' descoberto(s) em 72h'" />
        </div>

        @php($urgentUncovered = $nextUncovered && $nextUncovered->date->format('Y-m-d') <= $attentionWindowEnd ? $nextUncovered : null)

        @if ($urgentUncovered || $oldestPendingSwap)
            <div class="mt-4 grid gap-4 xl:grid-cols-2">
                @if ($urgentUncovered)
                    @php($urgentAbsence = $urgentUncovered->absences->first())
                    <x-shift-card :shift="$urgentUncovered" wire:key="dashboard-next-uncovered-{{ $urgentUncovered->id }}">
                        <p class="text-sm leading-6 text-muted">
                            Próximo plantão descoberto em
                            <strong class="text-ink">{{ $urgentUncovered->date->format('d/m/Y') }}</strong>.
                            @if ($urgentAbsence)
                                Ausência informada por <strong class="text-ink">{{ $urgentAbsence->user->name }}</strong>.
                            @endif
                        </p>
                        <a href="{{ route('manager.shifts', ['month' => $urgentUncovered->date->format('Y-m')]) }}" wire:navigate class="text-link mt-3 inline-flex">Realocar profissional</a>
                    </x-shift-card>
                @endif

                @if ($oldestPendingSwap)
                    <article class="alert-row" wire:key="dashboard-oldest-pending-{{ $oldestPendingSwap->id }}">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <x-status-badge :status="$oldestPendingSwap->status" label="Pendência mais antiga" />
                            <span class="font-mono text-xs text-muted">Pedido #{{ $oldestPendingSwap->id }} · {{ $oldestPendingSwap->created_at->diffForHumans() }}</span>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">{{ $oldestPendingSwap->shift->date->format('d/m/Y') }} · {{ substr($oldestPendingSwap->shift->start_time, 0, 5) }}–{{ substr($oldestPendingSwap->shift->end_time, 0, 5) }}</h3>
                        <p class="mt-1 text-sm text-muted">{{ $oldestPendingSwap->shift->sector->name }} · {{ $oldestPendingSwap->shift->category->name }}</p>
                        <div class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                            <strong>{{ $oldestPendingSwap->requester->name }}</strong>
                            <span class="text-muted" aria-hidden="true">→</span>
                            <span class="sr-only">será substituído por</span>
                            <strong>{{ $oldestPendingSwap->substitute->name }}</strong>
                        </div>
                        <a href="{{ route('manager.swaps') }}" wire:navigate class="text-link mt-4 inline-flex">Analisar solicitação</a>
                    </article>
                @endif
            </div>
        @else
            <div class="empty-state compact-empty mt-4">
                <p class="font-semibold text-ink">Nenhuma pendência nas próximas 72 horas.</p>
                <p class="mt-2 text-sm">Novos plantões descobertos e pedidos antigos aparecerão aqui primeiro.</p>
            </div>
        @endif
    </section>

    <div class="mt-10 grid gap-8 xl:grid-cols-2">
        <section aria-labelledby="uncovered-title">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Prioridade de cobertura</p>
                    <h2 id="uncovered-title">Plantões futuros descobertos</h2>
                </div>
                <x-status-badge status="uncovered" :label="$uncoveredCount.' aberto(s)'" />
            </div>

            <div class="mt-4 space-y-4">
                @forelse ($uncoveredShifts as $shift)
                    @php($absence = $shift->absences->first())
                    <x-shift-card :shift="$shift" wire:key="dashboard-uncovered-{{ $shift->id }}">
                        @if ($absence)
                            <p class="text-sm leading-6 text-muted">
                                Ausência informada por <strong class="text-ink">{{ $absence->user->name }}</strong>.
                                {{ \Illuminate\Support\Str::limit($absence->reason, 90) }}
                            </p>
                        @endif
                        <a href="{{ route('manager.shifts', ['month' => $shift->date->format('Y-m')]) }}" wire:navigate class="text-link mt-3 inline-flex">Realocar profissional</a>
                    </x-shift-card>
                @empty
                    <div class="empty-state compact-empty">
                        <p class="font-semibold text-ink">Todos os plantões futuros têm cobertura.</p>
                        <p class="mt-2 text-sm">Ausências informadas aparecerão aqui imediatamente.</p>
                    </div>
                @endforelse
            </div>

            @if ($uncoveredCount > $uncoveredShifts->count())
                <a href="{{ route('manager.shifts') }}" wire:navigate class="button-secondary mt-4 w-full">Ver todos os plantões</a>
            @endif
        </section>

        <section aria-labelledby="pending-swaps-title">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Decisão gerencial</p>
                    <h2 id="pending-swaps-title">Trocas aguardando aprovação</h2>
                </div>
                <x-status-badge status="pending_manager" :label="$pendingSwapCount.' pendente(s)'" />
            </div>

            <div class="mt-4 space-y-4">
                @forelse ($pendingSwaps as $swap)
                    <article class="alert-row" wire:key="dashboard-swap-{{ $swap->id }}">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <x-status-badge :status="$swap->status" label="Aguardando decisão" />
                            <span class="font-mono text-xs text-muted">Pedido #{{ $swap->id }}</span>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">{{ $swap->shift->date->format('d/m/Y') }} · {{ substr($swap->shift->start_time, 0, 5) }}–{{ substr($swap->shift->end_time, 0, 5) }}</h3>
                        <p class="mt-1 text-sm text-muted">{{ $swap->shift->sector->name }} · {{ $swap->shift->category->name }}</p>
                        <div class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                            <strong>{{ $swap->requester->name }}</strong>
                            <span class="text-muted" aria-hidden="true">→</span>
                            <span class="sr-only">será substituído por</span>
                            <strong>{{ $swap->substitute->name }}</strong>
                        </div>
                        <a href="{{ route('manager.swaps') }}" wire:navigate class="text-link mt-4 inline-flex">Analisar solicitação</a>
                    </article>
                @empty
                    <div class="empty-state compact-empty">
                        <p class="font-semibold text-ink">Nenhuma troca aguarda aprovação.</p>
                        <p class="mt-2 text-sm">Pedidos aceitos pelos substitutos aparecerão aqui.</p>
                    </div>
                @endforelse
            </div>

            @if ($pendingSwapCount > $pendingSwaps->count())
                <a href="{{ route('manager.swaps') }}" wire:navigate class="button-secondary mt-4 w-full">Ver todas as trocas</a>
            @endif
        </section>
    </div>

    <section class="mt-12 border-t border-line pt-8" aria-labelledby="management-modules-title">
        <p class="eyebrow">Atalhos operacionais</p>
        <h2 id="management-modules-title" class="mt-2 text-xl font-semibold">Gerenciar escala</h2>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ route('manager.shifts') }}" wire:navigate class="module-card block transition-colors hover:border-primary">
                <span class="module-index">01 / ESCALA</span><h3>Gestão de plantões</h3><p>Crie, edite, aloque e acompanhe os plantões.</p>
            </a>
            <a href="{{ route('manager.swaps') }}" wire:navigate class="module-card block transition-colors hover:border-primary">
                <span class="module-index">02 / TROCAS</span><h3>Aprovação de trocas</h3><p>Analise pedidos já aceitos pelos substitutos.</p>
            </a>
            <a href="{{ route('manager.users') }}" wire:navigate class="module-card block transition-colors hover:border-primary">
                <span class="module-index">03 / USUÁRIOS</span><h3>Gestão de usuários</h3><p>Cadastre gerentes e profissionais com seus vínculos.</p>
            </a>
            <a href="{{ route('manager.sectors') }}" wire:navigate class="module-card block transition-colors hover:border-primary">
                <span class="module-index">04 / SETORES</span><h3>Setores da unidade</h3><p>Organize os locais atendidos pela escala.</p>
            </a>
            <a href="{{ route('manager.categories') }}" wire:navigate class="module-card block transition-colors hover:border-primary">
                <span class="module-index">05 / CATEGORIAS</span><h3>Categorias profissionais</h3><p>Mantenha as especialidades exigidas nos plantões.</p>
            </a>
        </div>
    </section>
</div>
