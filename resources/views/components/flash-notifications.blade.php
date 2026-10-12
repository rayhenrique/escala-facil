@php
    $successMessage = session('status');
    $errorMessage = session('error');
@endphp
@if ($successMessage || $errorMessage)
    @foreach (['status' => ['success', $successMessage], 'error' => ['error', $errorMessage]] as $key => [$tone, $message])
        @if ($message)
            @php $identity = session('ui_feedback_'.$key.'_id') ?? 'legacy-'.$tone.'-'.md5($message); @endphp
            <div hidden x-data x-init="$store.operationalFeedback.notify({ id: @js($identity), tone: @js($tone), message: @js($message) })" wire:key="feedback-{{ $identity }}">{{ $message }}</div>
        @endif
    @endforeach
@endif
