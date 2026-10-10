<header x-data="{ mobileOpen: false }" @keydown.escape.window="if (mobileOpen) { mobileOpen = false; $refs.menuButton.focus() }" class="fixed inset-x-0 top-0 z-50 border-b border-line bg-white">
    <div class="landing-container flex h-18 items-center justify-between gap-3 sm:h-20">
        <a href="#hero" class="flex min-h-11 items-center gap-2.5 rounded-lg font-semibold text-ink hover:text-primary">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary-strong"><x-application-logo class="size-6 text-white" /></span>
            <span class="text-lg tracking-tight sm:text-xl">Escala Fácil</span>
        </a>
        <nav class="hidden items-center gap-1 text-sm font-medium text-muted lg:flex" aria-label="Navegação principal">
            <a href="#friccao-vs-fluidez" class="min-h-11 rounded-lg px-4 py-3 hover:bg-canvas hover:text-ink">Na rotina</a>
            <a href="#superpoderes" class="min-h-11 rounded-lg px-4 py-3 hover:bg-canvas hover:text-ink">Recursos</a>
            <a href="#simulacao" class="min-h-11 rounded-lg px-4 py-3 hover:bg-canvas hover:text-ink">Como funciona</a>
        </nav>
        <div class="flex items-center gap-2">
            <a href="{{ route('login') }}" class="button-primary min-h-11 px-3 py-2 sm:px-5">Entrar<span class="hidden lg:inline">&nbsp;na plataforma</span></a>
            <button type="button" x-ref="menuButton" x-cloak @click="mobileOpen = ! mobileOpen" class="flex size-11 items-center justify-center rounded-lg text-ink hover:bg-canvas lg:hidden" :aria-label="mobileOpen ? 'Fechar menu de navegação' : 'Abrir menu de navegação'" :aria-expanded="mobileOpen.toString()" aria-expanded="false" aria-controls="landing-mobile-menu" aria-label="Abrir menu de navegação">
                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
        </div>
    </div>
    <nav id="landing-mobile-menu" aria-label="Navegação móvel" x-show="mobileOpen" x-cloak @click.outside="mobileOpen = false" class="border-t border-line bg-white p-4 lg:hidden">
        <div class="landing-container flex flex-col gap-1">
            <a href="#hero" @click="mobileOpen = false" class="min-h-11 rounded-lg px-4 py-3 font-medium hover:bg-canvas">Início</a>
            <a href="#friccao-vs-fluidez" @click="mobileOpen = false" class="min-h-11 rounded-lg px-4 py-3 font-medium hover:bg-canvas">Na rotina</a>
            <a href="#superpoderes" @click="mobileOpen = false" class="min-h-11 rounded-lg px-4 py-3 font-medium hover:bg-canvas">Recursos</a>
            <a href="#simulacao" @click="mobileOpen = false" class="min-h-11 rounded-lg px-4 py-3 font-medium hover:bg-canvas">Como funciona</a>
        </div>
    </nav>
</header>
<noscript>
    <nav aria-label="Seções da página" class="landing-container flex flex-wrap gap-x-5 gap-y-1 pt-20 text-sm font-semibold text-primary">
        <a href="#friccao-vs-fluidez" class="min-h-11 py-3 underline">Na rotina</a>
        <a href="#superpoderes" class="min-h-11 py-3 underline">Recursos</a>
        <a href="#simulacao" class="min-h-11 py-3 underline">Como funciona</a>
    </nav>
    <style>#hero { padding-top: 2rem; }</style>
</noscript>
