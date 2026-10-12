# Central de Notificações no celular — 1.44.0

A estrutura expansível da versão 1.35.0 foi preservada. Inicialmente, cada card mostra data/hora, identificação de não lida, situação atual da troca, referência, evento histórico e resumo. Ver detalhes é a única ação no card fechado. Mais de um card pode permanecer aberto.

No painel expandido ficam Marcar como lida, Abrir módulo, mensagem completa e comprovante histórico. PDF oficial atualizado e Imprimir registro histórico continuam distintos. Marcar como lida aguarda a resposta do método existente, bloqueia envio repetido e devolve foco a Ver detalhes. Falhas mantêm o painel e o estado recebido do servidor. A leitura em massa mantém confirmação explícita e retorna ao título caso seu acionador desapareça.

## Dados e acesso

`NotificationsCenter`, `ShiftSwapReceipt`, Policies e rotas de PDF não tiveram suas regras alteradas. A situação atual continua consultada em lote e autorizada por registro. Notificações antigas conservam seu conteúdo, inclusive quando a troca já foi aprovada, rejeitada ou desfeita. Não há gravação ao renderizar ou expandir. A leitura explícita altera somente o estado de leitura existente; históricos e comprovantes permanecem intactos.

## Verificação

Testes PHP cobrem situações pendentes, aprovadas, rejeitadas e desfeitas, referência antiga, aviso sem comprovante, isolamento por usuário, paginação, consulta em lote, ausência de gravação no render e autorização de PDF. O teste de navegador `tests/browser/notification-receipts.mjs` verifica 320/390/1440 px, cards inicialmente fechados, um único controle compacto, abertura independente, leitura e retorno de foco, movimento reduzido, impressão isolada, PDF atualizado, navegação Livewire e ausência de sobreposição da barra inferior. Usa somente dados fictícios em banco descartável.

Os toasts continuam na região superior com área segura; o conteúdo conserva o espaço inferior da navegação mobile. Controles e ações documentais têm pelo menos 44 px. Acessibilidade e impressão são verificadas sem modificar o conteúdo histórico.
