<section id="friccao-vs-fluidez" class="gsap-friction-section scroll-mt-20 py-20 sm:py-28 lg:py-36 relative overflow-hidden bg-slate-50/70 border-t border-slate-200/60">
    <!-- Glows sutis de fundo -->
    <div class="absolute -top-32 -left-32 w-96 h-96 bg-emerald-100/40 rounded-full blur-3xl pointer-events-none -z-10" aria-hidden="true"></div>
    <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-teal-100/30 rounded-full blur-3xl pointer-events-none -z-10" aria-hidden="true"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Section Header -->
        <div class="text-center max-w-3xl mx-auto mb-14 sm:mb-20">
            <span class="inline-block font-mono text-xs font-bold tracking-[0.2em] uppercase text-emerald-700 bg-emerald-50 border border-emerald-200/70 px-3.5 py-1 rounded-full mb-3">
                Comparação Direta
            </span>
            <h2 class="text-3xl sm:text-5xl font-extrabold text-slate-900 tracking-[-0.03em] leading-tight">
                Troque o caos pela precisão.
            </h2>
            <p class="mt-4 sm:mt-5 text-base sm:text-lg text-slate-600 leading-relaxed">
                A rotina de plantões já exige o máximo da sua atenção. Você não deveria ter que garimpar mensagens em grupos lotados ou caçar planilhas impressas para saber onde vai trabalhar.
            </p>
        </div>

        <!-- Split Grid (1x2 a partir do MD) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-10 items-stretch">
            <!-- Coluna Esquerda: O Caos (Modelo Antigo) -->
            <div class="gsap-friction-chaos flex flex-col justify-between rounded-3xl bg-slate-100/90 border border-slate-200 p-6 sm:p-8 lg:p-10 shadow-sm relative overflow-hidden">
                <!-- Tarja de Status Antigo -->
                <div>
                    <div class="flex items-center justify-between pb-5 border-b border-slate-200/80">
                        <div class="flex items-center gap-2">
                            <span class="size-2.5 rounded-full bg-red-400"></span>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 font-mono">
                                Grupos de WhatsApp & Murais
                            </span>
                        </div>
                        <span class="text-xs font-bold text-red-700 bg-red-100/80 px-2.5 py-1 rounded-full">
                            Inseguro & Caótico
                        </span>
                    </div>

                    <!-- Conversa Caótica Simulada -->
                    <div class="mt-6 space-y-3.5 text-xs sm:text-sm">
                        <!-- Mensagem 1 -->
                        <div class="max-w-[85%] bg-white p-3.5 rounded-2xl rounded-tl-sm border border-slate-200/80 shadow-[0_2px_8px_rgba(0,0,0,0.03)]">
                            <span class="font-bold text-slate-800 text-[0.75rem] block mb-0.5">Dra. Fernanda (14:32)</span>
                            <p class="text-slate-600">Gente, pelo amor de Deus, alguém consegue cobrir minha sexta no Trauma? Minha filha adoeceu 😭</p>
                        </div>

                        <!-- Mensagem 2 -->
                        <div class="max-w-[85%] ml-auto bg-slate-200/80 p-3.5 rounded-2xl rounded-tr-sm text-slate-800">
                            <span class="font-bold text-slate-700 text-[0.75rem] block mb-0.5">Dr. Renato (14:39)</span>
                            <p class="text-slate-700">Eu até poderia cobrir, mas quem pega meu domingo na UTI em troca? Senão fico em dobra e estourei carga!</p>
                        </div>

                        <!-- Mensagem 3 -->
                        <div class="max-w-[85%] bg-white p-3.5 rounded-2xl rounded-tl-sm border border-slate-200/80 shadow-[0_2px_8px_rgba(0,0,0,0.03)]">
                            <span class="font-bold text-amber-800 text-[0.75rem] block mb-0.5">Coordenação (15:10)</span>
                            <p class="text-slate-600">Atenção: Quem for trocar precisa imprimir o anexo 2, colher assinatura das duas partes e protocolar na gerência física até 17h!</p>
                        </div>

                        <!-- Mensagem 4 -->
                        <div class="max-w-[85%] ml-auto bg-red-50 border border-red-200 p-3.5 rounded-2xl rounded-tr-sm text-red-900">
                            <span class="font-bold text-red-800 text-[0.75rem] block mb-0.5">Dr. Bruno (19:05)</span>
                            <p class="text-red-700">Cheguei no hospital e tem dois médicos pro mesmo box e a UTI tá vazia! Quem tá escalado afinal? A folha do mural tá diferente!</p>
                        </div>
                    </div>
                </div>

                <!-- Resumo Negativo -->
                <div class="mt-8 pt-5 border-t border-slate-200/80 flex items-center gap-3 text-xs sm:text-sm text-slate-600">
                    <div class="size-8 rounded-full bg-red-100 text-red-600 flex items-center justify-center shrink-0 font-bold">
                        ✕
                    </div>
                    <p><strong class="text-slate-900">Resultado:</strong> Retrabalho, plantões descobertos, insegurança jurídica e estresse desnecessário para quem salva vidas.</p>
                </div>
            </div>

            <!-- Coluna Direita: A Fluidez (Escala Fácil) -->
            <div class="gsap-friction-flow flex flex-col justify-between rounded-3xl bg-white border-2 border-emerald-200/90 p-6 sm:p-8 lg:p-10 shadow-[0_15px_45px_rgba(5,150,105,0.08)] relative overflow-hidden">
                <!-- Glow decorativo interno -->
                <div class="absolute -right-20 -bottom-20 size-60 bg-emerald-200/30 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>

                <div>
                    <!-- Tarja de Status Fluido -->
                    <div class="flex items-center justify-between pb-5 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <span class="size-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-xs font-bold uppercase tracking-wider text-emerald-800 font-mono">
                                Plataforma Escala Fácil
                            </span>
                        </div>
                        <span class="text-xs font-bold text-emerald-800 bg-emerald-100 px-3 py-1 rounded-full flex items-center gap-1.5">
                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            100% Digital & Seguro
                        </span>
                    </div>

                    <!-- Interface Limpa de Aprovação Oficial -->
                    <div class="mt-6 rounded-2xl bg-emerald-50/40 border border-emerald-200/70 p-5 sm:p-6 text-left relative overflow-hidden">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="size-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold shadow-sm">
                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </div>
                                <div>
                                    <span class="text-xs font-mono font-bold text-emerald-800 uppercase tracking-wider">Recibo Oficial #EF-4821</span>
                                    <h3 class="text-base sm:text-lg font-bold text-slate-900 leading-tight">Troca Homologada pela Gerência</h3>
                                </div>
                            </div>
                            <span class="text-xs font-bold text-emerald-700 bg-white px-2.5 py-1 rounded-lg border border-emerald-200/80 shadow-xs">
                                Aprovada
                            </span>
                        </div>

                        <!-- Participantes da Troca -->
                        <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div class="bg-white p-3 rounded-xl border border-emerald-100">
                                <span class="text-slate-500 block font-medium text-[0.6875rem]">CEDENTE ORIGINAL:</span>
                                <strong class="text-slate-900 block text-sm mt-0.5">Dra. Fernanda Lima</strong>
                                <span class="text-slate-500 block text-[0.6875rem]">Sexta, 14 Nov · 07h às 19h</span>
                            </div>
                            <div class="bg-white p-3 rounded-xl border border-emerald-100">
                                <span class="text-slate-500 block font-medium text-[0.6875rem]">SUBSTITUTO APROVADO:</span>
                                <strong class="text-slate-900 block text-sm mt-0.5">Dr. Renato Albuquerque</strong>
                                <span class="text-slate-500 block text-[0.6875rem]">Sexta, 14 Nov · 07h às 19h</span>
                            </div>
                        </div>

                        <!-- Validações Sistêmicas Automáticas -->
                        <div class="mt-4 pt-3.5 border-t border-emerald-200/60 space-y-1.5 text-xs text-emerald-900">
                            <div class="flex items-center gap-2">
                                <svg class="size-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <span>Compatibilidade de setor e categoria profissional validada</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <svg class="size-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <span>Cota de trocas do mês dentro do limite (1/4 utilizadas)</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <svg class="size-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <span>Sem choque de horário ou intervalo irregular de descanso</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resumo Positivo -->
                <div class="mt-8 pt-5 border-t border-slate-100 flex items-center gap-3 text-xs sm:text-sm text-slate-600">
                    <div class="size-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 font-bold">
                        ✓
                    </div>
                    <p><strong class="text-slate-900">Resultado:</strong> Escala atualizada instantaneamente no celular de todos, histórico auditável e total tranquilidade jurídica.</p>
                </div>
            </div>
        </div>
    </div>
</section>
