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
* `status` (enum: 'pending_professional', 'pending_manager', 'approved', 'rejected')
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

## Tabela técnica de dados de demonstração

### `demo_data_batches`
* `id` (bigIncrements)
* `key` (string, unique) - Identificador estável do lote fictício
* `manifest` (json) - IDs criados em cada tabela do domínio
* `timestamps`

Essa tabela não pertence ao domínio de plantões. Ela permite que `php artisan demo:data clear` remova somente a carga fictícia e os registros de teste vinculados a ela.
