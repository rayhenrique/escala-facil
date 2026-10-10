# TASKS - Plano de Execução Sequencial

- [x] **Análise contextual de trocas (1.26.0)**
  - [x] Compartilhar análise e confirmação entre dashboard e Trocas, com contrapartida e alertas para ambos os profissionais.
  - [x] Reutilizar Action, Policies, comprovantes e notificações; exigir justificativa no servidor e confirmação explícita.
  - [x] Revalidar atribuições, status, permissão, alertas e cota; bloquear informação desatualizada e decisões concorrentes.
  - [x] Verificar aprovação, rejeição, transação, processos concorrentes, teclado, celular, acessibilidade, Pint e build.

- [x] **Navegação contextual (1.25.0)**
  - [x] Validar e autorizar IDs, abrir registros exatos e destacar o destino com foco acessível.
  - [x] Preservar origem, filtros e seleção entre painel, calendário, escala, trocas e relatórios.
  - [x] Implementar Voltar interno, com retorno em refresh e links compartilhados, sem mudar aprovação.
  - [x] Validar regressões, permissões, navegador, PWA, Pint e build.

- [x] **Prioridades da gerência (1.24.0)**
  - [x] Reunir cobertura e trocas em fila única, sem repetir o pedido mais antigo.
  - [x] Ordenar risco, início e antiguidade; paginar com relacionamentos carregados em lote.
  - [x] Distinguir dias de calendário das 72 horas corridas; tornar indicadores e ações contextuais filtrados.
  - [x] Validar filtros, fronteiras, contagens, autorização, responsividade, acessibilidade, Pint e build.

- [x] **Acesso pelo celular e instalação (1.23.1)**
  - [x] Preservar duas colunas institucionais e simplificar composição móvel sem decoração.
  - [x] Compartilhar campos de senha, carregamento e erros; corrigir autofill e foco de validação.
  - [x] Tornar instalação opcional e discreta, com orientação iOS e recuperação de falha.
  - [x] Verificar sessão, recuperação, interface, acessibilidade, Pint e build.

- [x] **Clareza da landing (1.23.0)**
  - [x] Preservar identidade e revisar hierarquia, ritmo, comparação, recursos e acesso institucional.
  - [x] Usar componentes reais com dados ilustrativos e substituir promessas sem evidência.
  - [x] Manter visibilidade progressiva, navegação por teclado e movimento reduzido; atualizar contratos.
  - [x] Validar regressões, navegador desktop/mobile, contraste, Pint e build.

- [x] **Consistência de versão (1.22.1)**
  - [x] Auditar APP_VERSION, registro, documentação, deploy, caches e PWA.
  - [x] Usar o código instalado como fonte canônica e preservar .env e dados de usuários.
  - [x] Corrigir release:prepare, versão de destino do deploy e conferir metadados servidos.
  - [x] Cobrir divergências, cache antigo e telas por regressões; documentar conferência em produção.

- [x] **Horários e duração dos plantões (1.22.0)**
  - [x] Auditar cadastro, XLSX, calendário, dashboard, relatórios, trocas, cobertura e PDFs.
  - [x] Acrescentar término explícito para 24h, preservando dados antigos e documentando ambiguidades institucionais.
  - [x] Centralizar períodos, rótulos e duração; corrigir conflitos e dobras entre datas e meses.
  - [x] Cobrir as fronteiras por testes, incluindo migration sem reescrita e ida/volta do XLSX.

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

- [x] **Fase 17: Plantões noturnos, robustez temporal e controle de versão (1.6.1)**
  - [x] Permitir o cadastro de plantões noturnos que atravessam a meia-noite (ex.: 19h às 07h).
  - [x] Eliminar a fragilidade temporal nos testes de limites de troca executados no encerramento do mês.
  - [x] Contextualizar o alerta de cota mensal quando o profissional consulta períodos diferentes do mês corrente.
  - [x] Limpar variáveis sensíveis de memória após hashing no comando interativo de criação de gerente.
  - [x] Incrementar versão semântica para 1.6.1 e atualizar changelog em `versoes.md`, `config/version.php`, PWA cache e documentação.

- [x] **Fase 18: Filtros do calendário por setor e categoria (1.7.0)**
  - [x] Adicionar filtros de setor e categoria ao calendário mensal compartilhado.
  - [x] Aplicar os filtros às contagens dos dias e à relação de plantões do dia selecionado.
  - [x] Oferecer limpeza dos filtros e mensagem específica quando nenhum plantão corresponde à seleção.
  - [x] Atualizar manual, contrato de experiência, versão e testes automatizados.

- [x] **Fase 19: Importação e exportação de plantões em XLSX (1.8.0)**
  - [x] Disponibilizar modelo padrão com setores e categorias cadastrados para referência exata.
  - [x] Importar plantões com validação por linha, cancelamento total em caso de erro e duplicatas ignoradas.
  - [x] Exportar os plantões do mês exibido para edição e backup.
  - [x] Atualizar manual, contrato de experiência, versão e testes automatizados.

- [x] **Fase 20: Módulo Relatórios com exportação em PDF (1.9.0)**
  - [x] Criar item Relatórios no menu da gerência com resumo de cobertura do mês.
  - [x] Filtrar todos os números por mês, setor e categoria.
  - [x] Exibir plantões por setor, categoria, profissional e trocas criadas no mês.
  - [x] Exportar o relatório em PDF com os mesmos filtros da tela.
  - [x] Atualizar manual, contrato de experiência, versão e testes automatizados.

- [x] **Fase 21: Cobertura de 24h no calendário (1.10.0)**
  - [x] Calcular a cobertura diária pela união dos horários com profissional, incluindo madrugadas de noturnos.
  - [x] Marcar dias com 24h cobertas com ✓, legenda e descrição acessível.
  - [x] Atualizar manual, contrato de experiência, versão e testes automatizados.

- [x] **Fase 22: Dobras, desfazer troca e PDF para afixar (1.11.0)**
  - [x] Alertar dobra no aceite do substituto e sinalizar na fila de aprovação gerencial.
  - [x] Permitir desfazer trocas aprovadas futuras, com devolução, comprovante e liberação da cota.
  - [x] Disponibilizar PDF para afixar com grade mensal, legenda, trocas e assinaturas.
  - [x] Atualizar manual, contrato de experiência, versão e testes automatizados.

- [x] **Fase 23: Relatórios compartilhados e manual detalhado (1.12.0)**
  - [x] Disponibilizar relatórios e PDFs ao profissional, mantendo rotas gerenciais exclusivas.
  - [x] Detalhar o passo a passo do fechamento do mês e da consulta de relatórios no manual.
  - [x] Atualizar permissões, contrato de experiência, versão e testes automatizados.

- [x] **Fase 24: Notificações PWA, comprovante detalhado e trocas destacadas (1.13.0)**
  - [x] Integrar contagem de notificações não lidas ao ícone do PWA instalado (App Badging API) e suporte a notificações do dispositivo.
  - [x] Exibir visão geral dos dois plantões na aprovação gerencial (troca mútua) e gerar comprovante detalhado com horários e impressão.
  - [x] Destacar dias e plantões com troca realizada no calendário mensal (tom laranja, símbolo e legenda).
  - [x] Exigir justificativa no cancelamento/rejeição de troca pelo gestor e enviar comprovante aos dois profissionais.
  - [x] Atualizar documentação, versões e testes automatizados.

- [x] **Fase 25: Landing page cinematográfica, privacidade de trocas e tooltips detalhadas (1.14.0)**
  - [x] Implementar landing page mobile-first de alta conversão com Lenis, GSAP, ScrollTrigger e Blade.
  - [x] Restringir a visualização de trocas nos relatórios e agenda para que profissionais vejam apenas seus próprios registros.
  - [x] Exibir tooltip informativa no hover do selo de troca identificando solicitante, substituto e tipo de troca.
  - [x] Exibir participantes da troca ao passar o mouse nas células de dias com troca no calendário mensal.
  - [x] Atualizar controle de versão, documentações e testes automatizados.

- [x] **Fase 26: Isolamento de setor para profissionais e monogramas no calendário (1.15.0)**
  - [x] Confinar visualização da escala no calendário e relatórios estritamente ao setor do profissional.
  - [x] Bloquear e desabilitar seletor de setor na interface do profissional com o nome da sua unidade.
  - [x] Proteger controllers de exportação de PDF e serviços contra forja de parâmetros de setor.
  - [x] Exibir monogramas de duas letras com as iniciais dos plantonistas nas células do calendário mensal.
  - [x] Atualizar documentações, service worker cache, versões e testes automatizados.



