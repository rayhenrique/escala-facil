# Início profissional — 1.38.0

Agora você encontra seu próximo plantão e suas solicitações logo ao abrir o aplicativo.

No celular, o resumo antecede os filtros e informa data, início, término com virada de dia, duração, setor e categoria. Os detalhes abrem a janela compartilhada; Solicitar troca segue o fluxo de revisão existente. Ver escala mantém o mês e os filtros da URL.

Próximo plantão significa início igual ou posterior ao horário atual, situação Agendado e atribuição ao usuário autenticado. O resumo é independente dos filtros históricos. Ausências registradas não reaparecem como plantões próprios agendados; a última ausência é identificada separadamente. Plantões de 24 horas e intervalos de um minuto preservam a interpretação oficial de `ShiftPeriod`.

Pedidos recebidos aguardando resposta têm prioridade no celular. O contador inclui pedidos fora dos filtros e oferece limpeza para consultá-los. Até três pedidos enviados em andamento aparecem no resumo; as listas completas mantêm paginação SQL. Relações são carregadas em lote. Nenhuma alteração de banco acontece ao renderizar ou consultar detalhes.

## Verificação

`php artisan test`, `php vendor/bin/pint --test`, `node --test tests/js/*.test.mjs` e `npm run build`.

`npm run test:professional-home:browser` usa uma conta descartável com próximo plantão. Configure PROFESSIONAL_HOME_EMAIL, PROFESSIONAL_HOME_PASSWORD, opcionalmente PROFESSIONAL_HOME_URL, PROFESSIONAL_HOME_SCREENSHOTS, PLAYWRIGHT_MODULE, PLAYWRIGHT_CHANNEL e AXE_MODULE. Verifica 320, 390 e 1440 px, modal, solicitação de troca, revisão de aceite, paginação dos pedidos recebidos, teclado, foco, resumo independente do mês, refresh, navegação PWA e ausência de overflow. Não deve usar contas de produção.

Os testes PHP cobrem turno noturno, 24h, virada do mês, ausência, cadastro incompleto, IDs alheios e bloqueados, contrapartida ativa, pedidos recebidos/enviados, paginação e carregamento das relações. A emulação de viewport não substitui testes em um aparelho físico.
