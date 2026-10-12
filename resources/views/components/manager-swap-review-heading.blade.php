@props(['summary'])
<div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="font-mono text-xs text-muted">{{ $summary['reference'] }}</p>
        <div><span class="text-xs text-muted">Situação atual: </span><x-status-badge :status="$summary['status']" :label="$summary['status_label']" /></div>
    </div>
    <dl class="grid gap-3 text-sm sm:grid-cols-2">
        <div><dt class="meta-label">Solicitante</dt><dd class="font-semibold text-ink">{{ $summary['requester'] }}</dd></div>
        <div><dt class="meta-label">Substituto</dt><dd class="font-semibold text-ink">{{ $summary['substitute'] }}</dd></div>
    </dl>
</div>
