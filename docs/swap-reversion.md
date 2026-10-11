# Revisão administrativa de trocas

Gerência → Trocas → Histórico de trocas → Desfazer abre uma revisão de qualquer pedido aprovado, inclusive antigo. Abrir a revisão não altera a escala. A prévia apresenta cada plantão, horário, duração, setor, categoria e profissional atual → profissional original. Trocas mútuas restauram os dois plantões na mesma transação; trocas simples devolvem o plantão ao solicitante.

A confirmação exige justificativa de 3 a 1.000 caracteres e uma prévia válida emitida pelo servidor. Os participantes são bloqueados por ID, depois os plantões por ID e por fim o pedido, na mesma ordem usada na aprovação. O estado, a autorização atual do gerente e a disponibilidade dos profissionais são conferidos novamente sob locks. Operações simultâneas são serializadas; falhas, inclusive de notificação, revertem toda a transação.

## Quando a restauração é permitida

A aprovação deve ter evidência confiável das atribuições e horários originais. Novas aprovações guardam esse retrato no evento imutável de auditoria, antes da transferência. Aprovações anteriores podem usar a reconstrução do histórico append-only completo, desde a criação dos plantões e do pedido. Dados ausentes não são inventados nem preenchidos com o estado atual.

Ambos os plantões devem continuar agendados, ainda não iniciados, com os mesmos horários, datas, setor, categoria e atribuições esperadas após a aprovação. Nenhum deles pode ter ausência, pedido pendente em qualquer lado da troca ou alterações posteriores de escala. Realocações, trocas encadeadas e mudanças depois restauradas exigem análise administrativa. Os profissionais originais precisam continuar ativos e compatíveis; sobreposição real de horários bloqueia a confirmação, inclusive na virada do dia ou mês e em plantões de 24 horas. Turnos consecutivos geram aviso de dobra, sem inventar limites de descanso ou carga horária.

## Casos que precisam de correção separada

Plantões iniciados, passados, concluídos, alterados, dependentes de outras operações ou sem evidência completa não são restaurados automaticamente. A opção **Analisar correção administrativa** abre uma consulta separada com orientações e links filtrados para o histórico do pedido e de cada plantão. Compare os comprovantes originais e as alterações posteriores, identifique dependências e confirme o que efetivamente ocorreu. A análise é somente leitura; não cria aprovação forçada nem corrige fatos históricos. O procedimento institucional de correção desses casos deve ser definido antes de introduzir uma operação de escrita específica.

## Histórico, comprovantes e cotas

O registro de reversão guarda autor, data/hora, justificativa, referência da aprovação original, operação e retratos anterior/posterior. É append-only no modelo e no banco (SQLite/MySQL), sem exclusão em cascata. A justificativa aparece no histórico operacional, nos novos comprovantes e notificações. Os comprovantes originais permanecem intactos; PDFs da reversão mostram o retorno correto de cada profissional.

O pedido passa de Aprovado para Desfeito. A regra vigente conta somente trocas aprovadas no mês da solicitação, por participante; a reversão libera essa cota para ambos. Escala, calendário e relatórios consultam as atribuições e situações atuais, sem caches intermediários de cobertura. As notificações em banco são criadas na transação e ficam visíveis após o commit.

Nenhum dado de produção é alterado por esta implementação. Instalação futura precisa aplicar a migration de registros de reversão; não executar rollback destrutivo quando houver registros. Retenção e acesso seguem o histórico operacional: gerência autorizada, mínimo de dados, sem senhas, tokens ou anexos; justificativas devem evitar informações médicas ou pessoais desnecessárias. Expurgo exige procedimento institucional e técnico auditável, não exclusão pela interface.

## Verificação

Testes cobrem restauração simples/mútua, proveniência antiga completa ou ausente, início do plantão, passagem de mês, 24 horas, dependências, ausência na contrapartida, realocação posterior, encadeamento, conflitos, dobra informativa, justificativa, autorização atual, prévia desatualizada, rollback e imutabilidade no banco. Dois processos independentes confirmam simultaneamente o mesmo pedido: apenas um registra a reversão e notifica.

Regressão de interface: `npm run test:swap-reversion:browser` consulta e cancela prévias em 320, 390 e 1440 pixels, verifica validação da justificativa, foco, Escape e análise administrativa. Exige `SWAP_REVERSION_FIXTURE` (JSON com `mutual: {id}`, `blocked: {id, counter}` e `managerEmail`), `SWAP_REVERSION_QA_PASSWORD`, e banco descartável com uma troca mútua aprovada pela Action, íntegra e futura, e outra com contrapartida modificada. Opções: `SWAP_REVERSION_URL`, `PLAYWRIGHT_MODULE`, `PLAYWRIGHT_CHANNEL`, `AXE_MODULE`, `SWAP_REVERSION_SCREENSHOTS`. Não envia justificativa válida nem confirma reversões.
