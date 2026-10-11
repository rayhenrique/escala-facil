@props(['blocks' => [], 'warnings' => []])
<section aria-label="Alertas da alteração" class="space-y-4">
    @if ($blocks)
        <div class="border-l-2 border-danger pl-3">
            <h3 class="font-semibold text-danger">Bloqueios obrigatórios</h3>
            <ul class="mt-2 space-y-2 text-sm text-danger">@foreach ($blocks as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif
    @if ($warnings)
        <div class="border-l-2 border-primary pl-3">
            <h3 class="font-semibold text-ink">Avisos para revisão</h3>
            <ul class="mt-2 space-y-2 text-sm text-ink">@foreach ($warnings as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif
</section>
