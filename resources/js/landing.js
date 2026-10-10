import Lenis from 'lenis';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

export function initLandingPage() {
    const landingRoot = document.getElementById('landing-page');
    if (!landingRoot) {
        return;
    }

    // Movimento reduzido: sem scroll suave, parallax ou animações complexas.
    // Todo o conteúdo permanece visível no estado natural do DOM.
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (prefersReducedMotion) {
        showSimulationFinalState();
        return;
    }

    // 1. Lenis Smooth Scroll sincronizado com o GSAP Ticker
    const lenis = new Lenis({
        duration: 1.2,
        easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
        orientation: 'vertical',
        smoothWheel: true,
        autoRaf: false,
    });

    lenis.on('scroll', ScrollTrigger.update);

    const tickerCallback = (time) => {
        lenis.raf(time * 1000);
    };

    gsap.ticker.add(tickerCallback);
    gsap.ticker.lagSmoothing(0);

    // Suporte a âncoras suaves com Lenis
    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
        anchor.addEventListener('click', (event) => {
            const targetId = anchor.getAttribute('href');
            if (targetId && targetId !== '#') {
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    event.preventDefault();
                    lenis.scrollTo(targetElement, { offset: -80, duration: 1.2 });
                }
            }
        });
    });

    // 2. Orquestração Responsiva com gsap.matchMedia()
    const mm = gsap.matchMedia();

    // Desktop & Tablet (>= 768px): Animações cinematográficas com Blur Reveal e ScrollTriggers avançados
    mm.add('(min-width: 768px)', () => {
        // Hero: Blur Reveal dos textos principais (blur 10px -> 0px e fade-in)
        const blurElements = document.querySelectorAll('.gsap-blur-reveal');
        if (blurElements.length > 0) {
            gsap.fromTo(
                blurElements,
                {
                    opacity: 0,
                    y: 35,
                    filter: 'blur(10px)',
                },
                {
                    opacity: 1,
                    y: 0,
                    filter: 'blur(0px)',
                    duration: 1.1,
                    stagger: 0.15,
                    ease: 'power3.out',
                    clearProps: 'filter',
                }
            );
        }

        // Hero: Badge pop inicial
        const heroBadge = document.querySelector('.gsap-hero-badge');
        if (heroBadge) {
            gsap.fromTo(
                heroBadge,
                { opacity: 0, scale: 0.9, y: 15 },
                { opacity: 1, scale: 1, y: 0, duration: 0.8, ease: 'back.out(1.7)' }
            );
        }

        // Hero: CTAs de entrada
        const heroCtas = document.querySelector('.gsap-hero-ctas');
        if (heroCtas) {
            gsap.fromTo(
                heroCtas,
                { opacity: 0, y: 20 },
                { opacity: 1, y: 0, duration: 0.8, delay: 0.45, ease: 'power2.out' }
            );
        }

        // Seção Fricção vs Fluidez: Entrada paralela dos blocos
        const frictionSection = document.querySelector('.gsap-friction-section');
        const frictionChaos = document.querySelector('.gsap-friction-chaos');
        const frictionFlow = document.querySelector('.gsap-friction-flow');
        if (frictionSection && frictionChaos && frictionFlow) {
            gsap.from(frictionChaos, {
                scrollTrigger: {
                    trigger: frictionSection,
                    start: 'top 75%',
                },
                opacity: 0,
                x: -45,
                duration: 0.9,
                ease: 'power3.out',
            });

            gsap.from(frictionFlow, {
                scrollTrigger: {
                    trigger: frictionSection,
                    start: 'top 75%',
                },
                opacity: 0,
                x: 45,
                duration: 0.9,
                ease: 'power3.out',
            });
        }

        // Bento Grid: Tríade de Superpoderes com efeito Stagger via ScrollTrigger
        const bentoGrid = document.querySelector('.gsap-bento-grid');
        const bentoCards = gsap.utils.toArray('.gsap-bento-card');
        if (bentoGrid && bentoCards.length > 0) {
            gsap.from(bentoCards, {
                scrollTrigger: {
                    trigger: bentoGrid,
                    start: 'top 78%',
                },
                opacity: 0,
                y: 50,
                duration: 0.85,
                stagger: 0.18,
                ease: 'power3.out',
            });
        }

        // Simulação Visual (Seção 3.5 da especificação):
        // Conforme o scroll avança, o status transita de 'Em Análise' (Amber) para 'Aprovado' (Emerald)
        const simSection = document.querySelector('.gsap-simulation-section');
        const simCard = document.querySelector('.gsap-simulation-card');
        const simPending = document.querySelector('.gsap-sim-pending');
        const simApproved = document.querySelector('.gsap-sim-approved');
        const simProgress = document.querySelector('.gsap-sim-progress');
        const simBadgePending = document.querySelector('.gsap-sim-badge-pending');
        const simBadgeApproved = document.querySelector('.gsap-sim-badge-approved');

        if (simSection && simCard && simPending && simApproved) {
            const simTl = gsap.timeline({
                scrollTrigger: {
                    trigger: simSection,
                    start: 'top 65%',
                    end: 'center 45%',
                    scrub: 0.8,
                },
            });

            if (simProgress) {
                simTl.to(simProgress, { width: '100%', ease: 'none' }, 0);
            }

            if (simBadgePending && simBadgeApproved) {
                simTl.to(simBadgePending, { opacity: 0, scale: 0.9, duration: 0.3 }, 0.2);
                simTl.to(simBadgeApproved, { opacity: 1, scale: 1, duration: 0.3 }, 0.5);
            }

            simTl.to(simPending, { opacity: 0, y: -10, duration: 0.35 }, 0.2);
            simTl.to(simApproved, { opacity: 1, y: 0, duration: 0.45 }, 0.45);
            simTl.to(simCard, {
                borderColor: 'rgba(5, 150, 105, 0.4)',
                boxShadow: '0 25px 50px -12px rgba(5, 150, 105, 0.18)',
                duration: 0.5,
            }, 0.45);
        }

        // CTA Box: Surgimento suave
        const ctaBox = document.querySelector('.gsap-cta-box');
        if (ctaBox) {
            gsap.from(ctaBox, {
                scrollTrigger: {
                    trigger: ctaBox,
                    start: 'top 82%',
                },
                opacity: 0,
                y: 35,
                scale: 0.97,
                duration: 0.9,
                ease: 'power3.out',
            });
        }
    });

    // Mobile (< 768px): Animações leves sem filtros pesados de blur para máxima fluidez e taxa de quadros (FPS)
    mm.add('(max-width: 767px)', () => {
        const blurElements = document.querySelectorAll('.gsap-blur-reveal');
        if (blurElements.length > 0) {
            gsap.fromTo(
                blurElements,
                { opacity: 0, y: 20 },
                { opacity: 1, y: 0, duration: 0.7, stagger: 0.1, ease: 'power2.out' }
            );
        }

        const bentoGrid = document.querySelector('.gsap-bento-grid');
        const bentoCards = gsap.utils.toArray('.gsap-bento-card');
        if (bentoGrid && bentoCards.length > 0) {
            gsap.from(bentoCards, {
                scrollTrigger: {
                    trigger: bentoGrid,
                    start: 'top 85%',
                },
                opacity: 0,
                y: 30,
                duration: 0.6,
                stagger: 0.12,
                ease: 'power2.out',
            });
        }

        // Simulação no Mobile: Acionamento direto por ScrollTrigger
        const simSection = document.querySelector('.gsap-simulation-section');
        const simProgress = document.querySelector('.gsap-sim-progress');
        const simPending = document.querySelector('.gsap-sim-pending');
        const simApproved = document.querySelector('.gsap-sim-approved');
        const simBadgePending = document.querySelector('.gsap-sim-badge-pending');
        const simBadgeApproved = document.querySelector('.gsap-sim-badge-approved');
        const simCard = document.querySelector('.gsap-simulation-card');

        if (simSection && simCard && simPending && simApproved) {
            ScrollTrigger.create({
                trigger: simSection,
                start: 'top 60%',
                onEnter: () => {
                    if (simProgress) simProgress.style.width = '100%';
                    if (simBadgePending) simBadgePending.style.display = 'none';
                    if (simBadgeApproved) {
                        simBadgeApproved.style.opacity = '1';
                        simBadgeApproved.style.transform = 'scale(1)';
                    }
                    simPending.style.display = 'none';
                    simApproved.style.opacity = '1';
                    simApproved.style.transform = 'translateY(0)';
                    simCard.style.borderColor = 'rgba(5, 150, 105, 0.4)';
                },
            });
        }
    });
}

// Estado final estático da simulação para movimento reduzido: sem animação,
// o card exibe diretamente a conclusão, mantendo todo o conteúdo visível.
function showSimulationFinalState() {
    const simProgress = document.querySelector('.gsap-sim-progress');
    const simPending = document.querySelector('.gsap-sim-pending');
    const simApproved = document.querySelector('.gsap-sim-approved');
    const simBadgePending = document.querySelector('.gsap-sim-badge-pending');
    const simBadgeApproved = document.querySelector('.gsap-sim-badge-approved');
    const simCard = document.querySelector('.gsap-simulation-card');

    if (simProgress) simProgress.style.width = '100%';
    if (simBadgePending) simBadgePending.style.display = 'none';
    if (simBadgeApproved) {
        simBadgeApproved.style.opacity = '1';
        simBadgeApproved.style.transform = 'none';
    }
    if (simPending && simApproved) {
        simPending.style.display = 'none';
        simApproved.style.opacity = '1';
        simApproved.style.transform = 'none';
    }
    if (simCard) simCard.style.borderColor = 'rgba(5, 150, 105, 0.4)';
}
