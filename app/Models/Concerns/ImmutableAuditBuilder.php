<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

class ImmutableAuditBuilder extends Builder
{
    public function update(array $values)
    {
        throw new LogicException('O histórico é append-only.');
    }

    public function delete()
    {
        throw new LogicException('O histórico é append-only.');
    }

    public function forceDelete()
    {
        throw new LogicException('O histórico é append-only.');
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw new LogicException('O histórico é append-only.');
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        throw new LogicException('O histórico é append-only.');
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        throw new LogicException('O histórico é append-only.');
    }

    public function __call($method, $parameters)
    {
        if (in_array(strtolower($method), ['truncate', 'updateorinsert', 'incrementeach', 'decrementeach', 'updatefrom'], true)) {
            throw new LogicException('O histórico é append-only.');
        }

        return parent::__call($method, $parameters);
    }
}
