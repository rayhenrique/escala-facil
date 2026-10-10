<footer class="border-t border-line bg-white py-8">
    <div class="landing-container">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="font-semibold text-ink">Escala Fácil</p><p class="mt-1 text-sm text-muted">Gestão de plantões hospitalares</p></div>
            <nav aria-label="Navegação do rodapé" class="flex flex-wrap gap-x-5 text-sm font-semibold text-primary">
                <a href="{{ route('login') }}" class="min-h-11 py-3 hover:underline">Entrar</a>
                <a href="#superpoderes" class="min-h-11 py-3 hover:underline">Recursos</a>
                <a href="#hero" class="min-h-11 py-3 hover:underline">Voltar ao topo <span aria-hidden="true">↑</span></a>
            </nav>
        </div>
        <div class="mt-5 flex flex-col gap-3 border-t border-line pt-5 text-xs leading-6 text-muted sm:flex-row sm:items-center sm:justify-between">
            <p>© {{ date('Y') }} Escala Fácil · Acesso institucional</p>
            <p class="flex flex-wrap items-center gap-x-4">Desenvolvido por <a href="https://kltecnologia.com" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center font-semibold text-primary underline underline-offset-4 hover:text-primary-strong">KL Tecnologia<span class="sr-only"> (abre em nova aba)</span></a><span class="font-mono">Versão {{ config('version.current') }}</span></p>
        </div>
    </div>
</footer>
