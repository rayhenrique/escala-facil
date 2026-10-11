<?php

namespace App\Models;

use App\Models\Concerns\ImmutableAuditBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OperationalAuditEvent extends Model
{
    public const ENTITIES = ['shift' => 'Plantão', 'swap' => 'Troca', 'user' => 'Usuário', 'sector' => 'Setor', 'category' => 'Categoria', 'invitation' => 'Convite'];

    public const ACTIONS = ['created' => 'Criação', 'updated' => 'Edição', 'deleted' => 'Exclusão', 'reallocated' => 'Realocação', 'approved' => 'Aprovação', 'rejected' => 'Rejeição', 'reverted' => 'Troca desfeita', 'accepted' => 'Aceite do profissional', 'activated' => 'Ativação', 'revoked' => 'Revogação', 'resent' => 'Reenvio'];

    public $timestamps = false;

    protected $fillable = ['author_id', 'entity_type', 'entity_id', 'action', 'occurred_at', 'operation_id', 'changes'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    public function newEloquentBuilder($query)
    {
        return new ImmutableAuditBuilder($query);
    }

    protected function casts(): array
    {
        return ['author_id' => 'integer', 'entity_id' => 'integer', 'occurred_at' => 'immutable_datetime', 'changes' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('O histórico operacional não pode ser editado.'));
        static::deleting(fn () => throw new LogicException('O histórico operacional não pode ser excluído.'));
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
