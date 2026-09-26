<div>
    @if ($show)
        <div class="modal-overlay z-[90]" aria-live="polite">
            <div class="modal-backdrop" aria-hidden="true"></div>
            <section class="modal-dialog max-w-2xl" role="dialog" aria-modal="true" aria-labelledby="release-title" aria-describedby="release-summary">
                <header class="release-modal-header">
                    <div class="release-icon" aria-hidden="true">
                        <x-nav-icon name="sparkles" class="size-6" />
                    </div>
                    <div class="min-w-0">
                        <p class="eyebrow text-primary-soft">Novidades da versão {{ $release['version'] }}</p>
                        <h2 id="release-title">{{ $release['title'] }}</h2>
                        <p id="release-summary">{{ $release['summary'] }}</p>
                    </div>
                </header>

                <div class="modal-body">
                    <p class="font-mono text-xs font-semibold uppercase tracking-wider text-muted">O que mudou</p>
                    <ul class="release-highlights mt-4">
                        @foreach ($release['highlights'] as $highlight)
                            <li><span aria-hidden="true">✓</span><span>{{ $highlight }}</span></li>
                        @endforeach
                    </ul>
                </div>

                <footer class="modal-footer">
                    <button type="button" wire:click="openHelp" class="button-secondary w-full sm:w-auto" wire:loading.attr="disabled">Ver manual e versões</button>
                    <button type="button" wire:click="acknowledge" class="button-primary w-full sm:w-auto" wire:loading.attr="disabled">Entendi, continuar</button>
                </footer>
            </section>
        </div>
    @endif
</div>
