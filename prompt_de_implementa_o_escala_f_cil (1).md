# Prompt de Implementação: Escala Fácil Landing Page

Aja como um Engenheiro Frontend Sênior especializado no ecossistema Laravel, Blade, Tailwind CSS, GSAP e Lenis. Seu objetivo é implementar uma landing page *mobile-first*, cinematográfica e de alta conversão para o projeto "Escala Fácil".

## Contexto Técnico & Regras Estritas

1. **Stack:** Laravel (Views em Blade), Tailwind CSS (estilização), Alpine.js (interatividade simples de UI, como menus mobile), GSAP + ScrollTrigger (animações complexas) e Lenis (smooth scroll).

2. **Single File Output (Componentização):** A landing page será montada em um único arquivo de view principal (ex: `welcome.blade.php` ou `landing.blade.php`), mas **DEVE** utilizar componentes anônimos do Blade para modularidade (ex: `<x-landing.hero />`, `<x-landing.bento-grid />`). Crie o código prevendo essa estrutura.

3. **Instalação de Assets:** O GSAP e o Lenis devem ser instalados via NPM e inicializados no arquivo `resources/js/app.js` processado pelo Vite. Não utilize CDNs injetados no HTML.

4. **Semântica HTML & Acessibilidade:** Utilize tags semânticas (`<section>`, `<nav>`, `<article>`) e mantenha atributos ARIA onde necessário.

5. **Idioma:** Todos os textos estáticos (*copy*) devem estar em Português do Brasil (PT-BR).

## Diretrizes de Design (Tailwind CSS)

* **Backgrounds:** Utilize `bg-slate-50` para o corpo e `bg-white` para superfícies flutuantes.

* **Cores de Marca:** O verde primário é `emerald-600` (Hover: `emerald-700`).

* **Bordas e Sombras:** Utilize exaustivamente `rounded-2xl`, `rounded-3xl` e sombras muito difusas (ex: `shadow-[0_8px_30px_rgb(0,0,0,0.04)]`).

* **Efeitos de Profundidade:** Adicione *glows* de fundo com divs absolutas `bg-emerald-200/20 blur-3xl rounded-full` posicionadas atrás de elementos críticos para dar uma estética *premium*.

## Roteiro de Implementação

### 1. Setup no Javascript (`resources/js/app.js`)

Crie o script de inicialização que importa o Lenis e o GSAP, sincronizando o RequestAnimationFrame do Lenis com o Ticker do GSAP. Registre o plugin `ScrollTrigger`.

### 2. Estrutura Blade (`landing.blade.php`)

Desenvolva a estrutura principal da página sequencialmente:

* **Navbar:** Fixa, `backdrop-blur-md bg-white/70`, logo e botão "Acessar Plataforma" (`bg-emerald-600 text-white rounded-full`).

* **Hero Section:** Textos centralizados com a classe `.gsap-blur-reveal`. Crie um mockup simulado em CSS (uma div com formato de celular, bordas grossas pretas/cinzas, sombra premium) com a classe `.gsap-floating-mockup`. O conteúdo do mockup é um card de aprovação de plantão.

* **Split Section (Fricção vs Fluidez):** Grid 1x2 (MD em diante). Esquerda com balões de chat cinzas (simulando caos). Direita com uma UI limpa do aplicativo.

* **Bento Grid:** Grid estruturado para a "Tríade de Superpoderes". Utilize os ícones Lucide (via package Blade UI Kit ou SVG inline). Adicione a classe `.gsap-bento-card` em cada card para o efeito *stagger*.

* **CTA Bottom:** Container de tela inteira com um *box* interno largo (`bg-emerald-50`), texto forte e botão de login.

* **Footer:** Limpo, borda sutil superior, texto sobre direitos reservados e ícone de cadeado.

### 3. Orquestração GSAP (Lógica de Animação)

Dentro do arquivo de scripts (ou em uma tag `<script type="module">` dedicada ao fim da página), implemente as seguintes regras com `gsap`:

* Selecione os textos do Hero e aplique um `.fromTo()` simulando um desfoque (blur 10px para 0px) e fade-in na entrada.

* Aplique uma animação infinita de flutuação no mockup do celular (`y: 15, duration: 4, yoyo: true, ease: 'sine.inOut'`).

* Utilize o `ScrollTrigger` e a funcionalidade `batch` ou `stagger` para fazer os cards do Bento Grid aparecerem sequencialmente quando entrarem na *viewport*.

* Envolva as animações de ScrollTrigger complexas em um `gsap.matchMedia()` para desativar efeitos pesados em telas menores que 768px, garantindo performance no celular.

Gere o código completo, respeitando o ecossistema Laravel e garantindo que o design reflete uma estética "Apple Healthtech Premium".