<section id="hero" tabindex="-1" aria-labelledby="hero-title" class="landing-hero scroll-mt-24">
    <div class="landing-container grid items-center gap-10 lg:grid-cols-[1.05fr_1fr] lg:gap-14">
        <div>
            <p class="gsap-hero-badge eyebrow">Gestão de escalas hospitalares</p>
            <h1 id="hero-title" class="gsap-blur-reveal mt-4 text-4xl font-semibold leading-[1.08] tracking-[-0.035em] sm:text-5xl xl:text-6xl">
                O seu plantão,<br /> <span class="text-primary">sob o seu controle.</span>
            </h1>
            <p class="gsap-blur-reveal mt-5 max-w-xl text-lg leading-8 text-muted">
                Escalas, trocas e cobertura em um só lugar. A gerência organiza os plantões; os profissionais consultam sua agenda e acompanham cada solicitação.
            </p>
            <div class="gsap-hero-ctas landing-actions mt-7">
                <a href="{{ route('login') }}" class="button-primary">Acessar plataforma <span aria-hidden="true" class="ml-3">→</span></a>
                <a href="#superpoderes" class="button-secondary">Conhecer os recursos <span aria-hidden="true" class="ml-3">↓</span></a>
            </div>
            <p class="mt-5 text-sm leading-6 text-muted">Para gerentes e plantonistas. Acesso com credenciais fornecidas pela instituição.</p>
        </div>
        <x-landing.product-preview />
    </div>
</section>
