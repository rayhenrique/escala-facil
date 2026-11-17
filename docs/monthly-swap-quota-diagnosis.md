# Diagnóstico histórico da cota mensal

A regra permanece em quatro participações aprovadas por profissional no mês da aprovação, em `America/Maceio`. `User::approvedSwapCount`, `swapCountForMonth`, a consulta de elegibilidade e os relatórios compartilham `ShiftSwap::approvedInMonth`: status `approved` e intervalo fechado no início e aberto no começo do mês seguinte. Solicitante e substituto são contados; a data de criação da solicitação não define o mês. A aprovação trava os participantes em ordem estável e revalida a cota antes de gravar.

Desfazer uma troca muda seu status para `reverted`, liberando a participação no mês original. O `approved_at`, o evento de aprovação, os comprovantes e o registro de reversão são preservados. A comparação dos vínculos auditados ignora apenas a ordem das chaves de objetos JSON, que pode variar no MySQL; mantém a comparação estrita de valores, tipos e conjunto de chaves.

## Limites dos registros legados

A migração `2026_11_06_000000_add_approved_at_to_shift_swaps_table.php` preencheu somente trocas com status `approved`, usando `COALESCE(updated_at, created_at)`. Uma edição posterior à aprovação, anterior à migração, pode ter deslocado o mês inferido. A migração não gravou proveniência: igualdade com `updated_at` ou `created_at` é apenas indício, não prova da data da decisão. Trocas já desfeitas naquela ocasião podem continuar com `approved_at` nulo e não consomem cota.

A migração existente não foi alterada. O diagnóstico não executa migrações, não corrige registros e não usa eventos de auditoria para substituir datas. A partir desta manutenção, novas gravações auditadas de `approved_at` registram o instante completo com offset; eventos antigos permanecem intactos.

## Execução somente leitura

```sh
php artisan app:diagnose-monthly-swap-quota
php artisan app:diagnose-monthly-swap-quota --swap=123 --swap=456
php artisan app:diagnose-monthly-swap-quota --jsonl
```

O comando exige que a configuração efetiva `app.timezone` seja `America/Maceio`. Se houver outra configuração, ele encerra sem consultar os históricos; não muda o fuso automaticamente nem tenta converter datas de origem desconhecida. Não há opção de reparo.

As leituras são paginadas por ID em lotes de 200, com uma consulta de eventos de aprovação por lote, em uma transação sem escritas ou locks explícitos de linha. No MySQL, a visão consistente depende do isolamento `REPEATABLE READ` padrão; sob outro isolamento, execute em janela estável e repita para verificar mudanças concorrentes. A transação de leitura pode durar durante toda a varredura: em bancos grandes, prefira selecionar IDs ou uma réplica consistente.

O modo JSON Lines emite um registro de contexto, um por troca com achados e um resumo. Inclui IDs da troca e participantes, datas originais, mês em que o registro atualmente consome cota, evidências de auditoria e códigos dos achados. Não inclui nomes, e-mails ou comprovantes. `quota_month` é nulo para trocas desfeitas.

| Código de saída | Significado |
| --- | --- |
| 0 | Nenhuma divergência ou incerteza detectada nos registros examinados; não certifica datas sem proveniência externa. |
| 1 | Foram encontrados registros divergentes ou incertos; nenhum foi alterado. |
| 2 | Parâmetro ou configuração de fuso inválido. |

## Interpretação dos achados

| Código | Interpretação |
| --- | --- |
| `missing_or_invalid_approved_at` | Troca com evidência/status histórico sem data utilizável. Legados desfeitos podem ter esse estado sem afetar a cota. |
| `unexpected_approval_for_status` | Data/evento de aprovação em status diferente de aprovado ou desfeito. |
| `approval_in_future`, `approval_before_creation`, `approval_after_updated_at` | Inconsistência cronológica em relação ao instante da leitura ou timestamps do registro. |
| `missing_approval_event` | Não há evento de transição de aprovação para comprovar a data. Criação direta já aprovada também entra aqui. |
| `possible_updated_at_backfill`, `possible_created_at_backfill` | Data sem evento de aprovação igual a um timestamp que a migração podia copiar. Origem não comprovada. |
| `multiple_approval_events` | Há mais de um evento; o comando não escolhe qual representa a decisão. |
| `invalid_approval_event`, `invalid_event_date`, `invalid_audited_approved_at` | Evidência ausente ou malformada. |
| `approval_event_date_mismatch`, `approval_month_mismatch` | Sem um `approved_at` explícito no evento, sua ocorrência diverge da data legada. Classificado como incerto: a ocorrência do evento não é necessariamente o instante exato da decisão. |
| `audited_approved_at_mismatch` | A data gravada diverge do valor explícito preservado no evento de aprovação. |

Diferenças de microssegundos de `occurred_at` não são tratadas como alterações de `approved_at`, que é persistido com precisão de segundos. Se o evento preserva explicitamente o mesmo `approved_at`, uma gravação de auditoria que cruza a meia-noite não desloca artificialmente o mês nem gera um falso alerta. Correspondência entre uma data legada e a ocorrência do evento é corroborativa, não prova de como ocorreu o backfill.

Quando uma troca tem vários achados, sua classificação geral é `divergent` se algum for cronológico ou contradisser um valor explícito; os demais casos são `uncertain`. A classificação indica necessidade de revisão, não culpa, fraude ou autorização para corrigir dados.

## Validação

Os testes exercitam virada do mês à meia-noite de Maceió, participação nos dois papéis, aprovações de solicitações antigas, edições posteriores, liberação da cota original após reversão e preservação do evento original. Dois processos disputam as últimas vagas com três participações já aprovadas: apenas um pode aprovar, sem chegar à quinta participação. Os testes de diagnóstico comparam os dados antes/depois e inspecionam as consultas para rejeitar DML/DDL.

Os testes de disputa entre processos usam SQLite descartável, seguindo os testes de concorrência existentes. Os cenários de cota, diagnóstico, auditoria operacional, reversões e imutabilidade foram também executados no MySQL 8.4.7; não se afirma que a disputa por locks foi reproduzida nesse engine. O MySQL local difere da imagem 8.0 do CI.

O teste do backfill executa apenas `up()` sobre um esquema pré-migração descartável SQLite, com asserts sobre os timestamps originais. Não chama `down()` na base de testes ativa, evitando o commit implícito e a remoção de índice de foreign key do MySQL. Isso não pretende validar o rollback de uma migração já executada.

Nenhum banco de produção foi examinado nesta implementação; os cenários foram reproduzidos com fixtures. Achados reais devem ser confrontados com as evidências institucionais e permanecer sem correção automática.

### Resultado da validação em 16/11/2026

- `php artisan test --compact --no-ansi` (SQLite): **475 aprovados**, 3.742 assertions.
- Mesmo comando com `DB_CONNECTION=mysql`, instância descartável própria em `127.0.0.1:13316`, banco `escala_facil_testing`: **469 aprovados, 5 falhas e 1 skip existente**, 3.726 assertions.
- `php vendor/bin/pint --test`, `npm run build`, `node --test tests/js/*.test.mjs`: aprovados, incluindo os 53 testes JavaScript.
- `git diff --check`: aprovado. `User.php`, `ShiftSwap.php` e todas as migrações existentes permanecem sem alterações.

As cinco falhas do MySQL ocorreram em testes não alterados nesta manutenção:

| Teste | Bloqueio observado |
| --- | --- |
| `AccountInvitationsTest::test_management_list_eager_loads_invitation_status_without_per_row_queries` | O teste procura `from "account_invitations"`; o SQL MySQL usa backticks. A consulta não é reconhecida pelo contador do teste. |
| `DashboardSwapAnalysisTest::test_notification_failure_rolls_back_status_and_both_assignments` | O listener injeta a falha somente quando encontra `insert into "notifications"`; não reconhece o INSERT MySQL, então a simulação não ocorre. |
| `ManagerSwapHistoryTest::test_page_reads_eager_load_relations_and_revert_exists_without_per_record_queries` | Asserts procuram SQL com aspas de SQLite, incluindo `from "shift_swaps"`. |
| `NotificationReceiptStatusTest::test_current_states_are_loaded_once_per_page_without_n_plus_one` | Contadores procuram `from "shift_swaps"` e `from "users"` com aspas de SQLite. |
| `ShiftReallocationTest::test_audit_failure_rolls_back_assignment_and_audit_survives_record_deletion` | A execução lança `ValidationException` na comparação do fingerprint entre a prévia e a confirmação (`ReallocateShiftAction.php:39`). O teste cria a prévia sobre a instância da factory e a confirmação relê o registro; a normalização desses atributos no MySQL requer diagnóstico próprio. Não se atribui essa falha à cota nem se afirma que a causa fina foi resolvida. |

O skip é o teste existente de plano de consulta específico de SQLite em `OperationalFiltersTest`. Nenhum teste foi desativado. A suíte completa ainda não está verde. Após ciência dos resultados, o usuário autorizou commit separado e push da versão 1.47.3 com essas cinco falhas conhecidas; a recuperação completa do CI permanece pendente.
