import Lenis from 'lenis';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

let activeRoot = null;
let cleanupLanding = null;

// DOMContentLoaded e livewire:navigated podem ocorrer para a mesma página.
document.addEventListener('livewire:navigating', () => cleanupLanding?.());

export function initLandingPage() {
    const landingRoot = document.getElementById('landing-page');
    if (landingRoot === activeRoot) return;
    cleanupLanding?.();
    if (!landingRoot) {
        return;
    }

    activeRoot = landingRoot;
    const listeners = new AbortController();
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let lenis;
    let tickerCallback;
    const simulationStyles = [...landingRoot.querySelectorAll('[class*="gsap-sim"]')]
        .map((element) => [element, element.getAttribute('style')]);
    const mm = gsap.matchMedia();
    const refresh = () => ScrollTrigger.refresh();
    cleanupLanding = () => {
        listeners.abort();
        mm.revert();
        if (tickerCallback) gsap.ticker.remove(tickerCallback);
        lenis?.destroy();
        simulationStyles.forEach(([element, style]) => {
            if (style === null) element.removeAttribute('style');
            else element.setAttribute('style', style);
        });
        activeRoot = null;
        cleanupLanding = null;
    };
    motion.addEventListener('change', () => {
        cleanupLanding?.();
        initLandingPage();
    }, { signal: listeners.signal });

    if (motion.matches) {
        showSimulationFinalState();
        return;
    }

    try {
        // 1. Lenis Smooth Scroll sincronizado com o GSAP Ticker
        lenis = new Lenis({
            duration: 1.2,
            easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
            orientation: 'vertical',
            smoothWheel: true,
            autoRaf: false,
        });

        lenis.on('scroll', ScrollTrigger.update);

        tickerCallback = (time) => {
            lenis.raf(time * 1000);
        };

        gsap.ticker.add(tickerCallback);

        // Suporte a âncoras suaves com Lenis
        document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
            anchor.addEventListener('click', (event) => {
                if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
                const targetId = anchor.getAttribute('href');
                if (targetId && targetId !== '#') {
                    const targetElement = document.getElementById(decodeURIComponent(targetId.slice(1)));
                    if (targetElement) {
                        event.preventDefault();
                        lenis.scrollTo(targetElement, {
                            offset: -96,
                            duration: 1.2,
                            onComplete: () => {
                                window.history.pushState(null, '', targetId);
                                targetElement.focus({ preventScroll: true });
                            },
                        });
                    }
                }
            }, { signal: listeners.signal });
        });

        // 2. Orquestração Responsiva com gsap.matchMedia()
        // Desktop: movimento decorativo, sem esconder conteúdo.
        mm.add('(min-width: 768px)', () => {
            // Hero: deslocamento leve dos textos, sempre legíveis.
            const blurElements = document.querySelectorAll('.gsap-blur-reveal');
            if (blurElements.length > 0) {
                gsap.fromTo(
                    blurElements,
                    {
                        y: 35,
                    },
                    {
                        y: 0,
                        duration: 1.1,
                        stagger: 0.15,
                        ease: 'power3.out',
                        clearProps: 'transform',
                    }
                );
            }

            // Hero: Badge pop inicial
            const heroBadge = document.querySelector('.gsap-hero-badge');
            if (heroBadge) {
                gsap.fromTo(
                    heroBadge,
                    { scale: 0.9, y: 15 },
                    { scale: 1, y: 0, duration: 0.8, ease: 'back.out(1.7)', clearProps: 'transform' }
                );
            }

            // Hero: CTAs de entrada
            const heroCtas = document.querySelector('.gsap-hero-ctas');
            if (heroCtas) {
                gsap.fromTo(
                    heroCtas,
                    { y: 20 },
                    { y: 0, duration: 0.8, delay: 0.45, ease: 'power2.out', clearProps: 'transform' }
                );
            }

            // Seção Fricção vs Fluidez: Entrada paralela dos blocos
            const frictionSection = document.querySelector('.gsap-friction-section');
            const frictionChaos = document.querySelector('.gsap-friction-chaos');
            const frictionFlow = document.querySelector('.gsap-friction-flow');
            if (frictionSection && frictionChaos && frictionFlow) {
                gsap.from(frictionChaos, {
                    immediateRender: false,
                    clearProps: 'transform',
                    scrollTrigger: {
                        trigger: frictionSection,
                        start: 'top 75%',
                    },
                    x: -45,
                    duration: 0.9,
                    ease: 'power3.out',
                });

                gsap.from(frictionFlow, {
                    immediateRender: false,
                    clearProps: 'transform',
                    scrollTrigger: {
                        trigger: frictionSection,
                        start: 'top 75%',
                    },
                    x: 45,
                    duration: 0.9,
                    ease: 'power3.out',
                });
            }

            // Cada funcionalidade usa seu próprio trigger, inclusive após resize.
            const bentoGrid = document.querySelector('.gsap-bento-grid');
            const bentoCards = gsap.utils.toArray('.gsap-bento-card');
            if (bentoGrid && bentoCards.length > 0) {
                bentoCards.forEach((card) => gsap.from(card, {
                    immediateRender: false,
                    clearProps: 'transform',
                    scrollTrigger: {
                        trigger: card,
                        start: 'top 78%',
                    },
                    y: 50,
                    duration: 0.85,
                    ease: 'power3.out',
                }));
            }

            // A linha reforça a sequência; todas as etapas já estão legíveis.
            const simSection = document.querySelector('.gsap-simulation-section');
            const simProgress = document.querySelector('.gsap-sim-progress');
            if (simSection && simProgress) {
                gsap.to(simProgress, {
                    width: '100%',
                    ease: 'none',
                    scrollTrigger: {
                        trigger: simSection,
                        start: 'top 75%',
                        end: 'bottom 70%',
                        scrub: 0.8,
                    },
                });
            }

            // CTA Box: Surgimento suave
            const ctaBox = document.querySelector('.gsap-cta-box');
            if (ctaBox) {
                gsap.from(ctaBox, {
                    immediateRender: false,
                    clearProps: 'transform',
                    scrollTrigger: {
                        trigger: ctaBox,
                        start: 'top 82%',
                    },
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
                    { y: 20 },
                    { y: 0, duration: 0.7, stagger: 0.1, ease: 'power2.out', clearProps: 'transform' }
                );
            }

            const bentoGrid = document.querySelector('.gsap-bento-grid');
            const bentoCards = gsap.utils.toArray('.gsap-bento-card');
            if (bentoGrid && bentoCards.length > 0) {
                bentoCards.forEach((card) => gsap.from(card, {
                    immediateRender: false,
                    clearProps: 'transform',
                    scrollTrigger: {
                        trigger: card,
                        start: 'top 85%',
                    },
                    y: 30,
                    duration: 0.6,
                    ease: 'power2.out',
                }));
            }

            const simSection = document.querySelector('.gsap-simulation-section');
            const simProgress = document.querySelector('.gsap-sim-progress');
            if (simSection && simProgress) {
                ScrollTrigger.create({
                    trigger: simSection,
                    start: 'top 70%',
                    once: true,
                    onRefresh: (self) => { if (self.progress > 0) showSimulationFinalState(); },
                    onEnter: showSimulationFinalState,
                });
            }
            return () => simulationStyles.forEach(([element, style]) => {
                if (style === null) element.removeAttribute('style');
                else element.setAttribute('style', style);
            });
        });
        window.addEventListener('load', refresh, { once: true, signal: listeners.signal });
        window.addEventListener('pageshow', refresh, { signal: listeners.signal });
        document.fonts?.ready.then(() => {
            if (!listeners.signal.aborted) refresh();
        });
        refresh();
    } catch (error) {
        // Falhas na melhoria visual devolvem o DOM ao estado legível.
        cleanupLanding?.();
        showSimulationFinalState();
        console.warn('Animações da landing indisponíveis.', error);
    }
}

// A sequência permanece visível sem animação ou com movimento reduzido.
function showSimulationFinalState() {
    const simProgress = document.querySelector('.gsap-sim-progress');
    if (simProgress) simProgress.style.width = '100%';
}
