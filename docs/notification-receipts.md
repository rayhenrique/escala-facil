# Situação atual e comprovantes das notificações

As notificações são registros históricos. Uma solicitação ou aceite mantém o texto e a situação originais mesmo depois de aprovada, rejeitada ou desfeita. A Central apresenta primeiro **Situação atual da troca**, obtida do pedido atual com autorização da Policy. Abaixo, **Evento histórico desta notificação** identifica o título e a mensagem que foram enviados.

O comprovante incorporado é **Comprovante histórico da notificação**, com **Situação no momento deste registro**. Imprimir registro histórico conserva esse retrato e identifica a impressão como histórica; não transforma um aceite antigo em aprovação atual. **PDF oficial atualizado** é uma consulta separada ao controlador existente, que usa a situação atual e a decisão correspondente. Os PDFs antigos já salvos não são modificados.

## Caso TROCA-000106

Um aceite antigo pode registrar Aguardando gerência enquanto o pedido atual está Aprovado. Isso não indica decisão pendente: a Central mostra Situação atual da troca: Aprovada como informação principal e Aguardando gerência somente no registro histórico. O teste reproduz o pedido #106 com usuários e plantão de teste, aceite e aprovação reais pelas Actions; não depende de acesso à produção. Rejeições e reversões seguem a mesma separação: Rejeitada ou Desfeita atuais, eventos anteriores intactos.

## Consulta e permissões

Uma consulta carrega os pedidos da página, e outra carrega os responsáveis em lote. A Policy usa os IDs dos participantes, sem consultas por notificação. A referência antiga TROCA-000106 pode resolver o pedido quando o ID não está no payload; referências inválidas não são interpretadas como números de pedidos. Quando o pedido não existe ou não pode ser consultado pela conta, a situação atual é Indisponível para consulta e o link ao PDF atual não é exibido. O histórico da notificação recebida permanece disponível.

Abrir a Central, atualizar a página, paginar ou imprimir não escreve no banco. Os payloads, eventos, dados dos plantões e indicadores permanecem intactos. Marcar como lida e Marcar todas como lidas são ações explícitas e alteram somente os metadados de leitura das notificações da própria conta. Paginação continua com dez notificações por página.

## Verificação

`NotificationReceiptStatusTest` cobre #106, todos os eventos e estados, reversão preservando notificações antigas, consulta em lote, paginação, referência antiga, autorização, indisponibilidade e ausência de comandos SQL de escrita durante renderização. Os testes anteriores de notificações e imutabilidade permanecem aplicáveis. A revisão visual deve conferir desktop e celular, impressão do registro histórico e PDF oficial atual, sem disparar decisões ou alterar notificações reais.

A regressão de navegador `npm run test:notification-receipts:browser` consulta duas páginas, confere os estados atuais e históricos, acessa o PDF e inspeciona o iframe da impressão sem abrir diálogo nativo. Exige banco descartável e `NOTIFICATION_RECEIPTS_FIXTURE` (JSON com managerEmail e statuses, mapa de IDs para rótulos atuais; inclui #106 aprovado, #107 rejeitado e #108 desfeito, com aceites históricos na conta gerencial e mais de dez notificações), `NOTIFICATION_RECEIPTS_QA_PASSWORD`, com opções `NOTIFICATION_RECEIPTS_URL`, `NOTIFICATION_RECEIPTS_SCREENSHOTS`, `PLAYWRIGHT_MODULE`, `PLAYWRIGHT_CHANNEL` e `AXE_MODULE`. Não marca notificações como lidas nem toma decisões.
