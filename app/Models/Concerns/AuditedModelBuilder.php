<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

/** Operational writes must use models so they cannot silently bypass the audit. */
class AuditedModelBuilder extends Builder
{
    public function __call($method, $parameters)
    {
        if (in_array(strtolower($method), ['insertgetid', 'insertorignore', 'insertusing', 'insertorignoreusing', 'updateorinsert', 'truncate', 'incrementeach', 'decrementeach', 'updatefrom'], true)) {
            throw new LogicException('A escrita em lote não pode ignorar a auditoria operacional.');
        }

        return parent::__call($method, $parameters);
    }

    public function forceDelete()
    {
        throw new LogicException('Use delete do modelo para preservar a auditoria.');
    }

    public function update(array $values)
    {
        throw new LogicException('Use save/update do modelo ou processamento por eachById para preservar a auditoria.');
    }

    public function delete()
    {
        throw new LogicException('Use delete do modelo ou processamento por eachById para preservar a auditoria.');
    }

    public function insert(array $values)
    {
        throw new LogicException('Use create/save do modelo para preservar a auditoria.');
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw new LogicException('Use updateOrCreate do modelo para preservar a auditoria.');
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        throw new LogicException('Use update do modelo para preservar a auditoria.');
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        throw new LogicException('Use update do modelo para preservar a auditoria.');
    }
}
