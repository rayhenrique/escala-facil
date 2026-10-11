# Indicadores operacionais por setor

A gerência consulta os indicadores em Relatórios, nas abas Visão Consolidada e Cobertura e Indicadores. Mês, setor e categoria usam os filtros existentes. Cada setor cadastrado aparece, inclusive com zero movimentos, em páginas de dez setores. Limpar filtros reinicia as páginas. A consulta profissional continua restrita ao seu setor e às listas já disponíveis; não recebe os novos indicadores gerenciais nem o CSV.

## Definições e períodos

| Indicador | Numerador / quantidade | Denominador | Período |
| --- | --- | --- | --- |
| Total de plantões | Registros de plantão | Não se aplica | Data de início do primeiro ao último dia do mês selecionado |
| Cobertos | Agendados com profissional atribuído | Não se aplica | Mesmo conjunto de plantões |
| Cobertura | Cobertos × 100 | Total de plantões | Mesmo conjunto, situação atual, percentual arredondado ao inteiro |
| Sem cobertura | Total menos cobertos | Não se aplica | Mesmo conjunto, situação atual |
| Descobertos | Plantões com situação Descoberto | Não se aplica | Mesmo conjunto, situação atual |
| Ausência informada | Plantões com situação Ausência informada | Não se aplica | Mesmo conjunto, situação atual |
| Pedidos de troca | Pedidos criados no período, incluindo todos os estados ou o estado atual filtrado | Não se aplica | Criação >= primeiro dia às 00h e < primeiro dia do mês seguinte às 00h, no fuso configurado no aplicativo |
| Registros de ausência | Registros vinculados aos plantões do conjunto, inclusive após realocação | Não se aplica | Mês de início do plantão, independentemente da data de comunicação da ausência |

Sem plantões, a proporção é indefinida e aparece como “—”, enquanto quantidades são zero. Agendado sem profissional não é coberto. Um plantão noturno ou de 24h iniciado no último dia conta uma vez no mês inicial. Não são calculadas horas, continuidade de atendimento, capacidade mínima ou desempenho profissional. Não se converte contagem em carga horária.

## Situação atual e histórico

A primeira tabela mostra a situação dos plantões no instante da consulta, inclusive ao selecionar um mês passado. A segunda mostra registros do período com as definições acima, não apenas pendências atuais. Uma ausência permanece contada após preenchimento do plantão. Não se infere que a aprovação aconteceu no mês de criação do pedido.

Setor e categoria vêm do plantão atualmente vinculado ao registro; não existe snapshot completo desses vínculos anteriores à auditoria. Exclusões permitidas e seus efeitos de cascata continuam obedecendo às regras existentes. O relatório não reconstrói uma fotografia histórica nem inventa dados ausentes. Para investigar mudanças, a gerência consulta o histórico operacional. O vínculo ao mês de início das ausências preserva a semântica do módulo existente.

## Filtros e exportação

PDF consolidado inclui os indicadores por setor e mantém mês/setor/categoria, busca da lista de profissionais e situação da lista de trocas. Busca por profissional só restringe essa lista: não redefine o conjunto de plantões nem os indicadores por setor. Situação da troca restringe os pedidos na segunda tabela por setor e a lista de trocas; o resumo consolidado mantém todos os estados com seus próprios contadores.

CSV por setor exporta todas as páginas do agregado, com mês/setor/categoria/situação da troca, definições e instante da geração. Não inclui nomes de profissionais, razões de ausência ou anexos. Nomes de setor que podem ser interpretados como fórmula de planilha são neutralizados. Filtros inválidos na exportação são rejeitados, nunca descartados silenciosamente para ampliar o resultado. PDF para afixar continua com os filtros próprios de mês/setor/categoria e sem alterações.

## Consultas e validação

Plantões, pedidos e ausências são agregados separadamente em SQL, por ID de setor, antes de unidos aos setores. Isso evita multiplicação de contagens quando um plantão tem vários registros. Não há hidratação de todos os plantões nem consulta por setor na nova tabela. A tela pagina dez setores e CSV lê lotes de 200. Os filtros por data usam intervalos, aproveitando os índices existentes em `shifts.date` e `shift_swaps.created_at`; nenhum índice adicional foi necessário. Para grandes exportações agregadas, prefira CSV; o PDF consolidado mantém as listas detalhadas existentes.

Regressão: contagens com múltiplos eventos, limites mensais, turnos noturnos e de 24h, agendado sem profissional, realocação, combinação de filtros, setores sem dados, paginação, CSV, PDF, validação e autorização. QA no navegador usa apenas banco descartável com dados sintéticos.

### Regressão no navegador

Execute `npm run test:reports:browser` com `REPORT_URL` apontando para servidor local isolado, `REPORT_QA_PASSWORD` para senha sintética compartilhada e `REPORT_FIXTURE` para JSON com `managerEmail`, `professionalEmail`, `month`, `sector`, `category` e `otherSector`. A fixture deve ter 13 setores; Emergência QA, na categoria indicada, tem dois plantões iniciados em outubro/2026 (um coberto de 24h na virada do mês e um descoberto noturno), um pedido criado no mês e uma ausência vinculada ao descoberto. Setembro/2026 fica sem movimentos. Nenhum dado de produção deve ser usado.

`PLAYWRIGHT_MODULE` pode apontar para a instalação local de Playwright; `PLAYWRIGHT_CHANNEL` seleciona o navegador. `REPORT_SCREENSHOTS` recebe capturas e exportações; `AXE_MODULE` aponta para axe-core para verificar WCAG AA e boas práticas. O script verifica 320, 390 e 1440px, filtros, paginação, refresh, download, período vazio e bloqueio do acesso profissional.
