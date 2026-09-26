@props(['shift', 'showProfessional' => false])

<article {{ $attributes->class(['shift-card', 'shift-card-'.$shift->status->value]) }}>
    <div class="shift-card-date" aria-hidden="true">
        <span>{{ $shift->date->translatedFormat('D') }}</span>
        <strong>{{ $shift->date->format('d') }}</strong>
        <small>{{ $shift->date->translatedFormat('M') }}</small>
    </div>
    <div class="min-w-0 flex-1">
        <x-status-badge :status="$shift->status" />
        <h3 class="mt-3 text-lg font-semibold"><span class="sr-only">{{ $shift->date->format('d/m/Y') }}, </span>{{ substr($shift->start_time, 0, 5) }}–{{ substr($shift->end_time, 0, 5) }}</h3>
        <p class="mt-1 text-sm text-muted">{{ $shift->sector->name }} · {{ $shift->category->name }}</p>
        @if ($showProfessional)
            <p class="mt-3 text-sm"><span class="text-muted">Profissional:</span> <strong>{{ $shift->user?->name ?? 'Não alocado' }}</strong></p>
        @endif
        @if ($slot->isNotEmpty())
            <div class="mt-5">{{ $slot }}</div>
        @endif
    </div>
</article>
