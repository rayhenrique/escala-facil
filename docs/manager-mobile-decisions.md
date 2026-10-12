# Revisão gerencial de trocas — 1.42.0

No painel ou em Trocas, abra Analisar para consultar o pedido sem decidir. Aprovar abre uma confirmação com quem assume cada plantão; em permutas, ambos os movimentos são apresentados. Cotas e atribuições podem ser conferidas sem repetir toda a análise. Avisos e motivos que impedem a aprovação permanecem visíveis.

Rejeitar mantém a justificativa obrigatória. Voltar ou atualizar a análise conserva o texto; fechar pelo botão, Escape ou fundo da janela exige descarte explícito quando há alterações. Desfazer mostra os titulares atuais e os esperados, exige justificativa e usa a revisão segura existente. Plantões alterados, históricos ou dependentes continuam bloqueados e têm consulta administrativa separada.

## Segurança e estado

Nenhuma regra de negócio foi alterada. `DecideShiftSwapAction` e `RevertShiftSwapAction` continuam revalidando autorização, status, snapshot e atribuições dentro das transações com locks. Não há gravação ao abrir ou atualizar revisões. Apenas um painel de decisão permanece aberto. A interface bloqueia ações durante todas as requisições concorrentes e só fecha após resposta confirmada pelo servidor. Em falha de conexão, confira novamente o estado antes de decidir.

## Verificação

- `php artisan test`: decisões simples e mútuas, justificativas, permissões, alterações posteriores, snapshots desatualizados e concorrência real.
- `node --test tests/js/*.test.mjs`: foco, falhas e bloqueio enquanto há requisições concorrentes.
- `npm run test:manager-decisions:browser`: banco SQLite descartável com dados fictícios, em 320/390/1440 px; aprovação simples/mútua, rejeição, reversão, bloqueios, proteção de texto, redução de viewport e auditoria WCAG AA.

O teste de navegador exige `MANAGER_MOBILE_FIXTURE` (JSON com `email` e `cases`, por largura e modalidade, contendo IDs fictícios), `MANAGER_MOBILE_PASSWORD` e servidor local isolado em `MANAGER_MOBILE_URL`. Nunca use dados de produção. `PLAYWRIGHT_MODULE`, `PLAYWRIGHT_CHANNEL`, `AXE_MODULE` e `MANAGER_MOBILE_SCREENSHOTS` configuram o runtime e os artefatos. A viewport reduzida verifica o rodapé com pouco espaço; teclado virtual físico e Safari iOS ainda exigem validação no dispositivo.
