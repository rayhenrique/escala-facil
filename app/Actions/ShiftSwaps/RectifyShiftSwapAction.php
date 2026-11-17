<?php

namespace App\Actions\ShiftSwaps;

use App\Http\Requests\RectifyShiftSwapRequest;
use App\Models\OperationalAuditEvent;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\ShiftSwapRectification;
use App\Models\User;
use App\Services\ShiftSwapNotificationService;
use App\Support\AdministrativeSwapCorrection;
use App\Support\OperationalAuditContext;
use App\Support\ShiftSwapReversion;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RectifyShiftSwapAction
{
    use DetectsConcurrencyErrors;

    public function __construct(private readonly ShiftSwapNotificationService $notifications) {}

    public function execute(User $manager, int $id, array $payload): ShiftSwapRectification
    {
        Gate::forUser($manager)->authorize('viewAny', ShiftSwap::class);
        foreach (['administrativeReason', 'administrativeEvidence'] as $field) {
            if (is_string($payload[$field] ?? null)) {
                $payload[$field] = trim($payload[$field]);
            }
        }
        (new RectifyShiftSwapRequest)->validatePayload($payload);
        $reference = ShiftSwap::query()->findOrFail($id);

        try {
            return app(OperationalAuditContext::class)->transaction($manager, function () use ($manager, $id, $reference, $payload): ShiftSwapRectification {
                $users = User::query()->whereKey([$manager->id, $reference->requester_id, $reference->substitute_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $shifts = Shift::query()->whereKey(array_filter([$reference->shift_id, $reference->substitute_shift_id]))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $swap = ShiftSwap::query()->lockForUpdate()->findOrFail($id);
                $manager = $users->get($manager->id);
                abort_unless($manager, 403);
                Gate::forUser($manager)->authorize('administrativelyRectify', $swap);
                if ($reference->only(ShiftSwapReversion::BINDINGS) !== $swap->only(ShiftSwapReversion::BINDINGS)) {
                    throw ValidationException::withMessages(['administrativeSnapshot' => 'Os vínculos mudaram. Atualize a revisão administrativa.']);
                }
                $swap->setRelation('shift', $shifts->get($swap->shift_id));
                $swap->setRelation('substituteShift', $shifts->get($swap->substitute_shift_id));
                $review = app(AdministrativeSwapCorrection::class)->review($swap);
                if (! $review['can_register'] || ! hash_equals($review['fingerprint'], $payload['administrativeSnapshot'])) {
                    throw ValidationException::withMessages(['administrativeSnapshot' => 'Revisão desatualizada ou registro indisponível. Atualize a análise; pedidos elegíveis devem usar a reversão operacional.']);
                }
                $occurred = now();
                $operation = app(OperationalAuditContext::class)->operationId();
                $kindLabel = $payload['administrativeKind'] === 'annulment' ? 'Anulação administrativa' : 'Retificação administrativa';
                $receipt = [
                    'reference' => sprintf('TROCA-%06d', $swap->id), 'shift_swap_id' => $swap->id,
                    'event' => 'administratively_rectified', 'event_label' => 'Retificada administrativamente',
                    'status' => $swap->status->value, 'status_label' => $swap->status->label(),
                    'recorded_at' => $occurred->toIso8601String(), 'kind' => $payload['administrativeKind'], 'kind_label' => $kindLabel,
                    'author_id' => $manager->id, 'author' => $manager->name,
                    'reason' => $payload['administrativeReason'], 'evidence_notes' => $payload['administrativeEvidence'],
                    'requester' => $users->get($swap->requester_id)?->name ?? 'Cadastro indisponível',
                    'substitute' => $users->get($swap->substitute_id)?->name ?? 'Cadastro indisponível',
                    'shift_id' => $swap->shift_id, 'substitute_shift_id' => $swap->substitute_shift_id,
                    'has_counterpart' => $swap->substitute_shift_id !== null,
                    'approved_at' => $swap->approved_at?->toIso8601String(), 'approval_event_ids' => $review['evidence']['approval_event_ids'],
                    'operation_id' => $operation,
                    'effect' => 'Registro administrativo sem restauração de plantões, alteração da aprovação original ou ajuste de cotas.',
                ];
                $record = ShiftSwapRectification::create([
                    'shift_swap_id' => $swap->id, 'author_id' => $manager->id, 'occurred_at' => $occurred, 'operation_id' => $operation,
                    'kind' => $payload['administrativeKind'], 'reason' => $payload['administrativeReason'], 'evidence_notes' => $payload['administrativeEvidence'],
                    'evidence' => $review['evidence'], 'receipt' => $receipt,
                ]);
                OperationalAuditEvent::create([
                    'author_id' => $manager->id, 'entity_type' => 'swap', 'entity_id' => $swap->id, 'action' => 'administratively_rectified',
                    'occurred_at' => $occurred, 'operation_id' => $operation,
                    'changes' => ['administrative_record_id' => ['before' => null, 'after' => $record->id], 'administrative_kind' => ['before' => null, 'after' => $record->kind]],
                ]);
                $this->notifications->administrativelyRectified($swap, $record);

                return $record;
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (! $this->causedByConcurrencyError($exception)) {
                throw $exception;
            }
            throw ValidationException::withMessages(['administrativeSnapshot' => 'Outra operação está alterando este pedido. Atualize a revisão antes de confirmar.']);
        }
    }
}
