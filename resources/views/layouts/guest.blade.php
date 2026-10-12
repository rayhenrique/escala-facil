<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'Acesso' }} · {{ config('app.name') }}</title>
        <x-pwa-head />
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|ibm-plex-mono:500&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body id="auth-page" class="bg-canvas text-ink antialiased">
        <main class="auth-shell">
            <section class="auth-context" aria-labelledby="auth-context-title">
                <div class="relative z-10 max-w-lg">
                    <a href="{{ route('login') }}" class="brand-lockup" wire:navigate>
                        <x-application-logo class="size-11" />
                        <span id="product-name">Escala Fácil</span>
                    </a>

                    <p class="mt-12 font-mono text-xs font-medium uppercase tracking-[0.17em] text-primary-soft">Gestão hospitalar</p>
                    <h2 id="auth-context-title" class="mt-4 max-w-md text-4xl font-semibold leading-tight tracking-[-0.035em] text-white xl:text-5xl">
                        Plantões, trocas e cobertura em um só lugar.
                    </h2>
                    <p class="mt-5 max-w-md text-base leading-7 text-primary-soft">
                        A gerência organiza a escala e acompanha a cobertura. Os profissionais consultam seus plantões e seguem cada etapa das solicitações de troca.
                    </p>
                </div>
            </section>

            <section class="auth-form-panel">
                <div class="auth-content">
                    <a href="{{ route('landing') }}" class="mb-7 flex min-h-11 w-fit items-center gap-3 rounded-lg hover:text-primary lg:hidden" wire:navigate>
                        <x-application-logo class="size-10" />
                        <span class="text-lg font-semibold tracking-tight">Escala Fácil</span>
                    </a>
                    {{ $slot }}
                    <p class="mt-6 text-sm leading-6 text-muted">
                        Acesso exclusivo para usuários cadastrados pela gestão.
                    </p>
                    <x-auth.install-help />
                </div>
                <x-system-footer class="mt-auto w-full max-w-md" />
            </section>
        </main>
        <x-feedback-outlet />
        @livewireScriptConfig
    </body>
</html>
