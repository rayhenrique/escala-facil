@props(['steps', 'current' => 1, 'label' => 'Etapas'])
@php $current = max(1, min((int) $current, count($steps))); @endphp
<ol {{ $attributes->class(['step-progress']) }} aria-label="{{ $label }}" role="list">
    @foreach ($steps as $step)
        <li class="step-progress-item {{ $loop->iteration < $current ? 'step-complete' : '' }}" @if ($loop->iteration === $current) aria-current="step" @endif>
            <span class="step-number" aria-hidden="true">@if ($loop->iteration < $current)<x-nav-icon name="check-circle" class="size-5" />@else{{ $loop->iteration }}@endif</span>
            <span><span class="sr-only">Etapa {{ $loop->iteration }} de {{ count($steps) }}: </span>{{ $step }}@if ($loop->iteration < $current)<span class="sr-only"> · Concluída</span>@endif</span>
        </li>
    @endforeach
</ol>
