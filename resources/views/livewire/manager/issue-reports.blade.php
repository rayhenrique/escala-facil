<div>
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Suporte</p>
            <h1 class="page-title">Relatos de problema</h1>
            <p class="page-description">Acompanhe os relatos enviados pelos usuários, com protocolo, módulo e comportamento esperado.</p>
        </div>
    </div>

    <x-flash-notifications />

    <section class="mt-8 space-y-4" aria-label="Relatos recebidos">
        @forelse ($reports as $report)
            <article class="notification-card" wire:key="issue-report-{{ $report->id }}">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-sm font-semibold text-primary">{{ $report->protocol }}</span>
                            <span class="status-badge status-pending_professional">{{ $report->moduleLabel() }}</span>
                            <time class="font-mono text-xs text-muted" datetime="{{ $report->created_at->toIso8601String() }}">{{ $report->created_at->format('d/m/Y H:i') }}</time>
                        </div>
                        <p class="mt-2 text-sm text-muted">Relatado por: {{ $report->reporter?->name ?? 'usuário removido' }}</p>
                        <h2 class="mt-3 text-base font-semibold">O que aconteceu</h2>
                        <p class="mt-1 text-sm leading-6">{{ $report->description }}</p>
                        <h2 class="mt-3 text-base font-semibold">Comportamento esperado</h2>
                        <p class="mt-1 text-sm leading-6">{{ $report->expected_behavior }}</p>
                    </div>
                    <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                        @if ($report->attachment_path)
                            <a href="{{ route('manager.issue-reports.attachment', $report) }}" class="button-secondary">Baixar anexo</a>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="empty-state">
                <x-nav-icon name="flag" class="mx-auto size-8 text-primary" />
                <p class="mt-4 font-semibold text-ink">Nenhum relato por enquanto.</p>
                <p class="mt-2 text-sm">Os problemas enviados pelos usuários aparecerão aqui.</p>
            </div>
        @endforelse
    </section>

    <div class="mt-6">{{ $reports->links() }}</div>
</div>
