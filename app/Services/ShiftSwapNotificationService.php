<?php

namespace App\Services;

use App\Enums\ShiftSwapEvent;
use App\Enums\UserRole;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Notifications\ShiftSwapEventNotification;
use App\Support\ShiftSwapReceipt;
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
    }

    public function rejected(ShiftSwap $shiftSwap): void
    {
        $shiftSwap->loadMissing(['requester', 'substitute', 'manager']);

        Notification::send(
            collect([$shiftSwap->requester, $shiftSwap->substitute]),
            new ShiftSwapEventNotification($this->payload(
                $shiftSwap,
                ShiftSwapEvent::Rejected,
                'Troca rejeitada',
                "{$shiftSwap->manager->name} rejeitou a solicitação. A escala original foi mantida.",
                route('notifications', [], false),
            )),
        );
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
     * @param array<string, mixed> $receipt
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
