# Especificação da Landing Page: Escala Fácil

## 1. Visão Geral
* **Produto:** Escala Fácil (Plataforma PWA para gestão de plantões).
* **Objetivo:** Direcionar profissionais de saúde (médicos, enfermeiros, técnicos) para o acesso ao sistema (Login) e evidenciar o valor da ferramenta no seu dia a dia.
* **Público-Alvo:** Profissionais e plantonistas que operam em regime de turnos, com uso predominante via celular/smartphone (mobile-first). Exaustos, com pouco tempo e pouca tolerância a fricção digital.
* **Mensagem Central:** "O seu plantão sem caos. Trocas em um clique, calendário no bolso e zero confusão no WhatsApp."
* **Sensação Desejada:** Clareza mental, alívio, precisão clínica e confiança institucional.

## 2. Design System & Direção Visual
Estilo **Editorial Healthtech Premium** (Inspirado em Apple Health e Stripe). Design arejado, focado na clareza e precisão.

### 2.1. Paleta de Cores
* **Fundo (Background):** `slate-50` (#F8FAFC) - Traz respiro e iluminação clínica.
* **Superfícies (Cards/Modais):** `white` (#FFFFFF) com bordas `slate-200` translúcidas.
* **Primária / Ação:** `emerald-600` (#059669) com *hover* em `emerald-700`.
* **Destaques Visuais (Glows):** Luzes difusas no fundo da tela em `emerald-100` e `teal-50` (com `blur-3xl`).
* **Texto:** `slate-900` (Títulos e destaques) e `slate-600` (Corpo do texto).
* **Status:** `amber-500` (Pendente), `emerald-500` (Aprovado).

### 2.2. Tipografia e UI
* **Fontes:** Sans-serif geométrica (Inter, SF Pro ou similar). Títulos com espaçamento entre letras levemente reduzido (*tracking-tight*).
* **Cantos:** Arredondados e contínuos (`rounded-2xl`, `rounded-3xl`).
* **Sombras (Shadows):** Sombras multidirecionais e muito suaves (*ambient occlusion* - ex: `shadow-[0_8px_30px_rgb(0,0,0,0.04)]`).
* **Materiais:** *Glassmorphism* moderado (`backdrop-blur-md bg-white/70`) na navegação e elementos flutuantes.

## 3. Estrutura Narrativa (Arquitetura da Página)

### 3.1. Navbar Flutuante
* **Visual:** Fixo no topo da tela, fundo branco translúcido.
* **Elementos:** Logo "Escala Fácil" à esquerda; botão primário "Acessar Plataforma" à direita.

### 3.2. Hero Section (Cinematográfico)
* **Copy:** 
  * *Badge Superior:* "A revolução na gestão de escalas 🟢"
  * *Título:* "O seu plantão, sob o seu controle."
  * *Subtítulo:* "Esqueça os grupos de WhatsApp e as planilhas confusas. O Escala Fácil coloca o seu calendário, trocas e aprovações direto no seu bolso."
* **Visual:** Texto centralizado. Abaixo, um mockup animado de um celular exibindo a tela inicial do PWA (Card de "Próximo Plantão").

### 3.3. Seção "Fricção vs. Fluidez" (Split)
* **Copy:** "Troque o caos pela precisão."
* **Visual:** Dois grandes blocos em *Grid 1x2*. 
  * **Esquerda (O Caos):** Estética acinzentada, exibindo balões de mensagens empilhados ("Alguém me cobre na sexta?").
  * **Direita (A Fluidez):** Interface limpa do Escala Fácil exibindo um card de status "Troca Aprovada" com ícone de check verde.

### 3.4. Tríade de Superpoderes (Bento Grid)
* **Estilo:** Cards estilo "Apple", com fundo branco, ícones grandes (Lucide) e tipografia forte.
* **Cards:**
  1. **Troca em 1 Clique:** "Solicite substituição e acompanhe a aprovação em tempo real." (Ícone: *ArrowRightLeft*)
  2. **Calendário no Bolso:** "Acesse sua escala de qualquer lugar. PWA nativo para iOS e Android." (Ícone: *CalendarDays*)
  3. **Transparência Total:** "Recibos digitais detalhados para cada alteração na sua escala." (Ícone: *FileCheck*)

### 3.5. Simulação Visual (Microinteração de Scroll)
* **Visual:** Um card grande centralizado simulando o fluxo do aplicativo.
* **Ação Narrativa:** À medida que o usuário faz o *scroll*, o status do card transita de "Em Análise" (Amber) para "Aprovado" (Emerald).

### 3.6. CTA Box & Footer
* **Visual CTA:** Container largo (`max-w-5xl`), fundo levemente esmeralda (`bg-emerald-50`), minimalista.
* **Copy:** "Sua escala está pronta para ser visualizada."
* **Ação:** Botão grande "Fazer Login na Plataforma".
* **Footer:** Minimalista, borda superior sutil, ícone de segurança (Lock) e direitos reservados.

## 4. Roteiro de Animações (GSAP + Lenis)
A página deve transpirar fluidez. O Lenis garantirá o *smooth scroll*, enquanto o GSAP orquestra os elementos.

* **Setup Geral:** Inicializar Lenis no `app.js` atrelado ao GSAP Ticker.
* **Hero Mockup (Float):** Animação contínua (infinita) no eixo Y usando `gsap.to(..., {y: 15, duration: 4, repeat: -1, yoyo: true, ease: "sine.inOut"})`.
* **Entrada de Textos (Blur Reveal):** Títulos do Hero começam com `filter: blur(10px)` e `opacity: 0`, animando para `blur(0)` e `opacity: 1` no carregamento da página.
* **Scroll Reveals (Geral):** Seções surgem suavemente de baixo para cima (`y: 40`, `opacity: 0` para `1`) com a ajuda do `ScrollTrigger`.
* **Stagger no Bento Grid:** Os cards da Tríade aparecem um após o outro (atraso de `0.1s` entre cada) quando o container pai entra na tela.
* **Performance Mobile:** Utilizar `gsap.matchMedia()` para desativar os *Blur Reveals* complexos em telas com largura inferior a 768px, mantendo apenas `fade-in` simples.