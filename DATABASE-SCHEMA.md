# DATABASE-SCHEMA - Diagrama e Definição de Tabelas

## Tabelas e Relacionamentos

### `users`
* `id` (bigIncrements)
* `name` (string)
* `email` (string, unique)
* `password` (string)
* `role` (enum: 'manager', 'professional')
* `category_id` (foreignId -> categories.id, nullable, constrained)
* `sector_id` (foreignId -> sectors.id, nullable, constrained)
* `remember_token`
* `last_seen_version` (string(20), nullable) - Última versão cujas novidades foram confirmadas pelo usuário
* `timestamps`

### `categories`
* `id` (bigIncrements)
* `name` (string) - ex: 'Médico Clínico', 'Enfermeiro Padrão'
* `timestamps`

### `sectors`
* `id` (bigIncrements)
* `name` (string) - ex: 'Emergência', 'UTI'
* `timestamps`

### `shifts` (Plantões)
* `id` (bigIncrements)
* `date` (date)
* `start_time` (time)
* `end_time` (time)
* `end_date` (date, nullable): data explícita de término. NULL conserva a regra histórica, sem regravar registros antigos.
* `sector_id` (foreignId -> sectors.id, constrained)
* `category_id` (foreignId -> categories.id, constrained)
* `user_id` (foreignId -> users.id, nullable, constrained) - Profissional atualmente alocado
* `status` (enum: 'scheduled', 'absence_reported', 'uncovered') - Default: 'scheduled'
* `timestamps`

### `shift_swaps` (Solicitações de Troca)
* `id` (bigIncrements)
* `shift_id` (foreignId -> shifts.id, constrained, cascadeOnDelete)
* `requester_id` (foreignId -> users.id, constrained) - Quem quer sair do plantão
* `substitute_id` (foreignId -> users.id, constrained) - Quem vai assumir
* `status` (enum: 'pending_professional', 'pending_manager', 'approved', 'rejected', 'reverted')
* `manager_id` (foreignId -> users.id, nullable, constrained) - Quem aprovou/rejeitou
* `timestamps`

### `absences` (Justificativas de Falta)
* `id` (bigIncrements)
* `shift_id` (foreignId -> shifts.id, constrained, cascadeOnDelete)
* `user_id` (foreignId -> users.id, constrained)
* `reason` (text)
* `attachment_path` (string, nullable)
* `status` (enum: 'pending', 'acknowledged') - Gerente marca como ciente
* `timestamps`

### `notifications` (Notificações e Comprovantes)
* `id` (uuid, primary)
* `type` (string) - Classe da notificação
* `notifiable_type` (string) - Tipo do destinatário
* `notifiable_id` (unsignedBigInteger) - Usuário destinatário
* `data` (text/json serializado) - Evento, mensagem, destino e retrato imutável do comprovante
* `read_at` (timestamp, nullable) - Data e hora da leitura
* `timestamps`

O campo `data` preserva a referência da troca, plantão, horários, setor, categoria, solicitante, substituto, situação e gerente responsável no momento do evento. Isso mantém o comprovante fiel mesmo se cadastros forem alterados depois.

## Tabela técnica de dados de demonstração

### `demo_data_batches`
* `id` (bigIncrements)
* `key` (string, unique) - Identificador estável do lote fictício
* `manifest` (json) - IDs criados em cada tabela do domínio
* `timestamps`

Essa tabela não pertence ao domínio de plantões. Ela permite que `php artisan demo:data clear` remova somente a carga fictícia e os registros de teste vinculados a ela.


## operational_audit_events (1.29.0)

Tabela append-only com `id`, `author_id` nullable sem FK de cascata, `entity_type`, `entity_id`, `action`, `occurred_at` (microssegundos), `operation_id` (UUID) e `changes` (JSON com diferenças relevantes). Índices por tipo/registro/horário, autor/horário, horário e UUID. Não contém senhas, tokens, anexos ou cópias de comprovantes. Modelo/builders e triggers impedem alteração/exclusão ordinária; rollback da migração recusa remover eventos existentes. O histórico especializado em `shift_reallocations` permanece intacto.

## Convites de contas

`users.requires_activation` é booleano com padrão falso; convites novos usam verdadeiro até o aceite. `account_invitations` tem ID, `user_id` único (FK com exclusão em cascata), `invited_by`, hash SHA-256 de token único/nullable, hash do e-mail vinculado, contador de geração e datas de expiração/envio/aceite/revogação. Aceite/revogação apagam o digest do token. Autor não sofre cascata, e metadados relevantes são preservados no histórico append-only sem copiar tokens ou senhas.
