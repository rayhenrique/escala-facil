# Ativação por convite

## Uso pela gerência

Em **Usuários**, escolha **Convidar profissional**, informe nome, e-mail, setor e categoria e solicite o envio. Não há campo de senha nesse fluxo. A conta fica pendente e não pode entrar até o profissional abrir o link e definir sua própria senha.

A gerência vê **Pendente**, **Aceito**, **Expirado** ou **Revogado** junto ao cadastro. **Reenviar convite** cria um novo link e invalida o anterior, inclusive mensagens antigas ainda na fila. **Revogar convite** pede confirmação e preserva a conta; a gerência pode emitir outro link depois. Conta aceita não pode receber outro convite para redefinir sua senha: use recuperação de senha. Convites só criam profissionais novos; um e-mail já cadastrado não é sobrescrito. Cadastros e senhas existentes mantêm seu funcionamento, e não foi aberto registro público.

## Token, ativação e acesso

- Token aleatório de 256 bits (`random_bytes(32)`), com apenas SHA-256 persistido em `account_invitations`. Expiração padrão de 48 horas, configurável por `INVITATION_EXPIRE_HOURS` (mínimo efetivo de uma hora). Aceite/revogação removem o hash; reenvio o substitui. A tabela mantém uma emissão corrente por usuário, com contador de reenvios e datas.
- `requires_activation` é falso por padrão na migração, preservando todas as contas anteriores. Só convites novos criam contas pendentes, com hash de uma senha aleatória desconhecida. Login e recuperação Laravel excluem contas pendentes; a gestão não define senha nem promove uma conta pendente a gerente. Não são enviadas senhas provisórias.
- O link não contém e-mail ou ID. Ao abrir, o token é retirado da URL antes de mostrar a página e é convertido num digest guardado na sessão. A URL passa a conter uma referência aleatória `grant`, válida apenas nessa sessão; o Livewire conserva essa referência bloqueada, sem receber o token bruto ou o hash do banco. Há até vinte referências por sessão para evitar crescimento sem limite e preservar abas distintas.
- Refresh funciona na sessão atual. Compartilhar apenas a URL com `grant` não concede acesso em outra sessão; para reabrir em outro navegador ou após expiração da sessão, use o link original do e-mail. Um scanner que apenas abre o link não consome o convite. A confirmação consome uma única vez e redireciona ao login; a autenticação/sessão Laravel permanece a existente, sem login automático.
- Link inexistente, expirado, usado, revogado ou ligado a um e-mail alterado recebe a mesma mensagem na confirmação, sem identificar a conta. Recuperação de senha usa mensagem pública uniforme para contas ativas, pendentes e inexistentes. Há limites por IP na abertura/confirmação/recuperação e por gerente no envio. Validações de campos continuam explícitas na área gerencial autorizada.
- Alterar o e-mail pendente invalida o link antigo; depois de salvar, reenvie ao novo endereço. Perfis/vínculos são revalidados no cadastro. Tokens não entram no histórico operacional; auditoria guarda IDs, estado, geração e datas, incluindo autor do aceite, sem senha, hash de token ou cópia do e-mail.

## Transações e concorrência

As Actions revalidam Policy e estado no servidor, com locks ordenados em usuários e depois no convite. Aceite exige conta ainda pendente e profissional, endereço compatível, token corrente e expiração futura no instante da confirmação. Mudança de senha, retirada do estado pendente e consumo acontecem na mesma transação. Duas confirmações ou revogação/aceite simultâneos não podem ambos concluir. Falhas e rollback não deixam evento de sucesso; conflitos de concorrência recebem mensagem genérica apropriada ao fluxo.

## E-mail e fila

`AccountInvitationNotification` reutiliza Notifications/Mail do Laravel, implementa `ShouldQueue` e `ShouldBeEncrypted` e solicita envio **após commit**. O token necessário ao e-mail fica criptografado no payload da fila e dos jobs falhos. O worker verifica novamente emissão, expiração, conta e endereço antes de entregar; uma mensagem substituída, aceita ou revogada é descartada.

Use `APP_URL` com a origem HTTPS efetiva do aplicativo: o link usa essa configuração, sem confiar no Host da requisição. Configure um mailer real e seus parâmetros existentes (`MAIL_*`). `INVITATION_MAILER` permite usar um mailer próprio ou SMTP; ausente, usa `MAIL_MAILER`. O aplicativo recusa transporte `log`/`null` e fallback para esses transportes, pois gravariam links ou descartariam mensagens. O transporte `array` é permitido somente fora de produção para testes. Não foram alteradas credenciais nem o `.env` instalado.

Com `QUEUE_CONNECTION=database` (padrão do projeto), as migrações existentes fornecem `jobs`/`failed_jobs`; mantenha o worker supervisionado e confira falhas. Redis/SQS ou outros drivers configurados mantêm o fluxo Laravel. Com conexão `sync`, o envio é síncrono após commit; não há detecção automática de um processo worker em execução. A interface confirma solicitação de envio, não entrega garantida. Falha posterior do transporte/enfileiramento mantém conta pendente; revise a fila/configuração e reenvie pela gerência. Não use conexão `null` para convites.

Não registre URLs com `token` em proxy, analytics, ferramentas de suporte ou logs de acesso. A aplicação remove o token antes de renderizar, usa `no-store`, `no-referrer` e `noindex`, e não o copia para a auditoria; o primeiro acesso ainda chega à infraestrutura com o parâmetro. Restrinja acesso à fila, backups e chave de criptografia. Rotação de `APP_KEY` precisa considerar jobs criptografados pendentes.

## Verificação e manutenção

Testes PHP cobrem expiração no instante exato, uso único, reenvio, revogação, autorização, dados preservados, bloqueio de recuperação em contas pendentes, respostas genéricas, duas abas, transporte de log, consulta sem N+1, criptografia da fila, descarte de envio substituído e rollback sem e-mail. Processos separados cobrem aceite/aceite e aceite/revogação. SQLite não certifica os locks do MySQL; valide-os em homologação junto ao worker e transporte reais antes de instalar. Nenhum deploy foi executado.

Regressão de interface: `npm run test:invitations:browser`, exclusivamente com base sintética nova por execução. Informe `INVITATION_URL`, `INVITATION_FIXTURE`, `INVITATION_QA_PASSWORD`, `PLAYWRIGHT_MODULE`/`PLAYWRIGHT_CHANNEL`; `AXE_MODULE` ativa a verificação AA e `INVITATION_SCREENSHOTS` salva capturas fora do repositório. Fixture: `sector`, `category`, `teams[320|390|1440]` com `url` original de um convite sintético e `email`; conta gerencial `invites-manager@example.test`. Teste cria convites, reenvia/revoga, ativa registros sintéticos e verifica uso único/login em 320/390/1440 px. Foi usado Playwright porque o plugin Browser estava indisponível.

Retenção de metadados segue o plano institucional em [Histórico operacional](operational-audit.md). Não há expurgo automático nem prazo legal presumido. A migração não reescreve cadastros antigos e recusa rollback se houver convites ou contas pendentes, evitando remover o bloqueio de login. Voltar a uma versão sem esse bloqueio exige manter manutenção/restringir login até revisar compatibilidade; preserve as tabelas e o histórico, sem remover silenciosamente o estado de ativação. A conexão de fila `null` é recusada antes da criação/reenvio; no aceite, tokens de recuperação eventualmente existentes também são invalidados.
