<section id="hero" class="relative scroll-mt-20 pt-32 pb-20 sm:pt-40 sm:pb-28 lg:pt-44 lg:pb-36 overflow-hidden">
    <!-- Efeitos de Profundidade & Glows de Fundo -->
    <div class="absolute top-16 left-1/2 -translate-x-1/2 w-[34rem] sm:w-[50rem] h-[26rem] sm:h-[32rem] bg-emerald-200/25 blur-3xl rounded-full pointer-events-none -z-10" aria-hidden="true"></div>
    <div class="absolute top-72 right-10 w-[20rem] h-[20rem] bg-teal-200/20 blur-3xl rounded-full pointer-events-none -z-10" aria-hidden="true"></div>
    <div class="absolute bottom-10 left-10 w-[22rem] h-[22rem] bg-emerald-100/30 blur-3xl rounded-full pointer-events-none -z-10" aria-hidden="true"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Hero Text Content -->
        <div class="text-center max-w-4xl mx-auto">
            <!-- Badge Superior -->
            <div class="gsap-hero-badge inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-xs sm:text-sm font-semibold tracking-wide shadow-sm mb-6 sm:mb-8">
                <span class="relative flex size-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full size-2 bg-emerald-600"></span>
                </span>
                <span>A revolução na gestão de escalas 🟢</span>
            </div>

            <!-- Título Principal com Blur Reveal -->
            <h1 class="gsap-blur-reveal text-4xl sm:text-6xl lg:text-7xl font-extrabold text-slate-900 tracking-[-0.035em] leading-[1.08] sm:leading-[1.06]">
                O seu plantão, <br class="hidden sm:inline" />
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-700 via-emerald-600 to-teal-600">
                    sob o seu controle.
                </span>
            </h1>

            <!-- Subtítulo com Blur Reveal -->
            <p class="gsap-blur-reveal mt-6 sm:mt-8 text-lg sm:text-xl lg:text-2xl text-slate-600 max-w-2xl sm:max-w-3xl mx-auto font-normal leading-relaxed">
                Esqueça os grupos de WhatsApp e as planilhas confusas. O <strong class="text-slate-900 font-semibold">Escala Fácil</strong> coloca o seu calendário, trocas e aprovações direto no seu bolso.
            </p>

            <!-- Ações / CTAs do Hero -->
            <div class="gsap-hero-ctas mt-8 sm:mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a
                    href="{{ route('login') }}"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-3 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-semibold text-base sm:text-lg px-8 py-4 rounded-full shadow-xl shadow-emerald-600/25 hover:shadow-2xl hover:shadow-emerald-600/35 transition-all duration-200 hover:-translate-y-0.5"
                >
                    <span>Acessar Minha Escala</span>
                    <svg class="size-5 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </a>

                <a
                    href="#friccao-vs-fluidez"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-white/90 hover:bg-white text-slate-700 hover:text-slate-900 font-semibold text-base sm:text-lg px-7 py-4 rounded-full border border-slate-200/90 shadow-sm hover:shadow-md transition-all duration-200"
                >
                    <span>Ver como funciona</span>
                    <svg class="size-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </a>
            </div>

            <!-- Micro-Badges de Confiança -->
            <div class="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs sm:text-sm text-slate-500 font-medium">
                <span class="inline-flex items-center gap-1.5">
                    <svg class="size-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    PWA instalável no iOS & Android
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="size-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    Notificações automáticas no aparelho
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="size-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    Zero mensagens perdidas
                </span>
            </div>
        </div>

        <!-- CSS Simulated Smartphone Mockup (Cinematográfico com .gsap-floating-mockup) -->
        <div class="mt-14 sm:mt-20 relative flex justify-center">
            <!-- Glow Suave Atrás do Celular -->
            <div class="absolute inset-0 max-w-sm mx-auto bg-gradient-to-t from-emerald-300/30 to-teal-400/20 blur-3xl rounded-[3rem] -z-10 transform scale-95" aria-hidden="true"></div>

            <div class="gsap-floating-mockup w-full max-w-[310px] sm:w-[380px] sm:max-w-[380px] rounded-[3.2rem] p-3 bg-slate-950 border-[7px] border-slate-900 shadow-[0_30px_70px_-15px_rgba(15,23,42,0.35)] relative transition-transform">
                <!-- Botões laterais simulados no chassi -->
                <div class="absolute -left-[10px] top-24 w-[3px] h-10 bg-slate-800 rounded-l-md"></div>
                <div class="absolute -left-[10px] top-38 w-[3px] h-12 bg-slate-800 rounded-l-md"></div>
                <div class="absolute -right-[10px] top-28 w-[3px] h-14 bg-slate-800 rounded-r-md"></div>

                <!-- Tela Interna do Celular -->
                <div class="bg-slate-50 rounded-[2.5rem] overflow-hidden border border-slate-200/60 p-4 sm:p-5 relative select-none">
                    <!-- Dynamic Island / Speaker Notch -->
                    <div class="flex items-center justify-between mb-3 px-2 text-[0.6875rem] font-bold text-slate-800">
                        <span>09:41</span>
                        <div class="w-24 h-4 bg-slate-950 rounded-full mx-auto flex items-center justify-end px-2">
                            <span class="size-1.5 rounded-full bg-emerald-500/80 mr-0.5"></span>
                            <span class="size-2 rounded-full bg-slate-800"></span>
                        </div>
                        <div class="flex items-center gap-1 text-[0.625rem]">
                            <span>5G</span>
                            <div class="w-4 h-2 rounded-sm border border-slate-700 p-0.5 flex items-center">
                                <div class="w-full h-full bg-slate-800 rounded-[1px]"></div>
                            </div>
                        </div>
                    </div>

                    <!-- App Bar / Saudação Institucional -->
                    <div class="flex items-center justify-between pb-3.5 border-b border-slate-200/80">
                        <div class="flex items-center gap-2.5">
                            <div class="size-9 rounded-xl bg-emerald-600 flex items-center justify-center text-white shadow-sm font-bold text-xs">
                                LM
                            </div>
                            <div class="flex flex-col text-left">
                                <span class="text-xs font-bold text-slate-900 leading-tight">Dr. Lucas Mendes</span>
                                <span class="text-[0.625rem] text-slate-500 font-medium">UTI Adulto · Hospital Central</span>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[0.625rem] font-bold bg-emerald-100 text-emerald-800">
                            Ativo
                        </span>
                    </div>

                    <!-- Card "Próximo Plantão" -->
                    <div class="mt-4 rounded-2xl bg-white p-4 border border-slate-200/90 shadow-[0_4px_20px_rgb(0,0,0,0.03)] text-left relative overflow-hidden">
                        <div class="absolute top-0 left-0 bottom-0 w-1.5 bg-emerald-600"></div>

                        <div class="flex items-center justify-between text-xs text-slate-500 font-medium">
                            <span class="uppercase tracking-wider font-mono text-[0.625rem] text-emerald-700 font-bold">Próximo Plantão</span>
                            <span class="inline-flex items-center gap-1 text-[0.6875rem] font-semibold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200/60">
                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                Confirmado
                            </span>
                        </div>

                        <div class="mt-2.5 flex items-baseline justify-between">
                            <div>
                                <h3 class="text-base font-extrabold text-slate-900">Amanhã, 14 Nov</h3>
                                <p class="text-xs text-slate-600 font-medium mt-0.5">07:00 às 19:00 (12h Diurno)</p>
                            </div>
                            <span class="text-xs font-mono font-bold text-slate-500">Box 04</span>
                        </div>

                        <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                            <span class="text-slate-500 font-medium">Escala Oficial</span>
                            <span class="font-semibold text-emerald-700">Cobertura 100%</span>
                        </div>
                    </div>

                    <!-- Card "Troca Solicitada" (Simulação de Fluxo) -->
                    <div class="mt-3 rounded-2xl bg-white p-3.5 border border-amber-200/80 bg-amber-50/20 shadow-sm text-left">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <span class="size-2 rounded-full bg-amber-500 animate-pulse"></span>
                                <span class="text-[0.6875rem] font-bold text-amber-900">Troca Mútua em Análise</span>
                            </div>
                            <span class="text-[0.625rem] font-mono text-slate-500">Hoje · 08:30</span>
                        </div>

                        <div class="mt-2 text-xs text-slate-700 space-y-1">
                            <div class="flex items-center justify-between font-medium">
                                <span>Substituto:</span>
                                <strong class="text-slate-900">Dra. Beatriz Santos</strong>
                            </div>
                            <div class="flex items-center justify-between text-[0.6875rem] text-slate-500">
                                <span>Data de Troca:</span>
                                <span>Sexta, 20 Nov (19h-07h)</span>
                            </div>
                        </div>

                        <div class="mt-2.5 flex items-center justify-between pt-2 border-t border-amber-100/80 text-[0.6875rem]">
                            <span class="text-emerald-700 font-semibold">✓ Limite mensal respeitado</span>
                            <span class="font-bold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-full">Aguardando Chefia</span>
                        </div>
                    </div>

                    <!-- Botão de Ação Rápida no Mockup -->
                    <div class="mt-3.5 grid grid-cols-2 gap-2 text-center text-xs">
                        <div class="py-2.5 px-3 rounded-xl bg-emerald-600 text-white font-semibold shadow-sm flex items-center justify-center gap-1.5">
                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                            </svg>
                            <span>Nova Troca</span>
                        </div>
                        <div class="py-2.5 px-3 rounded-xl bg-slate-200/80 text-slate-800 font-semibold flex items-center justify-center gap-1.5">
                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                            </svg>
                            <span>Calendário</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
