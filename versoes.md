# Histórico de versões — Escala Fácil

Este arquivo registra todas as versões funcionais da aplicação. O projeto usa versionamento semântico no formato `MAIOR.MENOR.CORREÇÃO`:

- **MAIOR:** mudança incompatível ou nova geração do produto;
- **MENOR:** funcionalidade nova compatível com a versão anterior;
- **CORREÇÃO:** ajuste compatível, sem nova funcionalidade.

## 1.3.1 — 26/09/2026

### Correção do acesso em produção

- Corrigido o login quando o Nginx do CloudPanel bloqueia a rota dinâmica do JavaScript do Livewire.
- Livewire passou a ser compilado dentro do bundle do Vite, eliminando a dependência da rota dinâmica `livewire.min.js`.
- O formulário de login agora usa `POST` como fallback, impedindo que e-mail e senha sejam enviados pela URL caso o JavaScript não carregue.
- Adicionado teste de regressão para validar o carregamento manual do Livewire e a proteção do formulário de acesso.

## 1.3.0 — 26/09/2026

### Notificações e comprovantes de troca

- Nova Central de Notificações disponível para gerentes e profissionais.
- Contador de notificações não lidas na sidebar, com atualização periódica.
- Aviso ao substituto quando uma solicitação de troca é criada.
- Aviso a todos os gerentes quando o substituto aceita e a troca aguarda aprovação.
- Avisos ao solicitante e ao substituto após aceite, aprovação ou rejeição.
- Comprovante imutável em cada evento com referência, plantão, setor, categoria, participantes, situação e responsável pela decisão.
- Comprovante imediato para o usuário que solicita, aceita, aprova ou rejeita a troca.
- Controles para marcar uma notificação ou todo o histórico como lido.
- Guia de produção `deploy.md` para Hostinger e CloudPanel, incluindo banco, SSL, cron, worker, atualização e backup.

## 1.2.0 — 26/09/2026

### Central de ajuda e novidades

- Novo módulo Ajuda com manual completo para gerentes e profissionais.
- Histórico de versões disponível dentro do sistema.
- Modal de novidades exibido uma vez por usuário em cada nova versão.
- Registro da última versão visualizada por usuário.
- README e processo de lançamento atualizados.
- Rodapé global com autoria da KL Tecnologia e versão do sistema.
- Publicação do código-fonte em repositório público no GitHub.
- Licença proprietária da KL Tecnologia destacada no repositório e nos metadados do projeto.

## 1.1.0 — 26/09/2026

### Calendário e gestão mais rápida

- Calendário mensal com todos os plantões e profissionais de cada dia.
- Sidebar recolhível no desktop com preferência persistida.
- Usuários reposicionado como último módulo da área gerencial.
- Formulários de Plantões, Usuários, Setores e Categorias em modais responsivos.
- Massa fictícia isolada com comandos para instalar, remover e recriar.

## 1.0.0 — 26/09/2026

### MVP operacional

- CRUD completo de usuários, setores e categorias.
- Gestão completa de plantões pela gerência.
- Interface totalmente em português do Brasil.
- Experiência mobile-first com cartões no celular e tabelas no desktop.
- Sidebar autenticada com ícones.
- Proteção do histórico operacional e das compatibilidades de escala.

## 0.4.0 — 26/09/2026

### Painéis e qualidade

- Dashboard gerencial com indicadores reais de cobertura.
- Alertas de plantões descobertos e trocas pendentes.
- Cartões e badges semânticos para os estados operacionais.
- Testes do limite mensal de trocas e da compatibilidade entre profissionais.

## 0.3.0 — 25/09/2026

### Fluxos de plantão

- Gestão de escalas e agenda mensal do profissional.
- Solicitação, aceite e aprovação gerencial de trocas.
- Transferência automática do plantão após aprovação.
- Registro de ausência justificada com anexo privado.

## 0.2.0 — 25/09/2026

### Domínio e acesso

- Models Eloquent, casts e relacionamentos do domínio.
- Autenticação e recuperação de senha.
- Redirecionamento por perfil.
- Middleware e Policies para gerentes e profissionais.

## 0.1.0 — 25/09/2026

### Fundação do Escala Fácil

- Projeto criado com Laravel, Livewire, Tailwind CSS e Breeze.
- Banco MySQL com InnoDB.
- Migrations de categorias, setores, usuários, plantões, trocas e ausências.
- Seeders iniciais para desenvolvimento.

## Processo para uma nova versão

1. Escolha o próximo número conforme o versionamento semântico.
2. Atualize `APP_VERSION` no `.env.example` e o valor padrão em `config/version.php`.
3. Adicione o lançamento no início de `config/version.php` e deste arquivo.
4. Descreva mudanças concretas, impactos para o usuário e eventuais instruções de migração.
5. Execute migrations, testes, Pint, build e validação visual.
6. Ao entrar, cada usuário verá automaticamente o modal da nova versão até confirmar a leitura.
