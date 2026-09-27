<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'Painel' }} · {{ config('app.name') }}</title>
        <x-pwa-head />
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|ibm-plex-mono:500&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body
        class="min-h-screen bg-canvas text-ink antialiased"
        x-data="{ navigationOpen: false, sidebarCollapsed: localStorage.getItem('escala-facil:sidebar-collapsed') === 'true' }"
        x-init="$watch('sidebarCollapsed', value => localStorage.setItem('escala-facil:sidebar-collapsed', value.toString()))"
        @keydown.escape.window="navigationOpen = false"
        x-on:livewire:navigated.window="navigationOpen = false"
    >
        <div class="app-shell" :class="{ 'app-shell-collapsed': sidebarCollapsed }">
            <div
                x-cloak
                x-show="navigationOpen"
                x-transition.opacity
                class="fixed inset-0 z-40 bg-slate-950/55 lg:hidden"
                @click="navigationOpen = false"
                aria-hidden="true"
            ></div>

            <aside
                id="primary-sidebar"
                class="app-sidebar"
                :class="{ 'app-sidebar-open': navigationOpen, 'app-sidebar-collapsed': sidebarCollapsed }"
                aria-label="Barra lateral principal"
            >
                <div class="sidebar-brand-row flex items-center justify-between gap-3 border-b border-white/10 px-5 py-5">
                    <a href="{{ route('dashboard') }}" wire:navigate class="brand-lockup" title="Escala Fácil">
                        <x-application-logo class="size-10 ring-1 ring-white/20" />
                        <span class="sidebar-label">Escala Fácil</span>
                    </a>
                    <button
                        type="button"
                        class="sidebar-collapse-button hidden lg:inline-flex"
                        @click="sidebarCollapsed = ! sidebarCollapsed"
                        :aria-pressed="sidebarCollapsed.toString()"
                        aria-controls="primary-sidebar"
                        :aria-label="sidebarCollapsed ? 'Expandir barra lateral' : 'Recolher barra lateral'"
                        :title="sidebarCollapsed ? 'Expandir barra lateral' : 'Recolher barra lateral'"
                    >
                        <x-nav-icon name="collapse" class="size-4 transition-transform" x-bind:class="{ 'rotate-180': sidebarCollapsed }" />
                    </button>
                    <button type="button" class="sidebar-icon-button lg:hidden" @click="navigationOpen = false" aria-label="Fechar menu principal">
                        <x-nav-icon name="close" class="size-5" />
                    </button>
                </div>

                <div class="sidebar-user-block border-b border-white/10 px-5 py-5">
                    <p class="truncate text-sm font-semibold text-white">{{ auth()->user()->name }}</p>
                    <p class="mt-1 text-xs text-white/60">{{ auth()->user()->role->label() }}</p>
                </div>

                <nav class="flex-1 overflow-y-auto px-3 py-5" aria-label="Navegação principal">
                    <p class="sidebar-label px-3 font-mono text-[0.6875rem] font-medium uppercase tracking-[0.16em] text-white/45">Menu</p>
                    <div class="mt-3 space-y-1">
                        @if (auth()->user()->role === \App\Enums\UserRole::Manager)
                            <a href="{{ route('manager.dashboard') }}" wire:navigate title="Visão geral" class="sidebar-nav-link {{ request()->routeIs('manager.dashboard') ? 'sidebar-nav-link-active' : '' }}" @if(request()->routeIs('manager.dashboard')) aria-current="page" @endif>
                                <x-nav-icon name="home" class="sidebar-nav-icon" /><span class="sidebar-label">Visão geral</span>
                            </a>
                            <a href="{{ route('manager.calendar') }}" wire:navigate title="Calendário" class="sidebar-nav-link {{ request()->routeIs('manager.calendar') ? 'sidebar-nav-link-active' : '' }}" @if(request()->routeIs('manager.calendar')) aria-current="page" @endif>
                                <x-nav-icon name="calendar" class="sidebar-nav-icon" /><span class="sidebar-label">Calendário</span>
                            </a>
                            <a href="{{ route('manager.shifts') }}" wire:navigate title="Escalas" class="sidebar-nav-link {{ request()->routeIs('manager.shifts') ? 'sidebar-nav-link-active' : '' }}" @if(request()->routeIs('manager.shifts')) aria-current="page" @endif>
                                <x-nav-icon name="schedule" class="sidebar-nav-icon" /><span class="sidebar-label">Escalas</span>
                            </a>
                            <a href="{{ route('manager.swaps') }}" wire:navigate title="Trocas" class="sidebar-nav-link {{ request()->routeIs('manager.swaps') ? 'sidebar-nav-link-active' : '' }}" @if(request()->routeIs('manager.swaps')) aria-current="page" @endif>
                                <x-nav-icon name="swap" class="sidebar-nav-icon" /><span class="sidebar-label">Trocas</span>
                            </a>
                            <a href="{{ route('manager.sectors') }}" wire:navigate title="Setores" class="sidebar-nav-link {{ request()->routeIs('manager.sectors') ? 'sidebar-nav-link-active' : '' }}" @if(request()->routeIs('manager.sectors')) aria-current="page" @endif>
                                <x-nav-icon name="building" class="sidebar-nav-icon" /><span class="sidebar-label">Setores</span>
                            </a>
                            <a href="{{ route('manager.categories') }}" wire:navigate title="Categorias" class="sidebar-nav-link {{ request()->routeIs('manager.categories') ? 'sidebar-nav-link-active' : '' }}" @if(request()->routeIs('manager.categories')) aria-current="page" @endif>
                                <x-nav-icon name="tag" class="sidebar-nav-icon" /><span class="sidebar-label">Categorias</span>
                            </a>
                            <a href="{{ route('manager.users') }}" wire:navigate title="Usuários" class="sidebar-nav-link {{ request()->routeIs('manager.users') ? 'sidebar-nav-link-active' : '' }}" @if(request()->routeIs('manager.users')) aria-current="page" @endif>
                                <x-nav-icon name="users" class="sidebar-nav-icon" /><span class="sidebar-label">Usuários</span>
                            </a>
                        @else
                            <a href="{{ route('professional.dashboard') }}" wire:navigate title="Minha escala" class="sidebar-nav-link {{ request()->routeIs('professional.dashboard') ? 'sidebar-nav-link-active' : '' }}" @if(request()->routeIs('professional.dashboard')) aria-current="page" @endif>
                                <x-nav-icon name="schedule" class="sidebar-nav-icon" /><span class="sidebar-label">Minha escala</span>
                            </a>
                            <a href="{{ route('professional.calendar') }}" wire:navigate title="Calendário" class="sidebar-nav-link {{ request()->routeIs('professional.calendar') ? 'sidebar-nav-link-active' : '' }}" @if(request()->routeIs('professional.calendar')) aria-current="page" @endif>
                                <x-nav-icon name="calendar" class="sidebar-nav-icon" /><span class="sidebar-label">Calendário</span>
                            </a>
                        @endif
                    </div>

                    <div class="mt-6 border-t border-white/10 pt-5">
                        <p class="sidebar-label px-3 font-mono text-[0.6875rem] font-medium uppercase tracking-[0.16em] text-white/45">Suporte</p>
                        <div class="mt-3 space-y-1">
                            <livewire:notification-nav-item />
                            <a href="{{ route('help') }}" wire:navigate title="Ajuda e versões" class="sidebar-nav-link {{ request()->routeIs('help') ? 'sidebar-nav-link-active' : '' }}" @if(request()->routeIs('help')) aria-current="page" @endif>
                                <x-nav-icon name="help" class="sidebar-nav-icon" /><span class="sidebar-label">Ajuda e versões</span>
                            </a>
                            <button type="button" class="sidebar-nav-link w-full" title="Instalar aplicativo" data-pwa-install hidden>
                                <x-nav-icon name="install" class="sidebar-nav-icon" /><span class="sidebar-label">Instalar aplicativo</span>
                            </button>
                        </div>
                        <p class="sidebar-label mt-4 px-3 text-xs text-white/40">Versão {{ config('version.current') }}</p>
                    </div>
                </nav>

                <form method="POST" action="{{ route('logout') }}" class="border-t border-white/10 p-3">
                    @csrf
                    <button type="submit" class="sidebar-nav-link w-full" title="Sair">
                        <x-nav-icon name="logout" class="sidebar-nav-icon" /><span class="sidebar-label">Sair</span>
                    </button>
                </form>
            </aside>

            <div class="flex min-h-screen min-w-0 flex-col">
                <header class="mobile-topbar">
                    <button
                        type="button"
                        class="button-secondary size-11 p-0"
                        @click="navigationOpen = true"
                        :aria-expanded="navigationOpen.toString()"
                        aria-controls="primary-sidebar"
                        aria-label="Abrir menu principal"
                    >
                        <x-nav-icon name="menu" class="size-5" />
                    </button>
                    <a href="{{ route('dashboard') }}" wire:navigate class="brand-lockup text-ink">
                        <x-application-logo class="size-9" />
                        <span>Escala Fácil</span>
                    </a>
                    <span class="size-11" aria-hidden="true"></span>
                </header>

                <main class="mx-auto w-full max-w-page flex-1 px-4 py-8 sm:px-8 sm:py-12 lg:px-10 lg:py-14">
                    {{ $slot }}
                </main>
                <x-system-footer class="mx-4 sm:mx-8 lg:mx-10" />
            </div>
        </div>
        <livewire:release-announcement />
        @livewireScriptConfig
    </body>
</html>
