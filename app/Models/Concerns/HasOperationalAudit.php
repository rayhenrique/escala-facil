<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Support\OperationalAudit;

trait HasOperationalAudit
{
    /** Captured under the row lock; a stale model original is not authoritative. */
    private array $operationalAuditBefore = [];

    private bool $operationalWrite = false;

    public function newEloquentBuilder($query)
    {
        return $this->operationalWrite ? parent::newEloquentBuilder($query) : new AuditedModelBuilder($query);
    }

    public function save(array $options = [])
    {
        return $this->getConnection()->transaction(function () use ($options) {
            $emailBefore = null;
            if ($this->exists) {
                $current = $this->newQuery()->lockForUpdate()->findOrFail($this->getKey());
                $this->operationalAuditBefore = app(OperationalAudit::class)->snapshot($current);
                if ($this instanceof User) {
                    $emailBefore = $current->email;
                }
            }

            $creating = ! $this->exists;
            $this->operationalWrite = true;
            try {
                $saved = parent::save($options);
            } finally {
                $this->operationalWrite = false;
            }
            if ($saved) {
                $after = $this->fresh();
                app(OperationalAudit::class)->record($this, $creating ? 'created' : 'updated', $creating ? [] : $this->operationalAuditBefore, app(OperationalAudit::class)->snapshot($after), ! $creating && $this instanceof User && $emailBefore !== $after->email);
            }

            return $saved;
        });
    }

    public function delete()
    {
        return $this->getConnection()->transaction(function () {
            if ($this->exists) {
                $current = $this->newQuery()->lockForUpdate()->findOrFail($this->getKey());
                $this->operationalAuditBefore = app(OperationalAudit::class)->snapshot($current);
            }

            $this->operationalWrite = true;
            try {
                $deleted = parent::delete();
            } finally {
                $this->operationalWrite = false;
            }
            if ($deleted) {
                app(OperationalAudit::class)->record($this, 'deleted', $this->operationalAuditBefore, []);
            }

            return $deleted;
        });
    }
}
