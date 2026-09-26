<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|ibm-plex-mono:500&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-canvas px-4 py-8 text-ink antialiased sm:p-8">
    <main class="my-auto w-full max-w-lg self-center rounded-xl border border-line bg-white p-5 shadow-soft sm:p-10">
        <a href="{{ url('/') }}" class="brand-lockup text-ink">
            <x-application-logo class="size-10" />
            <span>Escala Fácil</span>
        </a>
        <p class="eyebrow mt-10">Erro {{ $code }}</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight">{{ $title }}</h1>
        <p class="mt-4 leading-7 text-muted">{{ $message }}</p>
        @auth
            <a href="{{ route('dashboard') }}" class="button-primary mt-8 w-full sm:w-auto">Voltar ao meu painel</a>
        @else
            <a href="{{ route('login') }}" class="button-primary mt-8 w-full sm:w-auto">Ir para o acesso</a>
        @endauth
    </main>
    <x-system-footer class="mt-8" />
</body>
</html>
