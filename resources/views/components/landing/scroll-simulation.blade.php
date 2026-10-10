<section id="simulacao" class="gsap-simulation-section scroll-mt-20 py-20 sm:py-28 lg:py-36 relative overflow-hidden bg-slate-50/80 border-t border-slate-200/60">
    <!-- Glow de Fundo -->
    <div class="absolute -top-24 right-1/4 w-[36rem] h-[24rem] bg-emerald-100/40 blur-3xl rounded-full pointer-events-none -z-10" aria-hidden="true"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Section Header -->
        <div class="text-center max-w-3xl mx-auto mb-12 sm:mb-16">
            <span class="inline-block font-mono text-xs font-bold tracking-[0.2em] uppercase text-emerald-700 bg-emerald-50 border border-emerald-200/70 px-3.5 py-1 rounded-full mb-3">
                Experiência Interativa
            </span>
            <h2 class="text-3xl sm:text-5xl font-extrabold text-slate-900 tracking-[-0.03em] leading-tight">
                Aprovação em segundos. Veja na prática.
            </h2>
            <p class="mt-4 sm:mt-5 text-base sm:text-lg text-slate-600 leading-relaxed">
                Role a página e observe a transição em tempo real: o sistema valida as regras institucionais e transforma solicitações pendentes em confirmações homologadas.
            </p>
        </div>

        <!-- Card de Simulação Centralizado -->
        <div class="max-w-3xl mx-auto">
            <div class="gsap-simulation-card rounded-3xl bg-white border-2 border-slate-200/90 p-6 sm:p-9 shadow-[0_15px_40px_rgb(0,0,0,0.04)] relative overflow-hidden transition-all duration-300">
                <!-- Barra de Progresso do Fluxo (animada por scrub no GSAP) -->
                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden mb-7">
                    <div class="gsap-sim-progress h-full w-0 bg-gradient-to-r from-amber-400 via-emerald-500 to-teal-600 rounded-full transition-all"></div>
                </div>

                <!-- Cabeçalho do Card com Badges Transicionáveis -->
                <div class="flex items-center justify-between pb-6 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="size-11 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-700 font-bold">
                            <svg class="size-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-xs font-mono font-bold text-slate-500 uppercase tracking-wider block">ID: #TR-2026-9042</span>
                            <h3 class="text-base sm:text-lg font-bold text-slate-900 leading-tight">Plantão de Domingo · Emergência Geral</h3>
                        </div>
                    </div>

                    <!-- Badges com transição controlada via GSAP -->
                    <div class="relative flex items-center">
                        <span class="gsap-sim-badge-pending inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-200">
                            <span class="size-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                            Em Análise
                        </span>
                        <span class="gsap-sim-badge-approved absolute right-0 opacity-0 scale-90 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-900 border border-emerald-200">
                            <span class="size-1.5 rounded-full bg-emerald-600"></span>
                            Aprovado Oficialmente
                        </span>
                    </div>
                </div>

                <!-- Detalhes do Turno -->
                <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs sm:text-sm">
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                        <span class="text-slate-500 text-xs font-medium block">Horário do Turno</span>
                        <strong class="text-slate-800 text-sm sm:text-base mt-0.5 block">07:00 às 19:00 (12 Horas)</strong>
                        <span class="text-slate-500 text-xs mt-1 block">Domingo, 22 de Novembro</span>
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                        <span class="text-slate-500 text-xs font-medium block">Profissionais Envolvidos</span>
                        <strong class="text-slate-800 text-sm sm:text-base mt-0.5 block">Dr. Tiago → Dra. Camila</strong>
                        <span class="text-emerald-700 font-semibold text-xs mt-1 block">Substituição voluntária aceita</span>
                    </div>
                </div>

                <!-- Conteúdo Transicionável (Pending vs Approved) -->
                <div class="mt-6 relative min-h-[90px]">
                    <!-- Estado 1: Em Análise -->
                    <div class="gsap-sim-pending rounded-2xl bg-amber-50/70 border border-amber-200/80 p-4 text-xs sm:text-sm text-amber-900 flex items-center justify-between transition-all">
                        <div class="flex items-center gap-3">
                            <div class="size-8 rounded-full bg-amber-200/80 flex items-center justify-center shrink-0">
                                <span class="animate-spin text-amber-800 text-base">⏳</span>
                            </div>
                            <div>
                                <strong class="block text-amber-950 font-bold">Aguardando homologação da coordenação médica</strong>
                                <span class="text-amber-800/80 text-xs block">O substituto já aceitou. Notificação na fila da gestão.</span>
                            </div>
                        </div>
                        <span class="hidden sm:inline-block font-mono text-[0.6875rem] font-bold uppercase text-amber-700 bg-white/70 px-2 py-1 rounded-md">Etapa 2 de 3</span>
                    </div>

                    <!-- Estado 2: Aprovado (Transição suave no scroll) -->
                    <div class="gsap-sim-approved absolute inset-0 opacity-0 translate-y-3 rounded-2xl bg-emerald-50 border border-emerald-200 p-4 text-xs sm:text-sm text-emerald-900 flex items-center justify-between transition-all">
                        <div class="flex items-center gap-3">
                            <div class="size-8 rounded-full bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </div>
                            <div>
                                <strong class="block text-emerald-950 font-bold">Troca Homologada com Sucesso!</strong>
                                <span class="text-emerald-800/80 text-xs block">Escala atualizada em tempo real. Recibo com hash de segurança gerado.</span>
                            </div>
                        </div>
                        <span class="hidden sm:inline-block font-mono text-[0.6875rem] font-bold uppercase text-emerald-800 bg-white px-2.5 py-1 rounded-md border border-emerald-200/80 shadow-xs">Concluído</span>
                    </div>
                </div>

                <!-- Rodapé de Apoio ao Scroll -->
                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-2 rounded-full bg-emerald-500"></span>
                        Simulação baseada no fluxo real do Escala Fácil
                    </span>
                    <span class="font-mono text-[0.6875rem]">Role para alternar o status</span>
                </div>
            </div>
        </div>
    </div>
</section>
