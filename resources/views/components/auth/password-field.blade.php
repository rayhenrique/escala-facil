@props(['id', 'label', 'errorKey' => null, 'autocomplete' => 'current-password', 'hint' => null])

@php
    $errorKey = $errorKey ?? $id;
    $describedBy = trim(($hint ? $id.'-hint' : '').' '.($errors->has($errorKey) ? $id.'-error' : ''));
@endphp

<div x-data="{ visible: false }">
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
        <label for="{{ $id }}" class="field-label">{{ $label }}</label>
        {{ $slot }}
    </div>
    <div class="relative">
        <input {{ $attributes->class(['field-input pr-24']) }} id="{{ $id }}" name="{{ $id }}" type="password" x-bind:type="visible ? 'text' : 'password'" autocomplete="{{ $autocomplete }}" @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif @if ($errors->has($errorKey)) aria-invalid="true" @endif>
        <button type="button" x-cloak class="password-toggle w-22 justify-center" @click="visible = ! visible" :aria-label="visible ? 'Ocultar {{ mb_strtolower($label) }}' : 'Mostrar {{ mb_strtolower($label) }}'" :aria-pressed="visible.toString()" aria-pressed="false" aria-controls="{{ $id }}" aria-label="Mostrar {{ mb_strtolower($label) }}" x-text="visible ? 'Ocultar' : 'Mostrar'">Mostrar</button>
    </div>
    @if ($hint)<p id="{{ $id }}-hint" class="mt-2 text-sm leading-6 text-muted">{{ $hint }}</p>@endif
    @error($errorKey)<p id="{{ $id }}-error" class="field-error">{{ $message }}</p>@enderror
</div>
