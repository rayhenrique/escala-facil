# Consulta gerencial de trocas

**Aguardando decisão** mostra os pedidos aceitos pelo substituto que aguardam a gerência. **Histórico de trocas** consulta todos os pedidos: aguardando profissional, aguardando gerência, aprovados, rejeitados e desfeitos, inclusive antigos. A consulta não é limitada aos dez registros mais recentes; cada página contém até dez resultados, com contagem total e paginação SQL.

Pesquisa geral procura solicitante ou substituto. Os campos separados de solicitante e substituto são combinados com a pesquisa geral, situação, setor, categoria e período. Nomes aceitam texto parcial; `%` e `_` são literais. Setor, categoria e datas referem-se ao início do plantão solicitado, com ambos os dias incluídos. A contrapartida de uma troca mútua é mostrada mesmo quando inicia fora desse período. Ordenação usa a data de criação do pedido, com ID como desempate: mais antigas primeiro na fila, mais recentes no histórico, com escolha pelo usuário.

Consulta, filtros, ordenação e página persistem na URL. Mudar filtros reinicia a paginação; limpar filtros conserva a visão atual. Selecionar uma situação diferente de Aguardando gerência na fila abre o histórico. Links antigos de situação e links por pedido continuam funcionando. Detalhes e retorno contextual preservam a pesquisa de origem, sem depender exclusivamente do histórico do navegador.

Cada cartão mostra pedido, modalidade, situação, início, término e duração calculada pelo intervalo existente. Trocas mútuas mostram ambos os plantões. Profissional previsto descreve o pedido, inclusive quando foi rejeitado ou desfeito; profissional atual descreve a atribuição atual da escala. A data de solicitação e a última atualização do registro são distintas da data do plantão. Não se reconstrói uma data de decisão histórica que não esteja registrada.

Datas, horários e nomes exibidos na listagem vêm dos cadastros atuais relacionados ao pedido. Quando esses cadastros foram alterados, os comprovantes imutáveis das notificações conservam os dados registrados no evento; a listagem não inventa um snapshot anterior.

Detalhes oferecem comprovante da situação atual e PDF pelo controlador existente. Comprovantes enviados nas notificações anteriores permanecem imutáveis e não são substituídos por essa consulta. O histórico operacional de alterações continua disponível pelo link Histórico do registro.

## Reversão preservada

Desfazer aparece somente quando a Policy permite. As mesmas condições existentes agora retornam uma mensagem específica: gerente, pedido aprovado, plantão principal existente, atribuído ao substituto do pedido, agendado, data de início igual ou posterior a hoje, ausência de outra troca pendente e de qualquer registro de ausência nesse plantão. Esta entrega não muda o critério de data, não acrescenta critérios para a contrapartida e não altera a Action de reversão, locks, transação, cotas, notificações ou comprovantes.

As condições de existência são carregadas com `withExists` para os plantões da página, evitando consultas por cartão. A ação sob lock carrega os dados atuais e consulta essas condições novamente; uma permissão exibida anteriormente não concede autorização para uma operação que passou a ser inválida.

## Acesso e validação

Somente gerentes acessam esta listagem. A Policy de consulta também protege detalhes e PDFs; profissionais conservam os módulos próprios e acesso aos comprovantes em que participam, sem acesso ao histórico gerencial ou a pedidos de terceiros. Valores de URL e filtros são validados; ordenação usa apenas opções permitidas. Relações são carregadas em lote; índices existentes de criação e situação/criação atendem os caminhos de ordenação, sem nova migração de dados.

Regressões: `ManagerSwapHistoryTest`, filtros e navegação operacional, reversão e imutabilidade de comprovantes. `npm run test:swap-history:browser` exige banco descartável e `SWAP_HISTORY_FIXTURE`, `SWAP_HISTORY_QA_PASSWORD`, com opções `SWAP_HISTORY_URL`, `PLAYWRIGHT_MODULE`, `PLAYWRIGHT_CHANNEL`, `AXE_MODULE` e `SWAP_HISTORY_SCREENSHOTS`. O navegador consulta, filtra e abre/cancela prévias sem decidir ou desfazer pedidos.
