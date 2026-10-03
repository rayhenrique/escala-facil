<?php

namespace App\Services;

use App\Enums\ShiftSwapEvent;
use App\Enums\UserRole;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Notifications\ShiftSwapEventNotification;
use App\Support\ShiftSwapReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ShiftSwapNotificationService
{
    public function __construct(private readonly ShiftSwapReceipt $receipts) {}

    public function requested(ShiftSwap $shiftSwap): void
    {
        $shiftSwap->loadMissing(['requester', 'substitute']);

        $shiftSwap->substitute->notify(new ShiftSwapEventNotification($this->payload(
            $shiftSwap,
            ShiftSwapEvent::Requested,
            'Nova solicitação de troca',
            "{$shiftSwap->requester->name} solicitou que você cubra um plantão.",
            route('professional.dashboard', [], false),
        )));
    }

    public function accepted(ShiftSwap $shiftSwap): void
    {
        $shiftSwap->loadMissing(['requester', 'substitute']);
        $receipt = $this->receipts->make($shiftSwap, ShiftSwapEvent::Accepted);

        $professionalPayload = $this->payloadFromReceipt(
            $receipt,
            'Solicitação aceita',
            "{$shiftSwap->substitute->name} aceitou cobrir o plantão. A troca aguarda a gerência.",
            route('notifications', [], false),
        );

        Notification::send(
            collect([$shiftSwap->requester, $shiftSwap->substitute]),
            new ShiftSwapEventNotification($professionalPayload),
        );

        $managers = User::query()
            ->where('role', UserRole::Manager->value)
            ->get();

        Notification::send($managers, new ShiftSwapEventNotification($this->payloadFromReceipt(
            $receipt,
            'Troca aguardando aprovação',
            "{$shiftSwap->substitute->name} aceitou a solicitação de {$shiftSwap->requester->name}. Revise a troca.",
            route('manager.swaps', [], false),
        )));
    }

    public function approved(ShiftSwap $shiftSwap): void
    {
        $shiftSwap->loadMissing(['requester', 'substitute', 'manager']);

        Notification::send(
            collect([$shiftSwap->requester, $shiftSwap->substitute]),
            new ShiftSwapEventNotification($this->payload(
                $shiftSwap,
                ShiftSwapEvent::Approved,
                'Troca aprovada',
                "{$shiftSwap->manager->name} aprovou a troca e a escala foi atualizada.",
                route('notifications', [], false),
            )),
        );

        $this->syncExistingSwapNotifications($shiftSwap, ShiftSwapEvent::Approved);
    }

    public function rejected(ShiftSwap $shiftSwap): void
    {
        $shiftSwap->loadMissing(['requester', 'substitute', 'manager']);

        $message = "{$shiftSwap->manager->name} rejeitou a solicitação. A escala original foi mantida.";

        if ($shiftSwap->rejection_reason) {
            $message = "{$shiftSwap->manager->name} rejeitou a solicitação. Justificativa: {$shiftSwap->rejection_reason}. A escala original foi mantida.";
        }

        Notification::send(
            collect([$shiftSwap->requester, $shiftSwap->substitute]),
            new ShiftSwapEventNotification($this->payload(
                $shiftSwap,
                ShiftSwapEvent::Rejected,
                'Troca rejeitada',
                $message,
                route('notifications', [], false),
            )),
        );

        $this->syncExistingSwapNotifications($shiftSwap, ShiftSwapEvent::Rejected);
    }

    public function reverted(ShiftSwap $shiftSwap): void
    {
        $shiftSwap->loadMissing(['requester', 'substitute', 'manager']);

        Notification::send(
            collect([$shiftSwap->requester, $shiftSwap->substitute]),
            new ShiftSwapEventNotification($this->payload(
                $shiftSwap,
                ShiftSwapEvent::Reverted,
                'Troca desfeita',
                "{$shiftSwap->manager->name} desfez a troca e o plantão voltou para {$shiftSwap->requester->name}.",
                route('notifications', [], false),
            )),
        );

        $this->syncExistingSwapNotifications($shiftSwap, ShiftSwapEvent::Reverted);
    }

    public function syncExistingSwapNotifications(ShiftSwap $shiftSwap, ShiftSwapEvent $event): void
    {
        $shiftSwap->loadMissing([
            'shift.category',
            'shift.sector',
            'substituteShift.category',
            'substituteShift.sector',
            'requester',
            'substitute',
            'manager',
        ]);

        $freshReceipt = $this->receipts->make($shiftSwap, $event);
        $reference = sprintf('TROCA-%06d', $shiftSwap->id);

        $notifications = DB::table('notifications')
            ->where(function ($query) use ($shiftSwap, $reference) {
                $query->where('data->receipt->shift_swap_id', $shiftSwap->id)
                    ->orWhere('data->receipt->reference', $reference)
                    ->orWhere('data', 'like', '%"shift_swap_id":'.$shiftSwap->id.'%')
                    ->orWhere('data', 'like', '%"reference":"'.$reference.'"%');
            })
            ->get();

        foreach ($notifications as $notification) {
            $data = json_decode((string) $notification->data, true);
            if (! is_array($data)) {
                continue;
            }

            $data['receipt'] = $freshReceipt;

            $currentTitle = $data['title'] ?? '';
            $currentEvent = $data['event'] ?? '';
            $isAwaitingDecision = $currentEvent === ShiftSwapEvent::Accepted->value
                || str_contains(mb_strtolower($currentTitle), 'aguardando')
                || ($data['target_url'] ?? '') === route('manager.swaps', [], false);

            if ($isAwaitingDecision) {
                if ($event === ShiftSwapEvent::Rejected) {
                    $data['title'] = 'Troca rejeitada';
                    $data['message'] = $shiftSwap->rejection_reason
                        ? "Solicitação rejeitada por {$shiftSwap->manager?->name}. Justificativa: {$shiftSwap->rejection_reason}. A escala original foi mantida."
                        : "Solicitação rejeitada por {$shiftSwap->manager?->name}. A escala original foi mantida.";
                    $data['target_url'] = route('notifications', [], false);
                } elseif ($event === ShiftSwapEvent::Approved) {
                    $data['title'] = 'Troca aprovada';
                    $data['message'] = "Troca aprovada por {$shiftSwap->manager?->name} e a escala foi atualizada.";
                    $data['target_url'] = route('notifications', [], false);
                } elseif ($event === ShiftSwapEvent::Reverted) {
                    $data['title'] = 'Troca desfeita';
                    $data['message'] = "Troca desfeita por {$shiftSwap->manager?->name} e o plantão voltou para {$shiftSwap->requester->name}.";
                    $data['target_url'] = route('notifications', [], false);
                }
            }

            DB::table('notifications')
                ->where('id', $notification->id)
                ->update([
                    'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
        }
    }

    /** @return array<string, mixed> */
    private function payload(
        ShiftSwap $shiftSwap,
        ShiftSwapEvent $event,
        string $title,
        string $message,
        string $targetUrl,
    ): array {
        return $this->payloadFromReceipt(
            $this->receipts->make($shiftSwap, $event),
            $title,
            $message,
            $targetUrl,
        );
    }

    /**
     * @param  array<string, mixed>  $receipt
     * @return array<string, mixed>
     */
    private function payloadFromReceipt(
        array $receipt,
        string $title,
        string $message,
        string $targetUrl,
    ): array {
        return [
            'event' => $receipt['event'],
            'title' => $title,
            'message' => $message,
            'target_url' => $targetUrl,
            'receipt' => $receipt,
        ];
    }
}
