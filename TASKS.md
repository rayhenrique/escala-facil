# TASKS - Plano de Execução Sequencial

- [x] **Fase 1: Setup e Banco de Dados**
  - [x] Inicializar projeto Laravel e configurar `.env` para MySQL.
  - [x] Instalar Livewire e Tailwind CSS.
  - [x] Criar Migrations estritas (ordem: categories, sectors, users, shifts, shift_swaps, absences) com chaves estrangeiras.
  - [x] Criar Seeders básicos (1 Gerente, 2 Categorias, 2 Setores, 5 Plantonistas para testes).

- [x] **Fase 2: Eloquent, Models e Casts**
  - [x] Configurar `$fillable`, `$casts` e relacionamentos (hasMany, belongsTo) em todos os Models.
  - [x] No model `User`, criar método `hasReachedSwapLimit($month, $year)` que conta ocorrências na tabela `shift_swaps` como requester ou substitute com status 'approved'.

- [x] **Fase 3: Autenticação e Autorização**
  - [x] Configurar autenticação base (Laravel Breeze ou custom auth).
  - [x] Criar Middleware `EnsureUserIsManager` para proteger rotas da gerência.
  - [x] Criar Policies para `ShiftSwap` (ex: um plantonista só pode aceitar troca se ele for o `substitute_id` e não tiver batido a cota).

- [x] **Fase 4: Core Logic e Controllers/Livewire Components**
  - [x] Criar componente Livewire para Gestão de Escalas (CRUD) visível ao Manager.
  - [x] Criar componente Livewire `Minha Escala` para o Professional visualizar seus plantões.
  - [x] Implementar fluxo de solicitação de troca:
    - [x] FormRequest validando mesma categoria/setor e limite de cota.
    - [x] Componente listando colegas elegíveis.
  - [x] Implementar fluxo de aceite do substituto (muda status para `pending_manager`).
  - [x] Implementar painel do Manager para aprovar trocas (muda status para `approved` e faz o UPDATE do `user_id` na tabela `shifts`).
  - [x] Implementar fluxo de ausência justificada com upload de anexo.

- [x] **Fase 5: Interface e UI**
  - [x] Refinar painéis usando Tailwind CSS (Cards para plantões, Badges coloridas para status: Pendente/Aprovado/Descoberto).
  - [x] Criar dashboard de alertas para o Manager (Plantões descobertos e Trocas aguardando aprovação).

- [x] **Fase 6: Testes**
  - [x] Escrever testes (Feature) para garantir que a cota de 4 trocas mensais bloqueia a 5ª tentativa rigorosamente.
  - [x] Testar bloqueio de troca entre profissionais de setores/categorias diferentes.

- [x] **Fase 7: Cadastros, localização e experiência responsiva**
  - [x] Criar módulo de Usuários com CRUD completo, busca, filtro e proteção do histórico operacional.
  - [x] Criar módulo de Setores com CRUD completo e bloqueio de exclusão quando houver vínculos.
  - [x] Criar módulo de Categorias com CRUD completo e bloqueio de exclusão quando houver vínculos.
  - [x] Traduzir validações, paginação, e-mail de senha e páginas de erro para português do Brasil.
  - [x] Adotar composição mobile-first em autenticação, painéis, formulários, listas e confirmações.
  - [x] Substituir a barra superior por sidebar com ícones, fixa no desktop e em formato de gaveta no celular.
  - [x] Atualizar README, DESIGN e UX-CONTRACT com o estado atual do sistema.
  - [x] Validar os módulos com testes automatizados, build, padrão de código e inspeção no navegador.

- [x] **Fase 8: Calendário, navegação e revisão dos CRUDs**
  - [x] Criar calendário mensal da gerência com contagem de plantões e sinalização de descobertos.
  - [x] Listar todos os plantões e profissionais ao selecionar um dia do calendário.
  - [x] Manter Usuários como último módulo do menu gerencial.
  - [x] Adicionar toggle persistente para recolher e expandir a sidebar no desktop.
  - [x] Revisar os CRUDs de Plantões, Usuários, Setores e Categorias e abrir seus formulários por botão em modal responsivo.
  - [x] Validar autorização, fluxos dos modais, responsividade, build e console do navegador.

- [x] **Fase 9: Massa de dados para demonstração**
  - [x] Criar lote fictício com categorias, setores, profissionais, plantões, trocas e ausências.
  - [x] Rastrear os IDs criados em manifesto próprio, sem marcar ou alterar os registros reais.
  - [x] Disponibilizar comandos para instalar, remover e recriar a carga de demonstração.
  - [x] Testar instalação duplicada, recriação e remoção preservando dados preexistentes.
  - [x] Instalar a carga fictícia no banco local para validação imediata do sistema.

- [x] **Fase 10: Ajuda e controle de versões**
  - [x] Adotar versionamento semântico e registrar todos os lançamentos em `versoes.md`.
  - [x] Criar registro estruturado da versão atual e das notas de cada lançamento.
  - [x] Criar módulo Ajuda com manual completo para gerentes e profissionais.
  - [x] Exibir o histórico de versões dentro do módulo Ajuda.
  - [x] Registrar a última versão visualizada por usuário.
  - [x] Exibir modal de novidades uma vez no primeiro acesso após cada nova versão.
  - [x] Atualizar README, schema, contrato de experiência e sistema visual.
  - [x] Validar autorização, persistência da confirmação, responsividade, testes e build.

- [x] **Fase 11: Notificações e comprovantes de troca**
  - [x] Criar notificações persistentes em banco e uma Central de Notificações para os dois perfis.
  - [x] Notificar o substituto quando receber uma solicitação de troca.
  - [x] Notificar todos os gerentes quando uma troca aceita aguardar aprovação.
  - [x] Notificar solicitante e substituto após aceite, aprovação ou rejeição.
  - [x] Gerar um comprovante imutável a cada movimentação da troca.
  - [x] Exibir o comprovante imediatamente para quem executou a ação e mantê-lo no histórico dos destinatários.
  - [x] Adicionar contador de não lidas e controles de leitura na sidebar e na central.
  - [x] Atualizar README, manual, schema, contrato de experiência e versão do sistema.
  - [x] Criar guia de deploy para a VPS Hostinger com CloudPanel, PHP 8.5 e Laravel 13.
  - [x] Validar privacidade, destinatários, comprovantes, responsividade, testes e build.

- [x] **Fase 12: Robustez operacional e produção**
  - [x] Criar comando seguro e interativo para o primeiro gerente.
  - [x] Impedir deploy parcial quando o Git local estiver alterado ou divergente do GitHub.
  - [x] Tornar mudanças de troca e notificações atômicas e reforçar bloqueios concorrentes.
  - [x] Normalizar filtros mensais e proteger a massa fictícia e os seeders em produção.
  - [x] Aplicar fallback `POST` e CSRF em todos os formulários Livewire.
  - [x] Validar a suíte completa, build de produção, dependências e script de deploy.

- [x] **Fase 13: Calendário compartilhado**
  - [x] Disponibilizar o calendário mensal no menu do profissional.
  - [x] Permitir consulta de todos os plantões e plantonistas por dia.
  - [x] Manter atalhos e ações de gestão exclusivos do gerente.
  - [x] Atualizar manual, contratos, escopo e controle de versão.
  - [x] Validar permissões, responsividade, testes e build de produção.

- [x] **Fase 14: Manual operacional detalhado**
  - [x] Organizar a implantação inicial na ordem de categorias, setores, usuários, plantões, calendário e operação diária.
  - [x] Detalhar os procedimentos e bloqueios dos módulos gerenciais.
  - [x] Detalhar as rotinas do profissional, trocas, ausências, notificações e comprovantes.
  - [x] Adicionar referências de status, permissões, segurança e solução de problemas.
  - [x] Atualizar controle de versão, documentação e testes do manual.

- [x] **Fase 15: Aplicativo PWA**
  - [x] Criar manifesto instalável com identidade, ícones e atalhos do sistema.
  - [x] Registrar service worker com atualização de recursos estáticos e tela offline própria.
  - [x] Evitar o cache de páginas autenticadas e dados operacionais.
  - [x] Disponibilizar a ação de instalação quando suportada pelo navegador.
  - [x] Documentar a instalação no Android, iOS, iPadOS e Windows.
  - [x] Validar manifesto, service worker, responsividade, testes e build de produção.

- [x] **Fase 16: Confirmações e feedback visual**
  - [x] Criar um modal acessível e reutilizável para confirmar ações importantes.
  - [x] Aplicar confirmação às exclusões dos CRUDs e às decisões de troca e ausência.
  - [x] Confirmar a leitura em massa sem apagar o histórico de notificações.
  - [x] Padronizar mensagens de sucesso e erro em avisos flutuantes responsivos.
  - [x] Atualizar manual, contrato de experiência, versão e testes automatizados.
