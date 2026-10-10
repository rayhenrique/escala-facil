# Escala Fácil

> **Software proprietário — © 2026 KL Tecnologia. Todos os direitos reservados.** O código está publicamente visível para avaliação, mas este repositório não é open source e não concede permissão de uso, cópia, modificação ou distribuição. Consulte [`LICENSE`](LICENSE).

Sistema interno para gestão de escalas médicas, plantões, trocas e ausências em unidades de saúde. A aplicação centraliza a operação da gerência, permite que profissionais acompanhem a própria agenda e aplica as regras de compatibilidade e limite mensal em todas as decisões de troca.

**Versão atual:** `1.15.0` — consulte o histórico completo em [`versoes.md`](versoes.md).

Desenvolvido por [KL Tecnologia](https://kltecnologia.com).

## Estado do projeto

O MVP está funcional e inclui:

- autenticação, recuperação de senha e redirecionamento por perfil;
- painel gerencial com indicadores reais de cobertura e pendências;
- cadastro completo de usuários, setores e categorias;
- criação, edição, consulta e exclusão de plantões, com modelo XLSX, importação validada e exportação mensal;
- calendário mensal compartilhado com todos os plantões e profissionais de cada dia, com filtros por setor e categoria e marcador de dias com 24h cobertas;
- agenda mensal do profissional;
- solicitação, aceite e aprovação gerencial de trocas, com alerta de dobras e desfazer troca aprovada;
- notificações persistentes para substitutos, solicitantes e gerentes em cada etapa da troca;
- comprovantes de solicitação, aceite e decisão com um retrato dos dados da movimentação;
- modais de revisão para exclusões, trocas, ausências e demais ações importantes;
- avisos flutuantes e acessíveis para mensagens de sucesso e erro;
- controle de quatro trocas aprovadas por profissional a cada mês;
- registro de ausência justificada com anexo privado;
- central de ajuda com ordem inicial de implantação, rotinas detalhadas por perfil, solução de problemas e histórico de versões;
- aviso individual das novidades no primeiro acesso após cada atualização;
- instalação como PWA no Android, iOS, iPadOS e Windows, com ícones próprios e tela offline segura;
- interface totalmente em português do Brasil;
- experiência mobile-first com cartões no celular e tabelas em telas maiores;
- testes automatizados para permissões, fluxos e regras críticas.

O progresso detalhado fica em [`TASKS.md`](TASKS.md).

## Perfis e permissões

### Gerente

O gerente pode:

- acompanhar cobertura, plantões descobertos e trocas pendentes;
- consultar relatórios mensais de cobertura, trocas, ausências e desempenho por profissional, com exportação em PDF e escala para afixar;
- consultar o calendário mensal e abrir a relação completa de plantonistas por dia, filtrando por setor e categoria;
- cadastrar e manter usuários, setores e categorias;
- criar, editar, alocar e excluir plantões;
- aprovar ou rejeitar trocas aceitas pelo substituto;
- receber uma notificação quando uma troca aceita aguardar decisão;
- consultar alertas gerados por ausências.

### Profissional

O profissional pode:

- consultar os próprios plantões por mês;
- consultar o calendário mensal completo e os plantonistas escalados em cada dia, com filtros por setor e categoria;
- consultar relatórios mensais de cobertura, trocas e ausências, com exportação em PDF;
- solicitar troca com um colega elegível;
- aceitar pedidos em que foi indicado como substituto;
- receber notificações ao ser indicado e após aceite ou decisão da gerência;
- informar ausência e anexar um comprovante.

Os dois perfis podem abrir **Notificações** para acompanhar movimentações e comprovantes e **Ajuda e versões** para consultar o manual, as regras operacionais e o histórico de lançamentos.

O cadastro público permanece desativado. Novas contas são criadas pela gerência no módulo **Usuários**.

## Regras de negócio

- Uma troca só pode envolver profissionais da mesma categoria e do mesmo setor do plantão.
- Apenas o profissional indicado como substituto pode aceitar a solicitação.
- O fluxo segue `Aguardando profissional` → `Aguardando gerência` → `Aprovada`, `Rejeitada` ou `Desfeita`.
- Cada profissional pode participar de no máximo **quatro trocas aprovadas por mês**, somando solicitações e substituições.
- A aprovação gerencial transfere o plantão ao substituto; o aceite que emenda turnos exibe alerta de dobra para decisão consciente.
- A gerência pode desfazer trocas aprovadas futuras, devolvendo o plantão ao solicitante e liberando a cota mensal.
- Uma solicitação avisa o substituto; o aceite avisa o solicitante, o substituto e todos os gerentes; a decisão avisa os dois profissionais.
- Cada etapa armazena um comprovante com referência, data, horário, setor, categoria e participantes conforme estavam no momento da movimentação.
- Uma ausência justificada deixa o plantão descoberto e gera um alerta gerencial.
- Usuários, setores e categorias com histórico ou vínculos operacionais não podem ser excluídos.
- O gerente não pode excluir a própria conta nem retirar dela o perfil gerencial.
- Alterações de perfil, setor ou categoria são bloqueadas quando deixariam plantões futuros incompatíveis.

## Interface, idioma e responsividade

O sistema usa `pt-BR` em textos, validações, paginação, páginas de erro e e-mails de recuperação de senha. Datas são exibidas no padrão brasileiro, horários usam o formato de 24 horas e o fuso padrão é `America/Maceio`.

A interface parte do celular como composição principal:

- formulários de criação e edição dos CRUDs abrem em modais responsivos, com uma coluna e botões na largura disponível no celular;
- registros aparecem em cartões com ações visíveis abaixo de 768 px;
- tabelas são reservadas para telas médias e grandes;
- métricas e grupos de ações se empilham em telas estreitas;
- a navegação usa uma sidebar recolhível e persistente no desktop e uma gaveta com ícones no celular;
- modais de nova versão comunicam mudanças uma vez por usuário, com acesso direto ao manual;
- ações importantes exibem o registro e suas consequências em um modal antes da confirmação;
- mensagens de resultado aparecem como avisos flutuantes, sem deslocar o conteúdo da página;
- a sidebar mostra o número de notificações não lidas e a central organiza os comprovantes em cartões responsivos;
- campos de formulário têm 48 px de altura e estados de foco perceptíveis.

As decisões visuais estão em [`DESIGN.md`](DESIGN.md) e os comportamentos compartilhados em [`UX-CONTRACT.md`](UX-CONTRACT.md).

## Aplicativo PWA

O Escala Fácil pode ser instalado diretamente pelo navegador, sem uma loja de aplicativos. O manifesto define nome, cores, ícones de 192 e 512 px, modo independente e atalhos; o service worker mantém somente recursos estáticos e oferece uma página própria quando a conexão falha.

Por segurança, respostas HTML autenticadas, escalas e dados de usuários não são armazenados para uso offline. A conexão continua obrigatória para entrar, consultar informações atuais e realizar operações. Em produção, a instalação exige HTTPS válido.

- **Android:** no Chrome, use **Instalar aplicativo** no sistema ou abra o menu de três pontos e escolha **Instalar aplicativo** ou **Instalar e criar atalho**.
- **iPhone/iPad:** no Safari, abra **Compartilhar**, escolha **Adicionar à Tela de Início**, mantenha **Abrir como App** ativado quando essa opção estiver disponível e confirme.
- **Windows:** no Edge, use o ícone de instalação na barra de endereços ou abra **Mais ferramentas → Aplicativos → Instalar este site como um aplicativo**.

O manual completo de instalação e remoção fica no módulo **Ajuda e versões**. Os arquivos principais são [`manifest.webmanifest`](public/manifest.webmanifest), [`sw.js`](public/sw.js) e [`offline.html`](public/offline.html).

## Tecnologias

| Tecnologia | Versão validada |
| --- | --- |
| PHP | 8.5.0 |
| Laravel | 13.33.0 |
| Livewire | 4.4.6 |
| Laravel Breeze | 2.4.2 |
| Tailwind CSS | 4.3.3 |
| Alpine.js | fornecido pelo Livewire |
| Node.js | 24.19.0 |
| Vite | 7.3.6 |
| MySQL | 8.0 ou superior, com InnoDB |

O Livewire é incorporado ao bundle do Vite para funcionar de forma consistente atrás do Nginx do CloudPanel. Depois de atualizar dependências PHP, execute novamente `npm run build` antes de publicar a aplicação.

Em 27/09/2026, o Composer confirmou `13.33.0` como a versão estável mais recente disponível do Laravel. A restrição `laravel/framework: ^13.0` permite receber atualizações compatíveis da série 13.

## Requisitos locais

- PHP 8.3 ou superior com as extensões exigidas pelo Laravel;
- Composer 2;
- Node.js e npm;
- MySQL 8.0 ou superior;
- banco configurado para usar InnoDB.

O ambiente de desenvolvimento foi validado no Windows com WampServer e PowerShell.

## Instalação

No PowerShell, acesse a pasta do projeto e instale as dependências:

```powershell
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
```

Crie um banco MySQL chamado `escala_facil` e ajuste o `.env`:

```dotenv
APP_NAME="Escala Fácil"
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=America/Maceio
APP_VERSION=1.19.1
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR
APP_FAKER_LOCALE=pt_BR

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=escala_facil
DB_USERNAME=root
DB_PASSWORD=
DB_ENGINE=InnoDB
```

Crie as tabelas, carregue os dados de desenvolvimento e gere os arquivos de produção:

```powershell
php artisan migrate --seed
npm run build
```

Para iniciar servidor, fila, registros e Vite em conjunto:

```powershell
composer run dev
```

Para iniciar somente o servidor HTTP:

```powershell
php artisan serve
```

## Criar o primeiro gerente

Depois das migrations, crie a conta administrativa com um comando interativo. A senha é solicitada de forma oculta, confirmada e não fica gravada no código nem no histórico do terminal:

```powershell
php artisan user:create-manager --name="Nome do responsável" --email="responsavel@exemplo.com"
```

A senha deve ter pelo menos 12 caracteres, letras maiúsculas e minúsculas, número e símbolo. Contas adicionais podem ser cadastradas pelo módulo **Usuários**.

## Usuários de desenvolvimento

Todos os usuários criados pelo seeder usam a senha `password`.

| Perfil | Nome | E-mail |
| --- | --- | --- |
| Gerente | Gerente de Plantões | `gerente@escalafacil.test` |
| Profissional | Ana Silva | `ana@escalafacil.test` |
| Profissional | Bruno Costa | `bruno@escalafacil.test` |
| Profissional | Carla Oliveira | `carla@escalafacil.test` |
| Profissional | Diego Santos | `diego@escalafacil.test` |
| Profissional | Elisa Lima | `elisa@escalafacil.test` |

Essas credenciais são exclusivas do ambiente local.

## Dados de demonstração

O projeto possui uma carga fictícia isolada para testar painéis, calendário, filtros e fluxos operacionais. Ela adiciona 5 categorias, 5 setores, 20 profissionais, 102 plantões, 12 trocas e 6 ausências. Os nomes e cadastros recebem a marca `(Demo)`, e os profissionais usam e-mails no padrão `demo.profissional.01@escalafacil.test` até `demo.profissional.20@escalafacil.test`, todos com a senha `password`.

Instale a carga:

```powershell
php artisan demo:data seed
```

Remova somente o lote fictício e os registros de teste vinculados a ele:

```powershell
php artisan demo:data clear
```

Para apagar e gerar uma carga nova em relação à data atual:

```powershell
php artisan demo:data refresh
```

O comando usa `demo_data_batches` como manifesto dos IDs criados e preserva os registros anteriores à instalação. Em produção, essas operações exigem a opção `--force`. O `DatabaseSeeder` padrão não instala a carga fictícia automaticamente.

## Versionamento e lançamentos

O Escala Fácil usa versionamento semântico `MAIOR.MENOR.CORREÇÃO`. A versão executada fica em `APP_VERSION`, com valor padrão em [`config/version.php`](config/version.php). O mesmo arquivo fornece ao sistema os títulos e destaques mostrados no módulo Ajuda e no modal de novidades.

Para preparar uma nova versão, use o comando centralizado (não edite os arquivos de versão manualmente):

```powershell
php artisan release:prepare X.Y.Z --title="Título da versão" --summary="Resumo das mudanças" --highlight="Destaque 1" --highlight="Destaque 2"
```

O comando valida o SemVer, impede regressão e duplicação e sincroniza `config/version.php`, `.env.example`, `README.md`, `deploy.md`, [`versoes.md`](versoes.md) e `public/sw.js` (cache do PWA). O arquivo `.env` nunca é alterado. Depois:

1. revise o histórico gerado;
2. execute migrations, testes, Pint, build e inspeção visual.

Cada usuário possui `last_seen_version`. Quando esse valor difere da versão atual, o sistema apresenta o modal de novidades no primeiro acesso e só grava a confirmação quando o usuário escolhe continuar ou abrir o manual.

### Fluxo de atualização do repositório

Antes de iniciar uma alteração, sincronize a branch principal:

```powershell
git pull --rebase origin main
```

Mudanças significativas devem ser versionadas com `php artisan release:prepare` (que atualiza `config/version.php`, `.env.example`, `versoes.md` e as notas exibidas no sistema). Depois das validações:

```powershell
git add .
git commit -m "tipo: descrição objetiva da versão"
git push origin main
```

O repositório público oficial está em [github.com/rayhenrique/escala-facil](https://github.com/rayhenrique/escala-facil).

Na VPS, use o deploy validado documentado em [`deploy.md`](deploy.md):

```bash
bash scripts/deploy-production.sh
```

O script valida a situação do Git antes de colocar o sistema em manutenção, aceita somente atualização por fast-forward e testa a página de login após a publicação.

## Integração contínua (CI)

O workflow [`ci.yml`](.github/workflows/ci.yml) executa em todo `push` e `pull request`, sem nenhuma etapa de deploy:

- `composer validate --strict` e `composer audit --locked`;
- `php artisan test` na matriz `sqlite` + `mysql` (serviço `mysql:8.0`, banco `escala_facil_testing`, InnoDB);
- `vendor/bin/pint --test`;
- `npm ci` e `npm run build`.

Versões fixadas: PHP `8.5` (mesma do deploy e do ambiente validado), Node.js `24` e MySQL `8.0`. Dependências usam cache do `setup-php` (Composer) e do `setup-node` (npm). Os testes usam exclusivamente o [`.env.testing`](.env.testing) e a configuração do [`phpunit.xml`](phpunit.xml) — sem credenciais reais; o job MySQL sobrescreve apenas variáveis de ambiente do passo, com senha fictícia local ao serviço.

### Proteção da branch `main`

Para exigir o CI aprovado antes de qualquer merge na `main`:

1. abra o repositório no GitHub em **Settings → Branches → Add classic branch protection rule**;
2. em **Branch name pattern**, informe `main`;
3. marque **Require a pull request before merging** (recomendado: exigir ao menos 1 aprovação);
4. marque **Require status checks before merging** e selecione o check do workflow **CI** (aguarde a primeira execução para o nome aparecer na lista);
5. marque **Require branches to be up to date before merging**;
6. opcional, marque **Do not allow bypassing the above settings** para administradores;
7. clique em **Create** (ou **Save changes**).

A partir disso, pushes diretos e merges com CI vermelho ou pendente são bloqueados; o deploy continua manual via `scripts/deploy-production.sh`.

## Observabilidade e suporte

O monitoramento externo é opcional e configurado só por ambiente. Com `SENTRY_DSN` vazio, nada sai da máquina e o sistema funciona normalmente apenas com logs locais:

```dotenv
SENTRY_DSN=
SENTRY_ENVIRONMENT=
SENTRY_TRACES_SAMPLE_RATE=0
SENTRY_SEND_DEFAULT_PII=false
```

- **Backend:** exceções são registradas com versão, rota, perfil, horário e identificador de erro (`error_id`), e enviadas ao Sentry somente com DSN configurado. Senhas, tokens, segredos, cookies, CPF/CNPJ e outros dados sensíveis são removidos antes de qualquer envio (`App\Support\ErrorReporting`), e o `send_default_pii` permanece desativado.
- **JavaScript:** erros não tratados e promessas rejeitadas são enviados de forma limitada (máximo 5 por carregamento, só da própria origem, só o caminho da página) para `POST /support/client-error` (autenticado, com limite de taxa). A captura nunca quebra a aplicação.
- **Relatar problema:** na página **Ajuda e versões**, qualquer usuário autenticado descreve o problema (módulo, descrição, comportamento esperado e anexo opcional em PDF/JPG/PNG de até 5 MB) e recebe um número de protocolo (`SUP-AAAAMMDD-XXXX`). Os relatos ficam no banco com anexo privado e acesso restrito à gerência, que os acompanha no item **Relatos** da sidebar.
- Usuários nunca veem dados técnicos sensíveis: páginas de erro são genéricas e o protocolo é o único código exibido.

## Licença e propriedade intelectual

O Escala Fácil e seu código-fonte são propriedade exclusiva da **KL Tecnologia**. A publicação do repositório não concede licença de uso ou exploração. Qualquer reprodução, modificação, distribuição, sublicenciamento ou uso comercial depende de autorização prévia e expressa da KL Tecnologia. Consulte os termos completos em [`LICENSE`](LICENSE).

## Rotas principais

| Área | Endereço | Perfil |
| --- | --- | --- |
| Acesso | `/login` | visitante |
| Painel gerencial | `/manager/dashboard` | gerente |
| Calendário mensal | `/manager/calendar` | gerente |
| Escalas | `/manager/shifts` | gerente |
| Trocas | `/manager/swaps` | gerente |
| Relatórios | `/manager/reports` | gerente |
| Setores | `/manager/sectors` | gerente |
| Categorias | `/manager/categories` | gerente |
| Usuários | `/manager/users` | gerente |
| Minha escala | `/professional/dashboard` | profissional |
| Calendário mensal | `/professional/calendar` | profissional |
| Relatórios | `/professional/reports` | profissional |
| Notificações e comprovantes | `/notifications` | gerente e profissional |
| Ajuda e versões | `/help` | gerente e profissional |

Todas as rotas gerenciais exigem autenticação e passam pelo middleware `EnsureUserIsManager`. As mutações também são protegidas por Policies.

## Estrutura de dados

O domínio principal usa estas tabelas:

- `categories`: categorias profissionais;
- `sectors`: setores da unidade;
- `users`: gerentes e profissionais vinculados a categoria e setor;
- `shifts`: plantões, horários, alocação e cobertura;
- `shift_swaps`: solicitações, participantes e decisão da troca;
- `absences`: ausências justificadas e anexos;
- `notifications`: avisos persistentes, leitura e comprovantes imutáveis das trocas;
- `demo_data_batches`: manifesto técnico usado para remover a carga fictícia sem apagar os dados reais.

Consulte [`DATABASE-SCHEMA.md`](DATABASE-SCHEMA.md) para os tipos e relacionamentos.

## Qualidade e testes

Execute a suíte automatizada:

```powershell
php artisan test
```

Verifique o padrão de código PHP:

```powershell
vendor\bin\pint --test
```

Gere o pacote de produção:

```powershell
npm run build
```

Valide dependências e vulnerabilidades conhecidas:

```powershell
composer validate --strict
composer audit --locked
```

Valide o contrato visual:

```powershell
npx -p @google/design.md designmd lint DESIGN.md
```

Na validação mais recente, a suíte possui **58 testes e 372 asserções**, todos aprovados.

## Documentação

- [`PRD.md`](PRD.md): visão do produto, perfis e regras de negócio;
- [`MVP-SCOPE.md`](MVP-SCOPE.md): escopo do MVP e funcionalidades futuras;
- [`DATABASE-SCHEMA.md`](DATABASE-SCHEMA.md): tabelas, tipos e relacionamentos;
- [`TASKS.md`](TASKS.md): roteiro sequencial de implementação;
- [`DESIGN.md`](DESIGN.md): identidade visual e regras da interface;
- [`UX-CONTRACT.md`](UX-CONTRACT.md): navegação, estados, feedback e responsividade;
- [`versoes.md`](versoes.md): histórico completo e processo de lançamento;
- [`deploy.md`](deploy.md): instalação e atualização na VPS Hostinger com CloudPanel;
- [`.cursorrules.json`](.cursorrules.json): convenções técnicas do projeto.

## Fora do MVP

O MVP não inclui geração automática de escalas, integração com folha de pagamento, WhatsApp, SMS ou trocas combinadas entre múltiplos plantões. Consulte [`MVP-SCOPE.md`](MVP-SCOPE.md) antes de ampliar o produto.
