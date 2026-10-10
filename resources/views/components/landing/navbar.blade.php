<header
    x-data="{ mobileOpen: false, scrolled: false }"
    x-init="window.addEventListener('scroll', () => { scrolled = window.scrollY > 20 })"
    @keydown.escape.window="mobileOpen = false"
    :class="scrolled ? 'bg-white shadow-md border-line' : 'bg-white border-transparent'"
    class="fixed top-0 inset-x-0 z-50 border-b transition-colors duration-150"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-18 sm:h-20">
            <!-- Brand / Logo -->
            <a href="#hero" class="flex items-center gap-3 group focus:outline-none focus-visible:ring-2 focus-visible:ring-coverage rounded-xl p-1">
                <div class="size-10 rounded-xl bg-primary-strong flex items-center justify-center shadow-sm">
                    <x-application-logo class="size-6 text-white" />
                </div>
                <div class="flex flex-col">
                    <span class="text-xl font-bold tracking-tight text-ink group-hover:text-primary-strong transition-colors">
                        Escala Fácil
                    </span>
                    <span class="text-[0.6875rem] font-semibold tracking-wider uppercase text-primary-strong/80 font-mono -mt-0.5">
                        Plataforma PWA
                    </span>
                </div>
            </a>

            <!-- Desktop Navigation Links -->
            <nav class="hidden md:flex items-center gap-1.5 lg:gap-2 text-sm font-medium text-muted" aria-label="Navegação Principal">
                <a href="#friccao-vs-fluidez" class="px-3.5 py-2 rounded-lg hover:text-ink hover:bg-canvas transition-colors">
                    Fricção vs Fluidez
                </a>
                <a href="#superpoderes" class="px-3.5 py-2 rounded-lg hover:text-ink hover:bg-canvas transition-colors">
                    Superpoderes
                </a>
                <a href="#simulacao" class="px-3.5 py-2 rounded-lg hover:text-ink hover:bg-canvas transition-colors">
                    Fluxo em Tempo Real
                </a>
            </nav>

            <!-- Actions -->
            <div class="hidden md:flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-primary-soft text-primary border border-line/60">
                    <span class="size-1.5 rounded-full bg-coverage animate-pulse"></span>
                    PWA Pronto
                </span>

                <a
                    href="{{ route('login') }}"
                    class="inline-flex items-center gap-2 bg-primary hover:bg-primary-strong active:bg-primary-strong text-white font-semibold text-sm px-5 py-2.5 rounded-lg shadow-sm transition-colors duration-150"
                >
                    <span>Acessar Plataforma</span>
                    <svg class="size-4 -mr-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </a>
            </div>

            <!-- Mobile Hamburger Button -->
            <div class="flex items-center gap-2 md:hidden">
                <a
                    href="{{ route('login') }}"
                    class="bg-primary hover:bg-primary-strong text-white font-semibold text-xs px-3.5 py-2 rounded-lg shadow-sm"
                >
                    Entrar
                </a>

                <button
                    type="button"
                    x-ref="menuButton"
                    @click="mobileOpen = ! mobileOpen"
                    class="p-2.5 rounded-xl text-ink hover:bg-canvas focus:outline-none focus:ring-2 focus:ring-coverage"
                    :aria-label="mobileOpen ? 'Fechar menu de navegação' : 'Abrir menu de navegação'"
                    :aria-expanded="mobileOpen.toString()"
                    aria-controls="landing-mobile-menu"
                    aria-label="Abrir menu de navegação"
                >
                    <svg x-show="! mobileOpen" class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                    <svg x-show="mobileOpen" x-cloak class="size-6 text-ink" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Slide-Down Menu -->
    <div
        id="landing-mobile-menu"
        x-show="mobileOpen"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="md:hidden border-b border-line bg-white px-5 py-6 space-y-4 shadow-md"
        @click.outside="mobileOpen = false"
    >
        <div class="flex flex-col space-y-2 font-medium text-ink">
            <a href="#hero" @click="mobileOpen = false" class="px-4 py-2.5 rounded-lg hover:bg-canvas hover:text-ink transition-colors">
                Início
            </a>
            <a href="#friccao-vs-fluidez" @click="mobileOpen = false" class="px-4 py-2.5 rounded-lg hover:bg-canvas hover:text-ink transition-colors">
                Fricção vs Fluidez
            </a>
            <a href="#superpoderes" @click="mobileOpen = false" class="px-4 py-2.5 rounded-lg hover:bg-canvas hover:text-ink transition-colors">
                Superpoderes
            </a>
            <a href="#simulacao" @click="mobileOpen = false" class="px-4 py-2.5 rounded-lg hover:bg-canvas hover:text-ink transition-colors">
                Fluxo em Tempo Real
            </a>
        </div>

        <div class="pt-4 border-t border-line/80 space-y-3">
            <div class="flex items-center justify-between text-xs text-muted px-1">
                <span>Versão PWA Instalável</span>
                <span class="inline-flex items-center gap-1 font-semibold text-primary-strong">
                    <span class="size-1.5 rounded-full bg-coverage"></span>
                    Ativo
                </span>
            </div>
            <a
                href="{{ route('login') }}"
                class="flex items-center justify-center gap-2 w-full bg-primary hover:bg-primary-strong active:bg-primary-strong text-white font-semibold py-3.5 rounded-lg shadow-sm transition-colors duration-150 text-center"
            >
                <span>Acessar Plataforma</span>
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </a>
        </div>
    </div>
</header>
