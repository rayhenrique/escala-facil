<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Escala Fácil · O seu plantão, sob o seu controle</title>
        <meta name="description" content="Plataforma PWA moderna para gestão de plantões na saúde. Trocas em um clique, calendário no bolso e zero confusão de WhatsApp.">
        <meta name="theme-color" content="#0A4D59">

        <!-- Open Graph / Meta Compartilhamento -->
        <meta property="og:type" content="website">
        <meta property="og:title" content="Escala Fácil · Gestão Inteligente de Plantões">
        <meta property="og:description" content="Esqueça grupos lotados de WhatsApp e planilhas impressas. Acesse sua escala oficial em segundos.">
        <meta property="og:locale" content="pt_BR">

        <!-- PWA Metadata & Favicons -->
        <x-pwa-head />

        <!-- Tipografia Premium (Instrument Sans + IBM Plex Mono) -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800|ibm-plex-mono:500,600&display=swap" rel="stylesheet" />

        <!-- Vite Assets (Tailwind CSS, GSAP, Lenis, Alpine.js) -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body id="landing-page" class="bg-canvas text-ink antialiased selection:bg-coverage selection:text-white overflow-x-clip min-h-screen flex flex-col justify-between">
        <!-- Navegação Flutuante -->
        <x-landing.navbar />

        <!-- Conteúdo Principal Modularizado com Componentes Anônimos Blade -->
        <main class="flex-grow">
            <!-- Hero Section com Mockup Flutuante e Blur Reveal -->
            <x-landing.hero />

            <!-- Comparação: Fricção (Caos no WhatsApp) vs Fluidez (Escala Fácil) -->
            <x-landing.friction-vs-flow />

            <!-- Bento Grid: Tríade de Superpoderes -->
            <x-landing.bento-grid />

            <!-- Simulação Visual Interativa ao Rolar a Tela -->
            <x-landing.scroll-simulation />

            <!-- Bloco de Chamada para Ação (CTA) -->
            <x-landing.cta />
        </main>

        <!-- Rodapé Institucional -->
        <x-landing.footer />
        @livewireScriptConfig
    </body>
</html>
