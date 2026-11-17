# Registro administrativo de trocas — 1.48.0

O registro administrativo documenta uma anulação ou retificação declarada pela gerência. Ele não reescreve a aprovação original nem pretende restaurar a escala. A reversão operacional permanece como fluxo para plantões futuros elegíveis.

## Fluxo

1. Em Trocas → Histórico, abrir **Desfazer troca** para consultar a revisão.
2. Se a reversão estiver bloqueada, escolher **Analisar correção administrativa**. Conferir a aprovação, os plantões, os comprovantes e as alterações posteriores.
3. Selecionar retificação ou anulação administrativa, informar justificativa (10–2.000 caracteres) e evidências/referências (10–4.000 caracteres).
4. Confirmar explicitamente que o registro é imutável, preserva a aprovação e não restaura plantões nem ajusta cotas. Acionar **Registrar retificação**.
5. Consultar o novo comprovante e seu PDF administrativo. Os participantes recebem notificações do registro efetivamente realizado.

Se já existir registro, a tela permite consultá-lo; não oferece substituição ou exclusão. Há um registro por pedido, seguindo o precedente de reversões únicas e garantindo idempotência. Adendos posteriores exigiriam um procedimento próprio; não se deve editar o registro no banco.

## Efeitos e invariantes

| Operação | Estado operacional | Atribuições | Cota | Evidências |
| --- | --- | --- | --- | --- |
| Desfazer operacionalmente | Passa de `approved` a `reverted` | Restaura somente quando todas as validações existentes passam | Libera participação pela regra vigente | Novo registro de reversão; originais preservados |
| Retificar/anular administrativamente | Continua `approved`, com badge administrativo separado | Nenhuma atribuição é alterada | Nenhum ajuste automático, inclusive em meses anteriores | Novo registro documental, auditoria, comprovante e notificações |

Datas de criação, atualização e aprovação do pedido não são gravadas pela Action administrativa. Aprovações, comprovantes anteriores e eventos de auditoria permanecem inalterados. A indisponibilidade de um snapshot original não autoriza fabricá-lo: a evidência registra os bloqueios encontrados e referencia os eventos existentes.

Se um pedido anotado administrativamente tornar-se elegível a uma reversão operacional futura, a operação continua seguindo suas regras e deixa sua própria evidência. O registro administrativo anterior permanece preservado e seu PDF específico continua disponível. A anotação não confere autorização para restaurar plantões iniciados/passados.

## Segurança e consistência

- `RectifyShiftSwapAction` exige gerente atual, pedido aprovado, ausência de registro anterior, revisão atual não reversível, justificativa, evidências, checkbox aceito e fingerprint válido.
- Locks seguem a ordem existente: usuários participantes/gerente por ID, plantões por ID e pedido. Após o lock, a autorização e os vínculos são revalidados. Índice único por pedido impede duplicação mesmo em concorrência.
- A evidência contém vínculos/timestamps observados, IDs das aprovações, estado comprovado quando disponível, bloqueios, snapshots atuais, todos os IDs/metadados de auditoria pertinentes e dependências pendentes. O hash considera também eventos além da amostra limitada da revisão operacional.
- Registro, evento de auditoria e notificações são atômicos. Falhas de auditoria ou de notificações — inclusive após escrever a primeira — causam rollback. Contenção devolve erro de revisão; não repete a ação no cliente.
- `shift_swap_rectifications` não possui exclusão em cascata. Modelo/builder e triggers SQLite/MySQL recusam updates/deletes; rollback de migration com registros existentes é recusado. A migration é nova e aditiva. O usuário da aplicação no banco deve continuar sem privilégios administrativos de DDL, como já exigido pela auditoria append-only.
- Somente gerente pode registrar. O PDF requer a Policy `view` do pedido: gerente ou participante correspondente, sem acesso por ID de terceiros. Texto livre é escapado no HTML e PDF; não registrar dados médicos, anexos, senhas ou informações pessoais desnecessárias.

## Correção do diagnóstico anterior

Snapshots parciais deixam de provocar `ErrorException` ao acessar campos ausentes. `ShiftSwapReversion` verifica estrutura, tipos e presença dos campos antes de usar `before`/`after`; evidência incompleta produz bloqueio explicado, não liberação forçada. O fallback legado continua exigindo trilha confiável. A comparação de vínculos mantém tipos/valores estritos e independe da ordem de chaves JSON.

A causa do pedido real #190 continua sem confirmação: não foram consultados nem modificados dados de produção. Os IDs do diagnóstico anterior foram exercitados apenas em fixtures.

## Publicação e testes

Aplicar a migration aditiva pelo procedimento de deploy já existente. Não executar migrações de rollback sobre histórico populado e não aplicar SQL manual para liberar pedidos.

Os testes cobrem casos simples/mútuos, legados, passados, elegibilidade operacional, confirmação, autorização atual (inclusive ator em memória desatualizado), documentos, preservação da cota/dados originais, rollback, imutabilidade e concorrência. A disputa concorrente MySQL cria um schema descartável `escala_admin_race_*`, usando a conta de testes do CI com permissão de criação de schema; nunca utiliza dados de produção. O schema é eliminado após a disputa. SQLite usa arquivo temporário independente.

O limite de memória de 512 MB está apenas em `phpunit.xml` para a suíte acumulada de PDFs/planilhas; a configuração PHP de produção não foi alterada.

Validação local final (PHP 8.5, MySQL 8.4.7): SQLite com 493 testes aprovados e 3.927 assertions; MySQL com 492 aprovados, 3.925 assertions e somente o skip preexistente do teste de plano específico de SQLite. Os cinco bloqueios MySQL anteriores foram corrigidos na instrumentação: consultas reconhecidas pela gramática do banco e fingerprint da fixture relida após persistência. Nenhuma verificação foi removida ou alerta de segurança ignorado. Build Vite, 53 testes JavaScript, Pint e lint do DESIGN.md passaram.

O fluxo foi exercitado com Chrome/Playwright em banco SQLite descartável, em 1440×720, 390×720 e 320×720, além de 320×640 para confirmação e teclado. Foram conferidos bloqueios, validação, rolagem, ausência de overflow horizontal, foco no diálogo, nova confirmação após atualizar a revisão, registro de retificação/anulação e PDF administrativo. Não houve erros de console. Outros navegadores e os dados reais de produção não foram exercitados.
