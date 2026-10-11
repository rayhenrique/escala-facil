<?php

namespace App\Support;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Models\ShiftSwap;
use App\Models\User;

/** Approval preconditions shared by the Policy, review and locked decision. */
class ShiftSwapApproval
{
    public static function statusReason(ShiftSwap $swap): ?string
    {
        return $swap->status === ShiftSwapStatus::PendingManager ? null
            : 'Pedido #'.$swap->id.': situação incompatível ('.$swap->status->label().'). Apenas pedidos aguardando a gerência podem ser decididos. Atualize a fila e confira a decisão registrada.';
    }

    public function inspect(ShiftSwap $swap): array
    {
        $swap->loadMissing(['requester', 'substitute', 'shift', 'substituteShift']);
        $blockers = [];
        $statusReason = self::statusReason($swap);
        if ($statusReason !== null) {
            $blockers[] = ['code' => 'request_status', 'message' => $statusReason];
        }
        $reference = $swap->created_at ?? now();
        $quotas = [];
        foreach (['requester' => 'Solicitante', 'substitute' => 'Substituto'] as $relation => $label) {
            $participant = $swap->{$relation};
            if (! $participant) {
                $blockers[] = ['code' => $relation.'_missing', 'message' => $label.' indisponível. Confira o cadastro e os vínculos do pedido antes de decidir.'];

                continue;
            }
            $count = $participant->approvedSwapCount($reference->month, $reference->year);
            $quotas[] = ['id' => $participant->id, 'name' => $participant->name, 'count' => $count, 'limit' => User::MONTHLY_SWAP_LIMIT, 'month' => $reference->format('m/Y')];
            if ($count >= User::MONTHLY_SWAP_LIMIT) {
                $blockers[] = ['code' => $relation.'_quota', 'message' => 'Limite mensal atingido por '.$participant->name.': '.$count.' de '.User::MONTHLY_SWAP_LIMIT.' trocas aprovadas em '.$reference->format('m/Y').'. Confira as participações aprovadas no mês de criação deste pedido.'];
            }
        }
        $assignments = [];
        $labels = [];
        foreach ([$swap->requester, $swap->substitute] as $participant) {
            if ($participant) {
                $labels[$participant->id] = $participant->name.' (profissional #'.$participant->id.')';
            }
        }
        foreach (['shift' => ['original', $swap->shift_id, $swap->requester_id], 'substituteShift' => ['de contrapartida', $swap->substitute_shift_id, $swap->substitute_id]] as $relation => [$label, $id, $expected]) {
            if ($relation === 'substituteShift' && ! $id) {
                continue;
            }
            $shift = $swap->{$relation};
            if (! $shift) {
                $blockers[] = ['code' => $relation.'_missing', 'message' => 'Plantão '.$label.' #'.$id.' indisponível. Confira se o registro foi excluído; este pedido não pode transferir um plantão inexistente.'];

                continue;
            }
            $owner = $shift->user_id !== null ? ($labels[$shift->user_id] ?? 'profissional #'.$shift->user_id) : 'sem profissional';
            $expectedLabel = $labels[$expected] ?? 'profissional #'.$expected;
            $assignments[] = 'Plantão '.$label.' #'.$id.': atribuição atual '.$owner.'; titular registrado no pedido '.$expectedLabel.'; situação '.$shift->status->label().'.';
            if ($shift->user_id !== $expected) {
                $blockers[] = ['code' => $relation.'_assignment', 'message' => 'Plantão '.$label.' #'.$id.': atribuição modificada. Esperado '.$expectedLabel.'; atual '.$owner.'. Revise a escala; se a mudança for intencional, rejeite o pedido com justificativa e solicite uma nova troca.'];
            }
            if ($shift->status !== ShiftStatus::Scheduled) {
                $blockers[] = ['code' => $relation.'_status', 'message' => 'Plantão '.$label.' #'.$id.': situação incompatível ('.$shift->status->label().'). A transferência exige um plantão agendado. Confira a ausência ou alteração da escala antes de decidir.'];
            }
        }

        // A completed transfer naturally changes ownership and consumes quota.
        // Its actual impediment is the request status, not those expected consequences.
        return ['blockers' => $statusReason !== null ? [$blockers[0]] : $blockers, 'quotas' => $quotas, 'assignments' => $assignments];
    }
}
