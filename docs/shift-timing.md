# Horários e duração dos plantões

## Resultado da auditoria

Antes de 1.22.0, a tabela guardava apenas `date`, `start_time` e `end_time`.
Cobertura e duplicação interpretavam término menor ou igual ao início como dia
seguinte. O cadastro e o XLSX, porém, rejeitavam horários iguais. Não havia
convenção que convertesse `07:00–07:01` em 24h. A instituição confirmou que
precisa representar 24h e turnos entre dias, sem essa convenção.

Telas e PDFs omitiam data de término e duração. Dobras e conflitos na cópia
podiam ignorar o dia seguinte e os meses vizinhos. O calendário já buscava o
dia anterior para calcular cobertura.

## Representação

`Shift::period()` / `ShiftPeriod` centralizam início, término, data de término,
duração em minutos e rótulos. `end_date` registra explicitamente o término nos
novos cadastros/importações. O fuso continua em `config/app.php`; a precisão
continua sendo de minuto, inclusive para horários SQL com segundos.

| Início | Término | Duração |
| --- | --- | --- |
| 31/01/2027 07:00 | 31/01/2027 07:01 | 1 minuto |
| 31/01/2027 19:00 | 01/02/2027 07:00 | 12h |
| 31/01/2027 07:00 | 01/02/2027 07:00 | 24h |
| 31/01/2027 23:59 | 01/02/2027 00:00 | 1 minuto |

Data de término vazia: horário final menor significa dia seguinte; maior,
mesmo dia. Horários iguais em novos cadastros exigem término explícito no dia
seguinte. O cadastro representa intervalos positivos de 1 minuto até 24h,
ampliando o limite técnico anterior inferior a 24h. Isso não define jornada
mínima, jornada autorizada ou limite institucional.

O XLSX conserva as seis colunas iniciais e acrescenta data de término e duração
calculada em minutos. O importador aceita seis, sete ou oito colunas. Duração
preenchida deve corresponder às datas/horários; vazia é calculada. Um valor 1440
nunca transforma um intervalo de 1 minuto em 24h. A exportação explicita o
término, inclusive para registros antigos. Duplicatas consideram o intervalo
efetivo, reconhecendo as representações implícita e explícita como iguais.

## Compatibilidade

A migration adiciona `end_date` nullable sem atualizar valores existentes.
Quando NULL, mantém a interpretação histórica: fim menor ou igual ao início
termina no dia seguinte. Eventuais registros antigos com horários iguais
continuam com 24h. Editar carrega seu término efetivo e salvar registra a data.

Nenhum `07:00–07:01` é convertido: permanece com 1 minuto. Correções de dados
incorretos devem ser revisadas individualmente pela gerência com a escala
original como evidência.

Comprovantes novos incluem datas completas e duração. Os antigos recebem
rótulos durante a leitura dos próprios valores registrados; notificações e
histórico não são regravados. Reverter a migration perde a data explícita;
faça backup antes. Esta entrega não executa deploy nem migra o banco de produção.

## Sobreposição, dobra, cobertura e carga horária

- Sobreposição usa `[início, término)`. Turnos que encostam não se sobrepõem.
  A cópia verifica dias e meses vizinhos e conflitos entre itens do lote.
- Dobra mantém a regra atual: sobreposição ou turnos consecutivos. Aceite e
  aprovação exibem aviso à gerência, sem novo bloqueio. Cadastro manual e XLSX
  não recebem uma regra nova de exclusividade.
- Cobertura divide intervalos por data e une sobreposições sem contar minutos
  duas vezes. 07:00–07:00 cobre 17h na data inicial e 7h na final; sozinho não
  marca nenhum desses dias como totalmente coberto. 00:00–00:00 cobre as 24h
  da data inicial.
- Dashboard e relatórios mantêm indicadores de **quantidade de plantões** e
  mês pela data de início. Essas contagens não são carga horária. A duração
  exibida representa o intervalo da agenda, não folha de pagamento.

Sem definição institucional: jornadas maiores que 24h, descanso mínimo,
limites mensais de horas, rateio entre meses, descontos de intervalos e
remuneração/adicional noturno. Nenhuma dessas regras foi criada. O indicador
“Próximas 72 horas” mantém o filtro por datas existente.

## Verificação

`tests/Feature/ShiftTimingTest.php` cobre meia-noite, virada do mês, duração
mínima, 24h explícitas, compatibilidade histórica, migration sem reescrita,
dobras nos dois sentidos, sobreposição, cobertura sem duplicação, cadastro,
calendário/painel, comprovantes e ida/volta do XLSX. Os testes existentes de
trocas, histórico, PDFs, duplicação e autorização também são executados.
