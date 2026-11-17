# Diagnóstico de bloqueios de reversão

> Atualização 1.48.0: o erro de snapshot parcial documentado abaixo foi tratado com validação estrutural e bloqueio explicado, sem liberar reversão sem evidência. O teste de caracterização foi convertido em regressão desse comportamento. Há agora um [procedimento administrativo separado](administrative-swap-corrections.md), sem restauração de plantões ou ajuste automático de cota. A análise abaixo preserva o diagnóstico feito sobre a 1.47.3; a causa real do pedido #190 não foi comprovada.

## Escopo e limite da conclusão

Análise da `main` no commit `d76944f` (versão 1.47.3). Não houve acesso a produção, deploy, correção de dados ou execução de reversão em banco real. Não foi fornecido um extrato dos registros reais do pedido #190 e dos plantões #1675/#1468; sua causa específica permanece **não comprovada**.

Os IDs foram usados somente em fixtures sintéticas descartáveis, com plantões futuros em 20/11 e 22/11/2026 e relógio de teste em 01/11/2026. Esses horários, vínculos e eventos não representam produção. Não se pode inferir datas ou dependências apenas pelo número dos registros.

Nesta etapa foram adicionados um CLI somente leitura, seu serviço de diagnóstico e testes. `ShiftSwapReversion`, `RevertShiftSwapAction`, auditoria operacional, Models, Policies, cotas e migrations não foram alterados. A investigação teve como base a versão 1.47.3; após a entrega dos resultados, o usuário autorizou commit e push do diagnóstico como manutenção 1.47.4. Isso não declara corrigido o defeito de snapshot parcial nem recuperado o CI geral.

## Causas e comportamentos comprovados no código/testes

| Condição | Resultado vigente e evidência |
| --- | --- |
| Nenhum ou vários eventos `approved` | Não há checkpoint único; `inspect()` não escolhe um evento arbitrariamente (`ShiftSwapReversion.php:112–116`). O CLI distingue as duas situações. |
| `approval_state` ausente/nulo | Não é bloqueio suficiente por si. O fallback `legacyState()` exige trilha desde a criação do pedido e dos plantões, campos originais e vínculos coerentes (`:44–83`). Fixture com trilha completa passou, inclusive sem `approval_state`. |
| Falta de `created` ou vínculos históricos diferentes | Fallback não comprova estado original; retorno fica bloqueado, sem inferir titulares pelo estado atual. Testado com evento de criação ausente. |
| `approval_state.after` definido, mas vazio ou parcial | `??` só usa o fallback quando o valor é nulo/ausente. Um payload definido não é substituído automaticamente pela reconstrução legada. Payloads parciais podem causar erro técnico, conforme reprodução abaixo. |
| Ordem das chaves JSON | A versão 1.47.3 ordena apenas as chaves de `bindings`, conservando tipos, valores e conjunto de chaves (`:87–98`). Não acrescenta snapshots a eventos antigos. |
| Ordem numérica #1675 > #1468 | Não foi causa de falso bloqueio nas fixtures. Troca mútua #190 com esses IDs produziu prévia elegível e fingerprint igual usando a coleção ordenada como na confirmação. Testado em SQLite e MySQL. |
| Eventos posteriores dos plantões | Eventos com ID maior que o da aprovação e outro `operation_id` bloqueiam (`:120–124`), mesmo se a alteração depois foi restaurada. Transferências da própria operação de aprovação são excluídas. O CLI mostra os dois grupos, sem remover eventos. |
| Estado atual diferente do retrato | Mudança de titular, status, horário, data, setor ou categoria bloqueia. O CLI informa diferenças por campo (`:144–156`). Suas diferenças estritas também mostram distinções de tipo; `review_snapshot_matches` conserva a comparação efetivamente usada pelo domínio. |
| Ausência, pedido pendente em qualquer perna, início/passagem do plantão, original inativo/incompatível ou conflito | São bloqueios institucionais existentes, não evidência automática de bug (`:125–183`, `ShiftAssignmentAnalysis`). Dobra consecutiva é aviso. |
| Autorização | `viewAny` exige gerente; `reviewReversion` exige gerente e status aprovado. `revert` combina isso com `inspect()` (`ShiftSwapPolicy.php:77–95`). A Action revalida o ator e os dados sob locks. Poder abrir a revisão não implica poder confirmar. |
| Fingerprint antigo | A Action recusa se `can_revert` for falso **ou** o hash divergir (`RevertShiftSwapAction.php:64`). É possível não haver bloqueios na prévia atual e ainda assim uma prévia anterior ser recusada. O CLI compara o hash opcional sem confirmar nada. |
| Cota mensal / `approved_at` | Não são condições de autorização da reversão. Alterar uma data da cota não cria `approval_state` nem resolve falta de proveniência. Nenhuma regra de cota foi modificada. |

O limite de 20 eventos em `review.later_events` é amostra da consulta do domínio, não prova de que só existem 20 alterações. O CLI lista separadamente os eventos dos plantões vinculados, com IDs, operações, campos e valores auditados.

## Bug reproduzível, ainda não corrigido

**Falha de robustez no consumo de snapshot parcial:** com `bindings` válidos, `before[1675]` contendo `status` e demais campos, mas sem `user_id`, `ShiftSwapReversion.php:146` acessa a chave diretamente. O handler do Laravel lança `ErrorException: Undefined array key "user_id"`. Em vez de um motivo de bloqueio estruturado, a análise falha tecnicamente. A Policy `revert` e a confirmação também dependem dessa análise.

Reprodução automatizada:

```sh
php artisan test --filter=test_partial_embedded_state_reproduces_existing_review_error_and_diagnostic_reports_it
```

O teste caracteriza o defeito existente com `assertThrows(ErrorException)` e protege o novo diagnóstico: ele deve capturar o erro, indicar o campo ausente em `embedded_shape`, retornar `review.can_revert=null` e código 3, sem criar reversão. **Teste verde não significa que o domínio foi corrigido.** Uma correção futura deverá trocar a expectativa do domínio por bloqueio estruturado e acrescentar casos de `before`, `after` e tipos malformados.

Esse caso é um erro técnico com dado parcial, não comprovação de que a recusa de um histórico incompleto seja indevida. Também não comprova que o pedido real #190 tenha esse payload. Não foi reproduzido falso bloqueio com snapshot completo ou legado reconstruível nos cenários executados.

## Hipóteses para o pedido real #190

Devem ser verificadas em uma cópia isolada, sem pressupor que #1675/#1468 sejam de fato as duas pernas atuais:

1. O pedido pode não ter exatamente uma aprovação auditada, ou pode ter sido aprovado antes da auditoria operacional. Status `approved` e notificações não substituem automaticamente a evidência exigida pelo domínio.
2. Pode faltar `approval_state` e a trilha anterior à aprovação pode começar após a criação de algum registro, ter campos indisponíveis ou vínculos distintos. A atualização para 1.47.3 não reconstrói retroativamente esses dados.
3. Pode existir snapshot definido, porém parcial ou com IDs como strings. Ordenar chaves não converte tipos nem fabrica campos. Examine `embedded_shape`, os vínculos e a exceção, se houver.
4. Pode haver realocação, troca encadeada, edição restaurada, ausência, dependência pendente ou plantão já iniciado. Compare os dois plantões, seus eventos e as datas efetivas, incluindo `America/Maceio`.
5. Eventos legados/importados podem ter sido inseridos com IDs ou `operation_id` diferentes da sequência esperada. O código usa ordem por ID e identidade da operação, não apenas `occurred_at`. Isso exige comprovação histórica própria; não é motivo para ignorar eventos automaticamente.
6. Se a prévia atual estiver elegível, mas a confirmação for recusada, compare o hash da tela, o ator e a versão efetiva do servidor. Reabrir uma prévia pode resolver um hash antigo; não resolve dados históricos ausentes.

Nenhuma dessas hipóteses foi atribuída aos registros reais sem os seus dados.

## CLI de diagnóstico somente leitura

Em uma **cópia isolada** já configurada com banco próprio e `APP_ENV=local` ou `testing`:

```sh
php artisan app:diagnose-swap-reversion 190 --json
php artisan app:diagnose-swap-reversion 190 --manager=ID_DO_GERENTE --json
php artisan app:diagnose-swap-reversion 190 --expected-snapshot=HASH_DA_PREVIA --json
```

Os marcadores `ID_DO_GERENTE` e `HASH_DA_PREVIA` devem ser substituídos por valores reais da cópia. O comando não executa `RevertShiftSwapAction`, não salva modelos, não aplica migrations e não tem opção de reparo. Não foi executado sobre dados reais nesta investigação. Em produção/staging, recusa antes das consultas. **Não troque apenas o nome do ambiente para contornar a proteção:** `APP_ENV=local` não prova que as credenciais apontam para uma cópia.

O relatório identifica o pedido e os vínculos efetivamente encontrados; mostra origem/tipo/estrutura do snapshot, eventos de aprovação, disponibilidade da trilha anterior, retratos dos plantões, diferenças, alterações posteriores, bloqueios do domínio, Policies quando solicitado e comparação opcional de hash. Campos legados não observados são evidência de ausência no log, não necessariamente campos obrigatórios faltantes: por exemplo, vínculos nulos podem não ter sido registrados, e o fallback admite `end_date` nulo.

Não imprime nomes de profissionais, justificativas, anexos ou conteúdo de notificações. IDs, horários, vínculos e operações ainda são dados internos e devem ter acesso restrito. O JSON não é autorização nem pacote de restauração.

| Saída | Significado |
| --- | --- |
| 0 | Nenhum bloqueio observado; Policies só foram avaliadas se informado `--manager`. Não garante confirmação futura. |
| 1 | Bloqueio, negação de Policy ou fingerprint divergente observado. |
| 2 | Ambiente/parâmetro inválido, pedido ou usuário não encontrado na cópia. |
| 3 | A análise existente lançou exceção; o relatório preserva o erro e não conclui elegibilidade. |

A transação é somente de leitura, sem locks de confirmação. A visão consistente no MySQL depende do isolamento padrão `REPEATABLE READ`; sob outro isolamento, use uma cópia estável. Alterações após a leitura podem mudar o resultado. A justificativa obrigatória de confirmação não é avaliada, porque nenhuma confirmação é solicitada.

## Riscos e correções recomendadas

- Validar estrutura e tipos do snapshot antes dos acessos, retornando motivo técnico específico sem liberar reversão com evidência incompleta. É correção recomendada, **não implementada** nesta etapa.
- Manter separados erro de dados, falta de proveniência, bloqueio institucional e prévia desatualizada. Não apresentar `can_revert=null` como falso/verdadeiro nem como autorização.
- Nunca preencher `approval_state` com titulares atuais, trocar status, apagar eventos posteriores, reutilizar artificialmente `operation_id` ou alterar `approved_at` para liberar a ação. Isso destruiria a distinção entre fatos originais e atuais.
- Para comprovar #190, preservar e examinar, na cópia: pedido e vínculos; eventos do pedido, especialmente `approved`; eventos dos plantões #1675/#1468 (e quaisquer vínculos diferentes encontrados); titulares originais/atuais e compatibilidade; dependências e ausências; hash da tela, horário da tentativa e versão do servidor. Exportar apenas os campos necessários, sem senhas, tokens, dados médicos ou anexos.
- Se faltar evidência confiável, continuar pela análise administrativa existente. Uma futura operação de correção deve ter procedimento e autorização próprios; não é uma reversão forçada disfarçada.

## Verificação

Testes cobrem IDs e ordem das pernas, legado completo, criação ausente, zero/múltiplas aprovações, evento posterior e diferença de campo, snapshot parcial, negação de Policy, hash antigo e recusa do CLI em produção antes de consultas. A verificação somente leitura compara pedidos, plantões, auditoria, reversões e notificações antes/depois e rejeita DML/DDL no log de consultas. As Actions de aprovação e os dados artificiais são usados apenas para montar fixtures descartáveis.

Também foram executados os testes existentes de reversão segura, liberação de cota e imutabilidade no MySQL 8.4.7, além de Policies e disputa entre processos no SQLite descartável. A disputa por locks MySQL não foi reproduzida. Nenhum bug de produção foi declarado corrigido.

Resultados: 7 testes finais do CLI aprovados em cada engine (69 assertions por execução); 25 testes existentes de reversão/imutabilidade aprovados no MySQL; 14 testes existentes de reversão segura e 5 de Policies/concorrência aprovados no SQLite. Pint e `git diff --check` aprovados. A suíte geral e os checks de frontend não foram repetidos nesta etapa de diagnóstico PHP; isso não declara o CI geral recuperado.
