# Histórico operacional

A partir da versão 1.29.0, o Escala Fácil registra alterações efetivas de plantões, usuários, setores, categorias e trocas. Em **Histórico operacional**, a gerência combina tipo, ID, ação, nome do autor e datas; cada página mostra até dez eventos. O link **Histórico** ao lado do registro abre sua consulta e preserva a origem para voltar à lista. Registros excluídos continuam consultáveis pelo tipo e ID.

## O que é armazenado

`operational_audit_events` contém autor (ID, ou operação sem sessão), ação, tipo e ID da entidade, data/hora com precisão de microssegundos, identificador da operação e apenas diferenças relevantes anteriores/posteriores. Horários usam o fuso configurado do aplicativo, explicitado na tela. Nomes de usuários são armazenados somente quando o próprio nome faz parte da criação/alteração/exclusão. O nome do autor é consultado no cadastro atual; após sua exclusão, fica o ID original, sem reescrever eventos.

Plantões: início, término, data de término, setor, categoria, profissional e situação. Cadastros: nome, perfil e vínculos; uma mudança de e-mail gera apenas uma indicação, sem copiar o endereço. Trocas: IDs dos plantões/participantes, situação e gerente. Aprovação, rejeição, aceite e reversão recebem rótulos próprios. Atribuir outro profissional é realocação; retirar a atribuição por ausência permanece uma edição do plantão. A rejeição automática após ausência é registrada com o autor da operação e o mesmo identificador da alteração do plantão.

Senhas, hashes de senha, tokens, anexos, motivos de ausência, texto da justificativa, notificações e comprovantes não são copiados. A tabela especializada de realocações e os comprovantes existentes permanecem como estavam; o novo histórico registra somente a diferença operacional, não uma segunda cópia desses documentos. Alterações de sessão/versão vista e gravações sem mudança relevante não geram ruído.

## Gravação e consistência

`HasOperationalAudit` envolve `save`/`delete` dos modelos em transação, relê o registro sob lock e compara o estado efetivamente persistido. Também funciona com `saveQuietly`/`deleteQuietly` e fora do Livewire. Se gravar a auditoria falhar, a alteração é desfeita; um rollback externo desfaz ambos, inclusive se houve saves internos. As Actions existentes usam `OperationalAuditContext`, preservando retries, regras, validações e autor explícito em CLI/jobs. Eventos da mesma decisão/transação recebem um UUID comum.

Escritas Eloquent em lote que poderiam pular a captura são recusadas pelo builder dos modelos. Use modelos individuais ou `eachById` dentro de transação; a rejeição automática por ausência e a limpeza de demonstração foram adaptadas com processamento limitado. Imports e cópias mensais passam pela criação auditada. Não use `DB::table`/SQL direto/`toBase()` para alterar entidades operacionais em novos serviços: esses caminhos são reservados a migrações/manutenção revisada e não oferecem contexto de autoria PHP. Cascatas já existentes do banco não foram alteradas nem recebem autoria inventada para cada dependência.

A consulta exige `OperationalAuditEventPolicy`, que confere o perfil gerencial atual no banco. Nenhum perfil possui permissão de edição/exclusão pelo aplicativo. Builder/modelo bloqueiam mutações e upsert/truncate, e triggers MySQL/SQLite recusam UPDATE/DELETE diretos. IDs/nomes não concedem permissão. Não há backfill: eventos anteriores à instalação não são fabricados. Uma tentativa concorrente recusada não deixa evento de sucesso; lacunas de IDs após rollback não representam eventos perdidos.

## Instalação e retorno de versão

A migração cria somente a tabela, índices e triggers; não reescreve dados de usuários nem históricos antigos. O usuário de migração precisa de permissão para criar triggers. Execute o fluxo normal de atualização em manutenção, aplicando migrações antes de liberar o aplicativo. Este trabalho não fez deploy.

A reversão da migração recusa apagar uma tabela com eventos. Para retornar o código a uma versão anterior, preserve a tabela e mantenha a operação em manutenção até revisar a compatibilidade. Evite `migrate:fresh`, DROP ou TRUNCATE em produção. A conta de execução deve ter SELECT/INSERT nesta tabela, sem UPDATE/DELETE ou privilégios DDL; use outra conta controlada para migrações. Triggers não impedem um administrador com poder de removê-los, e TRUNCATE MySQL exige controle de privilégios, não apenas trigger DELETE.

## Plano de retenção e dados pessoais

O controlador institucional deve registrar finalidade, base legal, prazo de retenção e responsáveis no inventário de tratamento antes de estabelecer uma rotina de descarte. Dados identificáveis devem ser limitados à finalidade, e a conservação após o término do tratamento precisa se enquadrar nas hipóteses legais aplicáveis. Referência: [LGPD, arts. 6, 7, 15 e 16](https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709compilado.htm). Os direitos do titular e as hipóteses de eliminação são explicados pela [ANPD](https://www.gov.br/anpd/pt-br/assuntos/titular-de-dados/direito-dos-titulares).

Plano proposto para aprovação institucional, sem prazo de expurgo inventado no código:

1. Gerência e encarregado identificam a necessidade de cada classe de evento e definem uma matriz de prazos, termo inicial, fundamento documentado e eventual suspensão por processo/investigação. IDs também são dados pessoais quando permitem associação ao titular.
2. TI restringe a consulta, separa credenciais de migração/manutenção, controla backups e registra os acessos administrativos fora do banco operacional. Transporte/backup devem seguir a política institucional; acesso ao banco não deve ser compartilhado com usuários do aplicativo.
3. Revisão trimestral proposta da matriz, dos privilégios e da necessidade dos nomes históricos. A instituição confirma periodicidade e prazos; este release não executa eliminação, anonimização automática nem altera políticas existentes.
4. Pedidos de titulares são recebidos pelo canal institucional e avaliados pelo encarregado. Correção de um dado operacional gera nova alteração, sem edição silenciosa do evento anterior. Acesso ao histórico completo não é liberado a outros profissionais para atender um pedido individual.
5. Vencido o prazo aprovado, um procedimento administrativo separado e revisado trata exclusivamente os dados autorizados, incluindo cópias/backups e impedimentos de conservação. Deve haver prévia, contagens, registro da autorização e validação posterior. A manutenção excepcional de retenção fica fora das permissões do aplicativo e precisa de procedimento específico para as proteções do banco; não pode ser um endpoint de apagar histórico.

Pendente institucional: base legal concreta, matriz de prazos e procedimento autorizado de descarte/anonimização. O aplicativo conserva os eventos até essa definição, sem afirmar que retenção indefinida seja permitida.

## Verificação

Regressão de navegador: `npm run test:audit:browser`, apontando `AUDIT_URL` para servidor local com base sintética descartável. Informe `AUDIT_QA_PASSWORD`, `AUDIT_SHIFT_ID` (registro com criação e doze edições), `AUDIT_DELETED_ID` (registro excluído), `PLAYWRIGHT_MODULE`/`PLAYWRIGHT_CHANNEL` conforme o ambiente. Contas de teste: `audit-manager@example.test` e `audit-ana@example.test`; o nome do gerente é `Gerente Auditor QA` e o profissional é `Ana Revisada QA`. `AXE_MODULE` ativa a verificação de acessibilidade e `AUDIT_SCREENSHOTS` salva capturas fora do repositório. O teste consulta os registros, sem alterar plantões, e verifica filtros, paginação, refresh, retorno contextual, histórico de exclusão, teclado e bloqueio do perfil profissional em 320/390/1440 px. O plugin Browser estava indisponível nesta execução, por isso foi usado Playwright.

PHPUnit cobre backend/quiet writes, diferenças, dados excluídos da captura, rollback, Policy, filtros, paginação, bloqueio de mutações e SQL direto. Processos PHP separados verificam decisões e realocações concorrentes, com um único evento de sucesso e autoria correta. Os testes SQLite não certificam locks/triggers/privilégios do MySQL de produção; valide em homologação antes da instalação, junto ao plano de acesso do banco.
