@props(['status'])

@if ($status)
    <div {{ $attributes->class(['auth-session-status']) }} role="status">
        {{ $status }}
    </div>
@endif
