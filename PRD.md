# PRD (Product Requirements Document) - Sistema de Gestão de Escalas e Plantões

## 1. Visão Geral
Sistema interno para gerenciamento de escalas médicas e trocas de plantões em uma unidade de saúde. O objetivo é descentralizar a negociação de substituições, reduzindo a carga operacional da gerência, mantendo o controle estrito sobre a cobertura dos setores através de regras de aprovação e limites de troca.

## 2. Perfis e Permissões (Roles)
* **Manager (Gerente):** Acesso total ao painel de escalas. Pode criar turnos, alocar profissionais, aprovar ou rejeitar solicitações de troca e visualizar o status de cobertura de todos os setores.
* **Professional (Plantonista):** Visualiza a própria escala mensal, consulta em modo somente leitura o calendário completo de plantões e acompanha as solicitações de troca disponíveis para o seu setor/categoria. Pode solicitar trocas, aceitar pedidos de colegas e reportar ausências.

## 3. Requisitos Funcionais
* **Módulo de Autenticação:** Login seguro e recuperação de senha. Redirecionamento baseado no `role` do usuário.
* **Módulo de Cadastros Base:** CRUD de Categorias (ex: Médico, Enfermeiro) e Setores (ex: UTI, Pediatria).
* **Módulo de Escala:** Interface para a gerência criar plantões informando Data, Hora de Início, Hora de Fim, Setor, Categoria exigida e o Profissional alocado.
* **Módulo de Calendário:** Gerentes e profissionais podem navegar pelos meses e selecionar um dia para consultar todos os plantões e plantonistas. Somente a gerência recebe atalhos e permissões de manutenção da escala.
* **Módulo de Trocas (Swap):**
  * Profissionais podem selecionar um plantão futuro e abrir um "Pedido de Troca".
  * O sistema lista colegas elegíveis (mesmo `sector_id` e `category_id`).
  * O substituto deve "Aceitar" a solicitação.
  * Após o aceite, a solicitação vai para a gerência aprovar.
  * A aprovação transfere automaticamente o plantão para o substituto.
* **Módulo de Ausências:** Profissionais podem sinalizar ausência em um plantão, anexando um documento (ex: atestado). O plantão é marcado como "Descoberto" e um alerta é gerado para a gerência.
* **Módulo de Notificações:** O substituto é avisado ao receber uma solicitação; todos os gerentes são avisados após o aceite; solicitante e substituto são avisados sobre aceite, aprovação ou rejeição. Cada evento preserva um comprovante com o resumo da troca.

## 4. Regras de Negócio Críticas
* **Match de Perfil:** Uma troca só pode ser solicitada/aceita por profissionais que possuam a exata mesma `categoria` e atuem no mesmo `setor`.
* **Limite de Cota:** Um profissional só pode realizar **4 trocas por mês** (somando as vezes em que pediu para sair e as vezes em que aceitou cobrir). Apenas trocas com status `approved` contabilizam nesta cota mensal.
* **Bloqueio de Cota:** Se o profissional atingir o limite de 4 trocas no mês corrente, o sistema deve desabilitar o botão de solicitar troca e ocultar seu nome da lista de substitutos elegíveis daquele mês.

## 5. Requisitos Não-Funcionais
* **Stack:** Laravel 11+, Livewire 3, Tailwind CSS, Alpine.js, MySQL 8.0.
* **Interface:** Design responsivo, otimizado para uso em desktops (gerência) e mobile (plantonistas).
* **Auditoria:** O status das solicitações de troca deve armazenar carimbos de data/hora para cada mudança de fase (criado, aceito, aprovado).
