<header
    x-data="{ mobileOpen: false, scrolled: false }"
    x-init="window.addEventListener('scroll', () => { scrolled = window.scrollY > 20 })"
    @keydown.escape.window="mobileOpen = false"
    :class="scrolled ? 'bg-white/85 shadow-[0_4px_25px_rgba(0,0,0,0.03)] border-slate-200/70' : 'bg-white/70 border-slate-200/40'"
    class="fixed top-0 inset-x-0 z-50 backdrop-blur-md border-b transition-all duration-300"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-18 sm:h-20">
            <!-- Brand / Logo -->
            <a href="#hero" class="flex items-center gap-3 group focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 rounded-xl p-1">
                <div class="size-10 rounded-xl bg-gradient-to-br from-emerald-600 to-teal-700 flex items-center justify-center shadow-md shadow-emerald-700/20 group-hover:scale-105 transition-transform">
                    <x-application-logo class="size-6 text-white" />
                </div>
                <div class="flex flex-col">
                    <span class="text-xl font-bold tracking-tight text-slate-900 group-hover:text-emerald-700 transition-colors">
                        Escala Fácil
                    </span>
                    <span class="text-[0.6875rem] font-semibold tracking-wider uppercase text-emerald-700/80 font-mono -mt-0.5">
                        Plataforma PWA
                    </span>
                </div>
            </a>

            <!-- Desktop Navigation Links -->
            <nav class="hidden md:flex items-center gap-1.5 lg:gap-2 text-sm font-medium text-slate-600" aria-label="Navegação Principal">
                <a href="#friccao-vs-fluidez" class="px-3.5 py-2 rounded-full hover:text-slate-900 hover:bg-slate-100/80 transition-colors">
                    Fricção vs Fluidez
                </a>
                <a href="#superpoderes" class="px-3.5 py-2 rounded-full hover:text-slate-900 hover:bg-slate-100/80 transition-colors">
                    Superpoderes
                </a>
                <a href="#simulacao" class="px-3.5 py-2 rounded-full hover:text-slate-900 hover:bg-slate-100/80 transition-colors">
                    Fluxo em Tempo Real
                </a>
            </nav>

            <!-- Actions -->
            <div class="hidden md:flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200/60">
                    <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    PWA Pronto
                </span>

                <a
                    href="{{ route('login') }}"
                    class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-semibold text-sm px-5 py-2.5 rounded-full shadow-md shadow-emerald-600/25 hover:shadow-lg hover:shadow-emerald-600/30 transition-all duration-200 hover:-translate-y-0.5"
                >
                    <span>Acessar Plataforma</span>
                    <svg class="size-4 -mr-0.5 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </a>
            </div>

            <!-- Mobile Hamburger Button -->
            <div class="flex items-center gap-2 md:hidden">
                <a
                    href="{{ route('login') }}"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs px-3.5 py-2 rounded-full shadow-sm"
                >
                    Entrar
                </a>

                <button
                    type="button"
                    x-ref="menuButton"
                    @click="mobileOpen = ! mobileOpen"
                    class="p-2.5 rounded-xl text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    :aria-label="mobileOpen ? 'Fechar menu de navegação' : 'Abrir menu de navegação'"
                    :aria-expanded="mobileOpen.toString()"
                    aria-controls="landing-mobile-menu"
                    aria-label="Abrir menu de navegação"
                >
                    <svg x-show="! mobileOpen" class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                    <svg x-show="mobileOpen" x-cloak class="size-6 text-slate-900" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
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
        class="md:hidden border-b border-slate-200 bg-white/95 backdrop-blur-xl px-5 py-6 space-y-4 shadow-xl"
        @click.outside="mobileOpen = false"
    >
        <div class="flex flex-col space-y-2 font-medium text-slate-700">
            <a href="#hero" @click="mobileOpen = false" class="px-4 py-2.5 rounded-xl hover:bg-slate-100 hover:text-slate-900 transition-colors">
                Início
            </a>
            <a href="#friccao-vs-fluidez" @click="mobileOpen = false" class="px-4 py-2.5 rounded-xl hover:bg-slate-100 hover:text-slate-900 transition-colors">
                Fricção vs Fluidez
            </a>
            <a href="#superpoderes" @click="mobileOpen = false" class="px-4 py-2.5 rounded-xl hover:bg-slate-100 hover:text-slate-900 transition-colors">
                Superpoderes
            </a>
            <a href="#simulacao" @click="mobileOpen = false" class="px-4 py-2.5 rounded-xl hover:bg-slate-100 hover:text-slate-900 transition-colors">
                Fluxo em Tempo Real
            </a>
        </div>

        <div class="pt-4 border-t border-slate-200/80 space-y-3">
            <div class="flex items-center justify-between text-xs text-slate-500 px-1">
                <span>Versão PWA Instalável</span>
                <span class="inline-flex items-center gap-1 font-semibold text-emerald-700">
                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                    Ativo
                </span>
            </div>
            <a
                href="{{ route('login') }}"
                class="flex items-center justify-center gap-2 w-full bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-semibold py-3.5 rounded-2xl shadow-lg shadow-emerald-600/25 transition-all text-center"
            >
                <span>Acessar Plataforma</span>
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </a>
        </div>
    </div>
</header>
