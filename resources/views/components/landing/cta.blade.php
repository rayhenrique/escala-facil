<section class="py-16 sm:py-24 lg:py-28 relative overflow-hidden bg-slate-50">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="gsap-cta-box relative overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-emerald-50 via-teal-50/40 to-white border-2 border-emerald-200/90 p-8 sm:p-14 lg:p-16 text-center shadow-[0_20px_50px_rgba(5,150,105,0.08)]">
            <!-- Glows Sutis de Fundo -->
            <div class="absolute -top-24 -left-24 size-72 bg-emerald-200/40 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>
            <div class="absolute -bottom-24 -right-24 size-72 bg-teal-200/30 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>

            <!-- Eyebrow -->
            <span class="inline-block font-mono text-xs font-bold tracking-[0.2em] uppercase text-emerald-800 bg-white/80 border border-emerald-200/80 px-4 py-1.5 rounded-full mb-4 shadow-xs">
                Acesso Imediato
            </span>

            <!-- Título Principal do CTA -->
            <h2 class="text-3xl sm:text-5xl lg:text-5xl font-extrabold text-slate-900 tracking-[-0.03em] leading-tight max-w-2xl mx-auto">
                Sua escala está pronta para ser visualizada.
            </h2>

            <!-- Subtítulo -->
            <p class="mt-4 sm:mt-6 text-base sm:text-xl text-slate-600 max-w-2xl mx-auto leading-relaxed">
                Entre agora com as suas credenciais institucionais e assuma o controle definitivo dos seus turnos, coberturas e confirmações de troca.
            </p>

            <!-- Botão de Ação -->
            <div class="mt-8 sm:mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a
                    href="{{ route('login') }}"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-3 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold text-base sm:text-lg px-9 py-4 rounded-full shadow-xl shadow-emerald-600/25 hover:shadow-2xl hover:shadow-emerald-600/35 transition-all duration-200 hover:-translate-y-0.5 active:translate-y-0"
                >
                    <span>Fazer Login na Plataforma</span>
                    <svg class="size-5 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </a>

                <a
                    href="{{ route('help') }}"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-base px-6 py-4 rounded-full border border-slate-200/90 shadow-xs hover:shadow-sm transition-all"
                >
                    <svg class="size-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                    </svg>
                    <span>Central de Dúvidas</span>
                </a>
            </div>

            <!-- Selos de Confiança -->
            <div class="mt-8 pt-8 border-t border-emerald-100 flex flex-wrap items-center justify-center gap-6 text-xs text-slate-500 font-medium">
                <span class="inline-flex items-center gap-1.5">
                    <svg class="size-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    Acesso Exclusivo Institucional
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="size-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                    </svg>
                    Instale direto no seu celular
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="size-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    Validade e Auditoria das Trocas
                </span>
            </div>
        </div>
    </div>
</section>
