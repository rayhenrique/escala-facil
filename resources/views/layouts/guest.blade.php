<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'Acesso' }} · {{ config('app.name') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|ibm-plex-mono:500&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-canvas text-ink antialiased">
        <main class="auth-shell">
            <section class="auth-context" aria-labelledby="product-name">
                <div class="relative z-10 max-w-lg">
                    <a href="{{ route('login') }}" class="brand-lockup" wire:navigate>
                        <x-application-logo class="size-11" />
                        <span id="product-name">Escala Fácil</span>
                    </a>

                    <p class="mt-16 font-mono text-xs font-medium uppercase tracking-[0.2em] text-teal-100">Gestão de plantões</p>
                    <h1 class="mt-4 max-w-md text-4xl font-semibold leading-[1.08] tracking-[-0.035em] text-white sm:text-5xl">
                        A escala certa, com cada decisão no lugar.
                    </h1>
                    <p class="mt-6 max-w-md text-base leading-7 text-teal-50/80">
                        Acompanhe plantões, trocas e coberturas em um ambiente seguro para gestores e profissionais.
                    </p>

                    <div class="mt-12 grid grid-cols-3 gap-2" aria-hidden="true">
                        <span class="h-2 rounded-full bg-white/85"></span>
                        <span class="h-2 rounded-full bg-coverage"></span>
                        <span class="h-2 rounded-full bg-amber-300"></span>
                    </div>
                </div>
            </section>

            <section class="auth-form-panel">
                <div class="my-auto w-full max-w-md py-10">
                    <div class="mb-10 flex items-center gap-3 lg:hidden">
                        <x-application-logo class="size-10" />
                        <span class="text-lg font-semibold tracking-tight">Escala Fácil</span>
                    </div>
                    {{ $slot }}
                    <p class="mt-10 text-sm leading-6 text-muted">
                        Acesso exclusivo para usuários cadastrados pela gestão.
                    </p>
                </div>
                <x-system-footer class="w-full max-w-md" />
            </section>
        </main>
        @livewireScriptConfig
    </body>
</html>
