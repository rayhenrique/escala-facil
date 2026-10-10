<section class="py-16 sm:py-24 lg:py-28 relative overflow-hidden bg-canvas">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="gsap-cta-box relative overflow-hidden rounded-xl bg-primary-strong p-8 sm:p-14 lg:p-16 text-center shadow-soft">
            <!-- Eyebrow -->
            <span class="inline-block font-mono text-xs font-bold tracking-[0.2em] uppercase text-white bg-white/10 border border-white/20 px-4 py-1.5 rounded-full mb-4">
                Acesso Imediato
            </span>

            <!-- Título Principal do CTA -->
            <h2 class="text-3xl sm:text-5xl lg:text-5xl font-semibold text-white tracking-[-0.02em] leading-tight max-w-2xl mx-auto">
                Sua escala está pronta para ser visualizada.
            </h2>

            <!-- Subtítulo -->
            <p class="mt-4 sm:mt-6 text-base sm:text-xl text-white/70 max-w-2xl mx-auto leading-relaxed">
                Entre agora com as suas credenciais institucionais e assuma o controle definitivo dos seus turnos, coberturas e confirmações de troca.
            </p>

            <!-- Botão de Ação -->
            <div class="mt-8 sm:mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a
                    href="{{ route('login') }}"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-3 bg-white hover:bg-primary-soft active:bg-primary-soft text-primary-strong font-bold text-base sm:text-lg px-9 py-4 rounded-lg shadow-md transition-colors duration-150"
                >
                    <span>Fazer Login na Plataforma</span>
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </a>

                <a
                    href="#superpoderes"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-transparent hover:bg-white/10 text-white font-semibold text-base px-6 py-4 rounded-lg border border-white/25 shadow-sm transition-colors duration-150"
                >
                    <svg class="size-5 text-white/80" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                    </svg>
                    <span>Explorar superpoderes</span>
                </a>
            </div>

            <!-- Selos de Confiança -->
            <div class="mt-8 pt-8 border-t border-white/15 flex flex-wrap items-center justify-center gap-6 text-xs text-white/65 font-medium">
                <span class="inline-flex items-center gap-1.5">
                    <svg class="size-4 text-white/80" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    Acesso Exclusivo Institucional
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="size-4 text-white/80" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                    </svg>
                    Instale direto no seu celular
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="size-4 text-white/80" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    Validade e Auditoria das Trocas
                </span>
            </div>
        </div>
    </div>
</section>
