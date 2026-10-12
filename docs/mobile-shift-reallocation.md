# Realocação assistida no celular — 1.43.0

No painel ou no calendário, abra Preencher plantão. O cartão mostra data, início, término, duração, setor e categoria. Pesquise um nome na lista de profissionais elegíveis, selecione uma opção e use Revisar alteração. A revisão mostra o profissional anterior e o escolhido, a mudança para Agendado e os alertas. Confirmar realocação executa a operação; Voltar à seleção conserva a escolha e a pesquisa.

A busca ignora acentos e filtra apenas o resultado da análise original, sem recalcular disponibilidade a cada tecla. Ela diferencia ausência de correspondência de ausência de elegíveis. Os profissionais com sobreposição continuam excluídos; dobras continuam sendo avisos conforme a regra institucional existente. Nenhum critério de atribuição foi criado.

## Confirmação e integridade

`ShiftReallocation` e `ReallocateShiftAction` permanecem responsáveis por elegibilidade, períodos, conflitos, snapshots, transação e locks. As regras e os registros anteriores não foram alterados. A revisão não grava no banco. Se cadastro, horários, disponibilidade ou status mudarem, o servidor recusa a confirmação antiga. A janela e a mensagem permitem conferir novamente o plantão, sem indicar sucesso indevido. Apenas o sucesso confirmado fecha a janela e atualiza os indicadores.

## Celular e teclado

O rodapé fica separado do conteúdo rolável e a janela bloqueia o fundo, incluindo a barra inferior. A opção `fitViewport` da biblioteca ajusta esta janela à altura e ao deslocamento de `visualViewport`, quando disponível, com fallback para `dvh` e safe area. Os listeners são removidos ao destruir a janela. Os demais modais conservam o comportamento anterior. Foco preso, Escape, retorno ao acionador e descarte explícito reutilizam a biblioteca.

## Validação

- Testes PHP: ausência de elegíveis, pesquisa com acentos e limite de tamanho, preservação da seleção, conflitos, turnos noturnos e 24h, autorização, histórico e concorrência real.
- Testes JavaScript: adaptação à área visual do teclado, limpeza de listeners, ciclo de requisição pelo calendário e bloqueio de ações em andamento.
- `tests/browser/shift-reallocation.mjs`: 320/390/1440 px, WCAG AA, teclado e foco, pesquisa, prévia, cancelamento, viewport reduzida e teclado sobreposto simulado, vazio pelo calendário mobile, operação lenta, duplo clique e revisão concorrente.

O navegador usa somente um banco descartável com dados fictícios. As variáveis `DASHBOARD_URL`, `DASHBOARD_QA_EMAIL`, `DASHBOARD_QA_PASSWORD` e `REALLOCATION_FIXTURE` configuram o servidor e a massa de teste. A fixture JSON informa `night`, `day24`, `date24`, `professionalName` e, opcionalmente, `empty`, `dateEmpty`, `emptySector`. Teclado físico e Safari iOS ainda precisam de conferência no dispositivo; a validação automatizada simula a geometria da área visível.
