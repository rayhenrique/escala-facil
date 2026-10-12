# Solicitação guiada de troca

A versão 1.40.0 mantém StoreShiftSwapRequest, CreateShiftSwapAction, Policies e limites existentes. O profissional escolhe plantão, modalidade, colega e contrapartida, revisa os movimentos e confirma. Voltar preserva entradas; alterar colega limpa uma contrapartida anterior.

Listas paginam dez resultados no banco. Busca por nome tem debounce de 300 ms; setor/categoria e cota são aplicados antes da paginação. Cotas contam participações aprovadas no mês da solicitação conforme regra vigente. Pedidos ativos são verificados em ambas as atribuições.

ShiftCoverage e ShiftPeriod sustentam os avisos de conflito/dobra, incluindo meia-noite e duração de 24h. Esses avisos não criam bloqueios institucionais na solicitação: aceite e aprovação continuam com suas validações. Não se presume intervalo mínimo de descanso.

O fingerprint do payload revisado impede confirmar escolhas diferentes das revisadas. A Action revalida no backend dentro da transação, bloqueando profissionais e plantões em ordem de ID. Conflitos de concorrência recebem até três tentativas de transação e orientação para revisar novamente; essa proteção segue o padrão existente das decisões. O aviso de sucesso e o comprovante aparecem após o retorno do servidor. Histórico e notificações seguem pelos serviços existentes. Nenhuma migration é necessária.

## Verificação

`php artisan test` cobre fluxo simples/mútuo, cotas 3/4 e 4/4, incompatibilidade, atribuição alterada, contrapartida ativa, ausência de gravações na revisão, paginação e processos concorrentes.

`npm run test:guided-swap:browser` usa contas descartáveis e verifica etapas em 320/390/1440 px, retorno das entradas, pesquisa, confirmação, foco/teclado, descarte explícito, layout e WCAG AA via axe quando disponível. O teste de interface não envia solicitações; os testes PHP verificam a persistência. Teclado virtual físico e instalação iOS não são certificados por emulação de viewport.

A janela compartilhada mantém uma referência estável ao elemento raiz para restaurar foco ao continuar editando após o aviso de descarte; handlers de botões filhos não mudam essa referência. O fluxo guarda o acionador móvel ou desktop para retorno após fechamento.
