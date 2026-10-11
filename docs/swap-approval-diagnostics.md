# Diagnóstico de aprovação de trocas

## Caso informado: pedido #89

A imagem fornecida mostra troca mútua, solicitante com 1/4 e substituta com 3/4 participações no mês 10/2026, ausência de avisos de horário e botão Aprovar desabilitado. Ela mostra a versão instalada 1.31.0; a correção é preparada sobre a main 1.32.0 como PATCH 1.32.1, sem deploy.

Essas cotas não bloqueiam por si: a aprovação acrescenta uma participação a cada pessoa, chegando a 2/4 e 4/4. Uma permuta transfere dois plantões, mas conta um pedido aprovado para cada participante. Não há convenção de converter 07:00–07:01 em 24h; o intervalo de um minuto foi preservado, sem modificar horários reais.

Foi consultado exclusivamente o arquivo SQLite local em modo somente leitura, com `query_only`. Essa base não contém `shift_swaps`; ela não demonstra o estado de produção. A causa exata do registro #89 não foi comprovada. Com cotas inalteradas, os demais impedimentos da Policy são atribuição/status do plantão original ou da contrapartida e vínculos indisponíveis. Mudanças entre a leitura das cotas e a avaliação da antiga Policy também podiam produzir uma análise contraditória. Nenhum registro real foi alterado ou aprovado.

## Condições que impedem aprovação

| Condição | Razão apresentada | Orientação |
| --- | --- | --- |
| Autor não é gerente | Somente gerentes podem aprovar | Usar perfil autorizado; continua sendo erro de autorização |
| Pedido não aguarda gerência | ID e situação atual do pedido | Atualizar fila e consultar decisão/aceite já registrado |
| Solicitante/substituto indisponível | Participante indisponível | Conferir cadastro e vínculos |
| Plantão original/contrapartida indisponível | Papel e ID do plantão inexistente | Conferir exclusão ou vínculos do pedido |
| Original não pertence mais ao solicitante | Papel/ID, profissional esperado e atribuição atual | Revisar escala; se a mudança foi intencional, rejeitar com justificativa e criar nova solicitação |
| Contrapartida não pertence mais ao substituto | Mesmos detalhes para a contrapartida | Mesma orientação, sem ignorar a segunda atribuição |
| Um dos plantões não está Agendado | Papel/ID e situação atual | Conferir ausência ou alteração na escala |
| Um participante tem pelo menos quatro participações aprovadas | Nome, quantidade, limite e mês de referência | Conferir pedidos aprovados do mês de criação da solicitação |
| Dados relevantes mudaram após revisão | Análise desatualizada e impedimentos atuais, se houver | Usar Atualizar análise e conferir novamente antes da confirmação |
| Operação concorrente não concluiu após tentativas | Outra operação está alterando os registros | Atualizar análise após a conclusão da operação concorrente |

`ShiftSwapApproval` fornece os impedimentos de domínio e cotas para `ShiftSwapPolicy::approve()` e `ShiftSwapAnalysis`. A Policy retorna razões específicas com `Response::deny`; o componente preserva essas razões. A revisão apresenta as duas atribuições e separa impedimentos obrigatórios de avisos de horário. Cotas da tela e elegibilidade usam o mesmo resultado, sem consultar novamente a cota para decidir o estado do botão.

## Regra mensal preservada

- Conta somente `approved`, com `requester_id = pessoa` **ou** `substitute_id = pessoa`, sem somar duas vezes o mesmo pedido.
- Referência: `created_at` da solicitação, do primeiro dia às 00h até antes do primeiro dia do mês seguinte, no fuso configurado no aplicativo. Não é o mês do plantão nem o mês da decisão.
- Pedidos pendentes, rejeitados e desfeitos não consomem cota. Desfazer uma aprovação libera essa participação, conforme o fluxo existente.
- 3/4 permite a quarta aprovação; 4/4 impede outra. `User::approvedSwapCount()` centraliza a contagem; `hasReachedSwapLimit()` mantém a comparação com quatro. Intervalos por data aproveitam os índices existentes.

## Conflitos, dobras e análise desatualizada

Sobreposição real de intervalos e plantões consecutivos são avisos na regra vigente de aprovação de trocas. Não foram transformados em novos bloqueios institucionais. O aviso identifica profissional, plantão recebido e plantão já atribuído. As duas atribuições liberadas pela permuta são excluídas da busca de conflitos. Ausência de conflito não implica que atribuições, status ou cotas estejam válidos.

Na confirmação, o backend mantém transação, locks ordenados de participantes e dos dois plantões, lock do pedido, repetição para deadlocks e revalidação da Policy. A comparação da revisão considera vínculos/status do pedido, horários/atribuições/setor/categoria dos plantões, dados pertinentes dos participantes, cotas e plantões envolvidos nos alertas. Senha, token de sessão, versão já lida e timestamps sem mudança operacional não invalidam mais a revisão. Alterações relevantes continuam exigindo nova análise; não há atualização silenciosa que confirme dados diferentes dos revisados.

Decisões já concluídas podem ser consultadas sem oferecer nova aprovação/rejeição. Atualizar análise apenas consulta novamente os dados e reinicia a escolha, sem executar decisão. Notificações, comprovantes, histórico e transações continuam no fluxo existente.

## Conferência autorizada do registro real

Após instalar a correção pelo processo habitual, abra o pedido #89 em Trocas ou na fila do painel e consulte **Conferência das atribuições** e **O que impede a aprovação**. Não há necessidade de executar UPDATE nem de forçar status/atribuição para investigar.

Se for necessária conferência técnica antes disso, execute a consulta abaixo somente no ambiente de dados autorizado; ela não modifica registros:

```sql
SELECT s.id, s.status, s.created_at, s.requester_id, s.substitute_id,
       s.shift_id, a.user_id AS original_user_id, a.status AS original_status,
       s.substitute_shift_id, b.user_id AS counterpart_user_id,
       b.status AS counterpart_status
FROM shift_swaps s
LEFT JOIN shifts a ON a.id = s.shift_id
LEFT JOIN shifts b ON b.id = s.substitute_shift_id
WHERE s.id = 89;
```

Para cada participante, confira as participações aprovadas com o mesmo mês de criação do pedido. Os parâmetros `:id_requester` e `:id_substitute` recebem o mesmo ID da pessoa:

```sql
SELECT COUNT(*) AS approved_participations
FROM shift_swaps
WHERE status = 'approved'
  AND created_at >= :month_start
  AND created_at < :next_month_start
  AND (requester_id = :id_requester OR substitute_id = :id_substitute);
```

## Verificação

PHP cobre permuta sintética #89 com 1/4 e 3/4, bloqueio em 4/4, ambas as atribuições modificadas, ambos os status incompatíveis, vínculos ausentes, pedido já decidido, limites mensais, estados que não consomem cota, mudanças irrelevantes de sessão, análise desatualizada e conflitos informativos. O teste de processos concorrentes confirma apenas uma decisão, uma notificação por participante e transferência atômica dos dois plantões.

`npm run test:swap-blockers:browser` usa base sintética isolada e fixture JSON em `SWAP_BLOCKERS_FIXTURE`, com `managerEmail`, `valid`, `quota`, `changed`, `stale` (IDs dos pedidos). `SWAP_BLOCKERS_QA_PASSWORD` contém senha somente de QA; `SWAP_BLOCKERS_URL` aponta para o servidor local; `SWAP_BLOCKERS_SCREENSHOTS` recebe capturas. Playwright e axe usam `PLAYWRIGHT_MODULE`, `PLAYWRIGHT_CHANNEL` e `AXE_MODULE`, como nas demais regressões do projeto. Há verificações em 320, 390 e 1440px de explicações, estado de botões, foco, confirmação e decisão desatualizada.

Pedidos já decididos mostram o impedimento pela situação do pedido. A transferência de titularidade e a participação consumida por uma aprovação concluída não são apresentadas como irregularidades adicionais.
