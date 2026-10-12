# Feedback operacional

A versão 1.46.0 reutiliza os componentes de toast, loading e janela. `UiFeedback` mantém status/error da sessão e acrescenta uma identidade por operação. Chamá-lo somente após o resultado efetivo, nunca em render. `flash-notifications` entrega a mensagem ao outlet único; `operational-feedback.js` deduplica a identidade durante a sessão de navegação. Autenticação mantém suas mensagens específicas inline.

O interceptor Livewire registra falhas sem ler ou mostrar o corpo técnico da resposta. 401/419 orientam entrar novamente; 403 orienta conferir acesso; 404/409 pede atualização e revisão; 422 preserva validação de campos. Falhas de rede/servidor durante alterações têm resultado incerto: conferir o registro antes de repetir. Não há fila ou reenvio de aprovação, rejeição ou reversão.

Verificar conexão consulta GET /up, sem reenviar operações. Voltar online não confirma sucesso. Atualizar consulta é uma ação explícita de leitura para falhas de leitura; revisões operacionais continuam nos botões existentes. Expiração preserva o texto na tela até atualização deliberada, sem armazenar senhas em storage. Recarregar ou sair continua sujeito ao comportamento de sessão anterior.

Botões submit compartilham o bloqueio do formulário Livewire, evitando competir com wire:loading.attr=disabled. Botões de clique conservam seu bloqueio específico. Formulários com anexos acompanham eventos de upload no próprio formulário e liberam o botão quando o envio termina, falha ou é cancelado. O cancelamento de ativações duplicadas é uma proteção de interface e não altera as garantias transacionais das Actions.

Avisos ficam inline em modais e autenticação; fora dessas áreas usam o outlet seguro acima da navegação inferior. Componentes de estado vazio mantêm as mensagens próprias de cada módulo.

## Verificação

Execute php artisan test, vendor/bin/pint --test, npm run build e node --test tests/js/*.test.mjs. O script npm run test:feedback:browser requer FEEDBACK_FIXTURE (JSON com email), FEEDBACK_PASSWORD e FEEDBACK_URL de um ambiente descartável. PLAYWRIGHT_MODULE, PLAYWRIGHT_CHANNEL, AXE_MODULE e FEEDBACK_SCREENSHOTS são opcionais. Nunca apontar a teste de latência para produção: a criação sintética é confirmada antes de atrasar a resposta.

O navegador verifica 320, 390 e 1440 px: validação sem sucesso, resposta atrasada, duplicação, erro de rede, verificação sem reenvio, erro sanitizado, sessão expirada e conflito. Simula também falha numa decisão gerencial sem executar a decisão, e preservação de dados no login. Auditoria axe cobre os estados operacionais autenticados; testes não substituem avaliação em aparelhos físicos ou com leitores de tela reais.
