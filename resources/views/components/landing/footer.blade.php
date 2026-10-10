<footer class="border-t border-line bg-white py-12 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <!-- Brand & Tagline -->
            <div class="flex items-center gap-3">
                <div class="size-8 rounded-xl bg-primary flex items-center justify-center text-white shadow-xs">
                    <x-application-logo class="size-5" />
                </div>
                <div class="text-left">
                    <span class="font-bold text-ink text-sm tracking-tight block">Escala Fácil</span>
                    <span class="text-xs text-muted">Gestão inteligente de plantões hospitalares</span>
                </div>
            </div>

            <!-- Security Badge -->
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-canvas border border-line/80 text-xs text-muted font-medium">
                <!-- Lucide Icon: Lock -->
                <svg class="size-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
                <span>Ambiente institucional protegido e criptografado</span>
            </div>

            <!-- Footer Quick Links -->
            <div class="flex items-center gap-6 text-xs font-semibold text-muted">
                <a href="{{ route('login') }}" class="hover:text-primary-strong transition-colors">
                    Login
                </a>
                <a href="#superpoderes" class="hover:text-primary-strong transition-colors">
                    Superpoderes
                </a>
                <a href="#hero" class="hover:text-primary-strong transition-colors">
                    Voltar ao topo ↑
                </a>
            </div>
        </div>

        <!-- Copyright, Credits & Version -->
        <div class="mt-8 pt-6 border-t border-line flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-muted text-center sm:text-left">
            <p>© {{ date('Y') }} Escala Fácil. Todos os direitos reservados.</p>
            <div class="flex flex-wrap items-center justify-center sm:justify-end gap-3">
                <p>
                    Desenvolvido por
                    <a href="https://kltecnologia.com" target="_blank" rel="noopener noreferrer" class="font-semibold text-muted hover:text-primary-strong underline decoration-line hover:decoration-primary transition-colors">
                        KL Tecnologia
                    </a>
                </p>
                <span class="text-line" aria-hidden="true">·</span>
                <span class="inline-flex items-center rounded-full bg-canvas px-2 py-0.5 font-mono text-[11px] font-medium text-muted border border-line/80">
                    Versão {{ config('version.current') }}
                </span>
            </div>
        </div>
    </div>
</footer>
