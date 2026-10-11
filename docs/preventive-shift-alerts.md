# Alertas preventivos de plantões

## Regras preservadas

Antes desta entrega, criação/edição exigiam perfil Profissional e vínculos compatíveis, mas permitiam sobreposição. A realocação já recusava sobreposição e exibia dobra como aviso. Não havia regra configurada de descanso mínimo, horas máximas ou autorização automática de plantões consecutivos. Essa distinção permanece:

| Resultado | Criação e edição | Realocação |
| --- | --- | --- |
| Perfil, setor ou categoria incompatíveis | Bloqueio obrigatório | Bloqueio obrigatório |
| Sobreposição real `[início, término)` | Aviso que exige revisão e confirmação | Bloqueio obrigatório existente |
| Plantões consecutivos, sem sobreposição | Aviso de dobra, sem autorização automática | Aviso de dobra, sem autorização automática |
| Sem profissional ou situação diferente de Agendado | Aviso de que o plantão não contará como cobertura | Só permite preencher plantão descoberto, conforme Policy existente |
| Prévia ou registro desatualizado | Revalidar e revisar novamente | Reabrir seleção/prévia |

A sobreposição não passou a ser uma proibição institucional para criação/edição. Torná-la bloqueio nesses fluxos exige requisito documentado da instituição. Os avisos informam o ID, período, duração do plantão envolvido e recomendam rever horários/profissional ou conferir autorização de dobra.

## Intervalos e fontes

`ShiftAssignmentAnalysis` centraliza compatibilidade, janela de consulta, conflitos e avisos. `SaveShiftRequest` e `ShiftReallocation` reutilizam essa análise. `ShiftPeriod` calcula os intervalos reais com datas de término e a interpretação legada, inclusive madrugada, virada de mês e 24h. Terminar no instante em que outro começa é consecutividade, não sobreposição; um intervalo de um minuto é um minuto. Edição exclui apenas o ID do próprio registro.

`ShiftCoverage` continua definindo cobertura como situação Agendado com profissional atribuído. A alteração não redefine indicadores, não inventa critérios mínimos de pessoal e não transforma contagem de plantões em carga horária. Registros de ausência/descobertos não entram como compromissos ativos de horário.

## Revisão e concorrência

Salvar um formulário válido sem avisos conclui a operação. Com avisos, o formulário mostra a prévia sem gravar e oferece **Revisar dados** ou **Confirmar com os avisos**. Retornar conserva os valores. A prévia é protegida no Livewire e compara dados normalizados, cadastro e agenda; alterações relevantes pedem nova revisão.

`CreateShiftAction`/`UpdateShiftAction` revalidam Form Request, Policy, cadastro e disponibilidade na transação, com locks ordenados nos usuários e nos plantões consultados. O lock do usuário serializa atribuições mesmo quando não existe nenhum plantão na janela. A edição mantém a assinatura original contra sobrescrita. Realocação conserva sua confirmação/fingerprint e revalidação. Importação e cópia usam a mesma ordem de locks dos profissionais, preservando seus critérios atuais, sem acrescentar etapa de avisos nesses fluxos em lote.

Falhas de validação, prévia desatualizada ou concorrência não deixam uma alteração nem evento de sucesso na auditoria. Conflitos transitórios são apresentados como necessidade de revisar a escala. A interface compartilha foco, estado ocupado e recuperação de erro de transporte com os modais operacionais existentes. Cancelar/Escape ficam indisponíveis enquanto a requisição está em andamento. Notificações e comprovantes seguem os caminhos atuais.

## Limites institucionais pendentes

Não há limite oficial configurado de horas ou descanso para aplicar nesta versão; nenhum valor foi presumido. Antes de parametrizar avisos/bloqueios de carga horária, a instituição precisa fornecer: escopo por profissional/setor/categoria, período de apuração (civil ou móvel), rateio de turnos entre dias/meses, intervalos descontados, limites e unidades, vigência, exceções autorizadas e classificação obrigatória/informativa. Esses parâmetros só devem ser implementados e ativados após definição institucional, com testes correspondentes. Aviso de dobra não comprova conformidade trabalhista nem substitui autorização.

## Verificação

PHPUnit cobre madrugada, mês, 24h, legado, minuto mínimo, consecutividade nos dois sentidos, ausência de falso conflito na edição, compatibilidade, confirmação, mudança posterior à prévia, permissões e rollback da auditoria. Processos separados testam criações/edições simultâneas; a suíte existente mantém as corridas de realocação. Locks específicos do MySQL continuam exigindo validação em homologação: os testes automatizados de concorrência usam SQLite descartável.

`npm run test:preventive:browser` usa servidor local e base sintética nova para cada execução. Configure `PREVENTIVE_URL`, `PREVENTIVE_FIXTURE`, `PREVENTIVE_QA_PASSWORD`, `PLAYWRIGHT_MODULE`/`PLAYWRIGHT_CHANNEL`; `AXE_MODULE` ativa a verificação AA e `PREVENTIVE_SCREENSHOTS` salva capturas fora do repositório. Fixture JSON: `sector`, `category`, `date` (dia seguinte a uma noite que termina às 07:00), `month` e `teams[320|390|1440]` com `user`, `name`, `night` e `uncovered` (24h com outra noite consecutiva). Conta gerencial sintética `alerts-manager@example.test`. O teste cria/edita plantões sintéticos, revisa sobreposição/dobra, navega pela realocação e confere teclado/acessibilidade em três larguras. Foi usado Playwright porque o plugin Browser estava indisponível.
