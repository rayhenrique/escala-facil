# Responder aos pedidos de troca

Ver detalhes abre uma janela responsiva com os dois plantões, período completo, participantes, cota e alertas. Aceitar mantém o fluxo atual: pedido passa de Aguardando profissional para Aguardando gerência, com escala intacta até aprovação.

## Recusa profissional

Não existia uma operação de recusa do destinatário. A versão 1.41.0 acrescenta DeclineShiftSwapAction, autorizada somente para profissional destinatário de pedido Aguardando profissional. Recusar exige confirmação, com motivo opcional limitado a 1000 caracteres, e encerra no status Rejeitada já existente. Não altera cotas ou atribuições. Rejeição pela gerência mantém suas Policies e justificativa obrigatória.

A migração aditiva inclui professional_response e professional_responded_at, ambos nulos para registros anteriores. Ela diferencia recusa profissional de rejeição gerencial em comprovantes atuais sem inferir respostas antigas. Evento declined é registrado na auditoria append-only e novas notificações guardam seu comprovante. Não há backfill ou atualização de notificações antigas. Rollback recusa remover metadados já utilizados: preserve a tabela para retorno do código e revise compatibilidade antes de operar. Instalação segue o comando de migração existente no deploy; nenhum deploy é executado nesta entrega.

## Revalidação e recuperação

RespondToShiftSwapAction mantém aceite e recusa em uma transação comum: bloqueia participantes e plantões em ordem de ID, depois pedido; relê perfil, destinatário e status. O fingerprint inclui as atribuições e horários dos dois plantões, compatibilidade, cota e alertas. Revisão desatualizada pede nova consulta; disputa concorrente é tratada com até três tentativas e motivo claro. Notificações e auditoria participam da mesma transação.

A cota do aceite usa o mês de criação da solicitação, não necessariamente o mês corrente. Ambos os plantões precisam conservar atribuição/status e compatibilidade para aceite. Conflitos e dobras são avisos informativos nesta etapa, sem inventar descanso mínimo ou mudar a aprovação gerencial. A recusa permanece disponível diante desses impedimentos operacionais, enquanto o pedido ainda aguarda o destinatário.

Uma interrupção de rede não confirma nem desfaz uma decisão. O formulário conserva os dados e oferece Atualizar detalhes. Se o servidor já concluiu, a consulta mostra a situação atual; uma repetição não gera nova decisão, histórico ou notificação.

## Validação

Testes PHP cobrem aceite/recusa simples e mútua, cotas, permissões, estado alterado, eventos/comprovantes imutáveis, ausência de gravações ao consultar e dois processos concorrentes. `npm run test:incoming-response:browser` usa banco e contas descartáveis, verifica mobile/desktop, teclado, WCAG AA via axe, resposta sem reload e interrupção antes/depois da persistência. Testes de viewport não certificam teclado virtual físico ou instalação iOS.
