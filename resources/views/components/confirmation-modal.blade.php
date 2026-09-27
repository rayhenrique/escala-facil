@props([
    'show' => false,
    'id',
    'title',
    'eyebrow' => 'Confirmação necessária',
    'closeAction',
    'tone' => 'danger',
])

@if ($show)
    <div
        class="modal-overlay"
        x-data
        x-init="$nextTick(() => $el.querySelector('[data-confirm-cancel]')?.focus())"
        @keydown.escape.window="$wire.{{ $closeAction }}()"
    >
        <div class="modal-backdrop" aria-hidden="true"></div>
        <section
            class="modal-dialog confirmation-dialog max-w-lg"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="{{ $id }}-title"
            aria-describedby="{{ $id }}-description"
        >
            <header class="modal-header">
                <div class="flex items-start gap-4">
                    <span class="confirmation-icon confirmation-icon-{{ $tone }}" aria-hidden="true">
                        <x-nav-icon :name="$tone === 'danger' ? 'alert-triangle' : 'help'" class="size-6" />
                    </span>
                    <div>
                        <p class="eyebrow">{{ $eyebrow }}</p>
                        <h2 id="{{ $id }}-title">{{ $title }}</h2>
                    </div>
                </div>
            </header>

            <div id="{{ $id }}-description" class="modal-body confirmation-copy">
                {{ $slot }}
            </div>

            <footer class="modal-footer">
                {{ $actions }}
            </footer>
        </section>
    </div>
@endif
