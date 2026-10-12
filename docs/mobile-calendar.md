# Calendário no celular — 1.39.0

Ficou mais fácil consultar seus plantões por dia, semana ou mês, diretamente no celular.

Semana mostra domingo a sábado; Mês, o mês da data escolhida; Lista, os 30 dias seguintes incluindo a data escolhida. Os cartões são agrupados pela data de início, paginados em grupos de até 25 plantões e mostram os horários completos, duração e virada de dia. A última linha de um dia pode continuar na próxima página, com a data repetida.

Hoje, próximo plantão, trocas aprovadas e plantões sem profissional ficam identificados. Próximo plantão usa os filtros vigentes e, para profissionais, somente a própria atribuição. Se o próximo plantão estiver fora da consulta atual, Ir ao próximo plantão leva à sua data e à página que contém esse plantão, com os mesmos filtros. Um plantão iniciado anteriormente permanece na agenda de sua data de início; não é classificado como próximo.

Detalhes reutilizam a janela compartilhada. Profissionais podem consultar a escala do próprio setor e abrir Minha escala para as ações pessoais. Gerentes mantêm escala, análise de troca e preenchimento quando permitido pela Policy. IDs e filtros manipulados não ampliam o acesso.

Visualização, data, mês, categoria, setor e página fazem parte da URL e do contexto de retorno. Alterar filtros/período reinicia a página; refresh preserva o estado válido. O desktop mantém a grade mensal e a lista completa do dia. Nenhum cálculo de duração, cobertura ou regra de troca foi alterado; não há migrations.

## Validação

Execute `php artisan test`, `php vendor/bin/pint --test`, `node --test tests/js/*.test.mjs` e `npm run build`.

O script `tests/browser/mobile-calendar.mjs` usa apenas contas descartáveis e fixtures com turno noturno em 30/11/2026, plantão de 24h em 01/12/2026, descoberto em 30/11 e troca aprovada em 21/10. A fixture de paginação inclui 27 colegas em 30/11. Configure CALENDAR_MANAGER, CALENDAR_PROFESSIONAL, CALENDAR_PASSWORD e opcionalmente CALENDAR_URL, CALENDAR_SCREENSHOTS, PLAYWRIGHT_MODULE, PLAYWRIGHT_CHANNEL e AXE_MODULE. Verifica os dois perfis em 320, 390 e 1440 px, modos, datas, foco, ações, filtros, refresh, PWA, contraste/acessibilidade e overflow. A emulação não substitui verificação em aparelhos físicos.
