<?php

namespace App\Console\Commands;

use App\Enums\ShiftSwapEvent;
use App\Enums\ShiftSwapStatus;
use App\Models\ShiftSwap;
use App\Support\ShiftSwapReceipt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncSwapNotifications extends Command
{
    protected $signature = 'app:sync-swap-notifications';

    protected $description = 'Sincroniza notificações de trocas com o status real e comprovantes das trocas decididas';

    public function handle(ShiftSwapReceipt $receipts): int
    {
        $notifications = DB::table('notifications')->get();
        $updatedCount = 0;

        $swapIds = $notifications->map(function ($notification) {
            $data = json_decode((string) $notification->data, true);
            if (! is_array($data) || ! isset($data['receipt']) || ! is_array($data['receipt'])) {
                return null;
            }
            $swapId = $data['receipt']['shift_swap_id'] ?? null;
            if (! $swapId && ! empty($data['receipt']['reference']) && preg_match('/(\d+)/', (string) $data['receipt']['reference'], $matches)) {
                $swapId = (int) $matches[1];
            }

            return $swapId;
        })->filter()->unique()->values();

        if ($swapIds->isEmpty()) {
            $this->info('Nenhuma notificação de troca encontrada.');

            return self::SUCCESS;
        }

        $swaps = ShiftSwap::query()
            ->with([
                'shift.category',
                'shift.sector',
                'substituteShift.category',
                'substituteShift.sector',
                'requester',
                'substitute',
                'manager',
            ])
            ->whereKey($swapIds)
            ->get()
            ->keyBy('id');

        foreach ($notifications as $notification) {
            $data = json_decode((string) $notification->data, true);
            if (! is_array($data) || ! isset($data['receipt']) || ! is_array($data['receipt'])) {
                continue;
            }

            $swapId = $data['receipt']['shift_swap_id'] ?? null;
            if (! $swapId && ! empty($data['receipt']['reference']) && preg_match('/(\d+)/', (string) $data['receipt']['reference'], $matches)) {
                $swapId = (int) $matches[1];
            }

            if (! $swapId || ! $swaps->has($swapId)) {
                continue;
            }

            /** @var ShiftSwap $swap */
            $swap = $swaps->get($swapId);

            $recordedStatus = $data['receipt']['status'] ?? null;
            $currentStatus = $swap->status->value;

            $hasChanged = ($recordedStatus !== $currentStatus)
                || (empty($data['receipt']['rejection_reason']) && ! empty($swap->rejection_reason))
                || (empty($data['receipt']['manager']) && ! empty($swap->manager?->name));

            if (! $hasChanged) {
                continue;
            }

            $event = match ($swap->status) {
                ShiftSwapStatus::PendingProfessional => ShiftSwapEvent::Requested,
                ShiftSwapStatus::PendingManager => ShiftSwapEvent::Accepted,
                ShiftSwapStatus::Approved => ShiftSwapEvent::Approved,
                ShiftSwapStatus::Rejected => ShiftSwapEvent::Rejected,
                default => ShiftSwapEvent::Reverted,
            };

            $freshReceipt = $receipts->make($swap, $event);
            $data['receipt'] = $freshReceipt;

            $currentTitle = $data['title'] ?? '';
            $currentEvent = $data['event'] ?? '';
            $isAwaitingDecision = $currentEvent === ShiftSwapEvent::Accepted->value
                || str_contains(mb_strtolower($currentTitle), 'aguardando')
                || ($data['target_url'] ?? '') === route('manager.swaps', [], false);

            if ($isAwaitingDecision) {
                if ($swap->status === ShiftSwapStatus::Rejected) {
                    $data['event'] = ShiftSwapEvent::Rejected->value;
                    $data['title'] = 'Troca rejeitada';
                    $data['message'] = $swap->rejection_reason
                        ? "Solicitação rejeitada por {$swap->manager?->name}. Justificativa: {$swap->rejection_reason}. A escala original foi mantida."
                        : "Solicitação rejeitada por {$swap->manager?->name}. A escala original foi mantida.";
                    $data['target_url'] = route('notifications', [], false);
                } elseif ($swap->status === ShiftSwapStatus::Approved) {
                    $data['event'] = ShiftSwapEvent::Approved->value;
                    $data['title'] = 'Troca aprovada';
                    $data['message'] = "Troca aprovada por {$swap->manager?->name} e a escala foi atualizada.";
                    $data['target_url'] = route('notifications', [], false);
                } elseif ($swap->status === ShiftSwapStatus::Reverted) {
                    $data['event'] = ShiftSwapEvent::Reverted->value;
                    $data['title'] = 'Troca desfeita';
                    $data['message'] = "Troca desfeita por {$swap->manager?->name} e o plantão voltou para {$swap->requester->name}.";
                    $data['target_url'] = route('notifications', [], false);
                }
            }

            DB::table('notifications')
                ->where('id', $notification->id)
                ->update([
                    'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);

            $updatedCount++;
        }

        $this->info("{$updatedCount} notificações sincronizadas com sucesso.");

        return self::SUCCESS;
    }
}
