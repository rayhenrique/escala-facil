<?php

namespace App\Support;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Models\ShiftSwap;
use App\Models\User;

/** Response eligibility shared by Policy, preview and the locked backend operation. */
class ShiftSwapResponse
{
    public function inspect(ShiftSwap $swap, User $recipient): array
    {
        $swap->loadMissing(['requester', 'substitute', 'shift.sector', 'shift.category', 'substituteShift.sector', 'substituteShift.category']);
        $blocks = [];
        if ($swap->status !== ShiftSwapStatus::PendingProfessional) {
            $blocks[] = 'Este pedido já não aguarda sua resposta ('.$swap->status->label().'). Atualize os detalhes e confira o histórico.';
        }
        foreach ([[$swap->shift, $swap->requester_id, 'original'], [$swap->substituteShift, $swap->substitute_id, 'de contrapartida']] as [$shift, $expected, $label]) {
            if ($label === 'de contrapartida' && ! $swap->substitute_shift_id) {
                continue;
            }
            if (! $shift) {
                $blocks[] = 'O plantão '.$label.' não está disponível. Consulte a gerência.';

                continue;
            }
            if ($shift->user_id !== $expected) {
                $blocks[] = 'A atribuição do plantão '.$label.' #'.$shift->id.' mudou. Consulte a gerência antes de aceitar.';
            }
            if ($shift->status !== ShiftStatus::Scheduled) {
                $blocks[] = 'O plantão '.$label.' #'.$shift->id.' não está agendado ('.$shift->status->label().'). Confira a ausência ou alteração da escala.';
            }
            if ($recipient->sector_id !== $shift->sector_id || $recipient->category_id !== $shift->category_id) {
                $blocks[] = 'Seu setor ou categoria é incompatível com o plantão '.$label.'. Consulte a gerência.';
            }
        }
        if (! $swap->requester || $recipient->sector_id !== $swap->requester->sector_id || $recipient->category_id !== $swap->requester->category_id) {
            $blocks[] = 'O cadastro do solicitante é incompatível com seu setor ou categoria. Consulte a gerência.';
        }
        $reference = $swap->created_at ?? now();
        $count = $recipient->approvedSwapCount($reference->month, $reference->year);
        if ($count >= User::MONTHLY_SWAP_LIMIT) {
            $blocks[] = 'Você atingiu a cota de '.$count.' de '.User::MONTHLY_SWAP_LIMIT.' trocas aprovadas em '.$reference->format('m/Y').', mês da solicitação. Ainda é possível recusar este pedido.';
        }
        $warnings = $swap->shift && $swap->requester && $swap->substitute
            ? app(ShiftSwapTimingWarnings::class)->make($swap->shift, $swap->substitute, $swap->substituteShift, $swap->requester) : [];
        $shiftFields = ['id', 'date', 'end_date', 'start_time', 'end_time', 'sector_id', 'category_id', 'user_id', 'status'];
        $userFields = ['id', 'role', 'sector_id', 'category_id'];

        return [
            'reference' => sprintf('TROCA-%06d', $swap->id), 'status' => $swap->status->value, 'status_label' => $swap->status->label(),
            'shift' => $swap->shift?->period()->datedLabel() ?? 'Plantão indisponível',
            'location' => ($swap->shift?->sector?->name ?? 'Setor não informado').' · '.($swap->shift?->category?->name ?? 'Categoria não informada'),
            'requester' => $swap->requester?->name ?? 'Solicitante indisponível', 'substitute' => $swap->substitute?->name ?? 'Profissional indisponível',
            'counterpart' => $swap->substituteShift?->period()->datedLabel(),
            'counterpart_location' => $swap->substituteShift ? ($swap->substituteShift->sector?->name ?? 'Setor não informado').' · '.($swap->substituteShift->category?->name ?? 'Categoria não informada') : null,
            'blocks' => $blocks, 'warnings' => $warnings, 'quota' => $count.' de '.User::MONTHLY_SWAP_LIMIT.' em '.$reference->format('m/Y'),
            'can_accept' => $blocks === [], 'can_decline' => $swap->status === ShiftSwapStatus::PendingProfessional,
            'fingerprint' => hash('sha256', json_encode([$swap->only(['id', 'status', 'shift_id', 'substitute_shift_id', 'requester_id', 'substitute_id']),
                $swap->shift?->only($shiftFields), $swap->substituteShift?->only($shiftFields), $swap->requester?->only($userFields), $recipient->only($userFields), $count, $warnings], JSON_THROW_ON_ERROR)),
        ];
    }
}
