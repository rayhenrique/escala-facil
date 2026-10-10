<?php

namespace App\Livewire;

use App\Models\ShiftSwap;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Notificações'])]
class NotificationsCenter extends Component
{
    use WithPagination;

    public bool $showMarkAllConfirmation = false;

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function markAsRead(string $notificationId): void
    {
        $notification = auth()->user()
            ->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();

        $notification->markAsRead();
        $this->dispatch('notifications-updated');
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        $this->showMarkAllConfirmation = false;
        $this->dispatch('notifications-updated');
        session()->flash('status', 'Todas as notificações foram marcadas como lidas.');
    }

    public function requestMarkAllAsRead(): void
    {
        if (auth()->user()->unreadNotifications()->doesntExist()) {
            return;
        }

        $this->showMarkAllConfirmation = true;
    }

    public function cancelMarkAllAsRead(): void
    {
        $this->showMarkAllConfirmation = false;
    }

    public function render(): View
    {
        $paginator = auth()->user()->notifications()->paginate(10);

        return view('livewire.notifications-center', [
            'notifications' => $paginator,
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
            'swapStatuses' => $this->currentSwapStatuses($paginator->getCollection()),
        ]);
    }

    /**
     * Resolve o estado atual das trocas apenas para leitura em tela.
     *
     * Os comprovantes históricos permanecem imutáveis no banco; este mapa
     * alimenta o aviso de "Situação atual" exibido ao lado de cada evento.
     *
     * @param  Collection<int, DatabaseNotification>  $notifications
     * @return array<int, array{status: string, status_label: string, manager: ?string, rejection_reason: ?string}>
     */
    private function currentSwapStatuses(Collection $notifications): array
    {
        $swapIds = $notifications
            ->map(fn ($notification) => $this->swapIdFromData($notification->data))
            ->filter()
            ->unique()
            ->values();

        if ($swapIds->isEmpty()) {
            return [];
        }

        $swaps = ShiftSwap::query()
            ->with(['requester', 'substitute', 'manager'])
            ->whereKey($swapIds)
            ->get()
            ->keyBy('id');

        $statuses = [];

        foreach ($swapIds as $swapId) {
            /** @var ShiftSwap|null $swap */
            $swap = $swaps->get($swapId);

            if (! $swap) {
                continue;
            }

            $statuses[$swapId] = [
                'status' => $swap->status->value,
                'status_label' => $swap->status->label(),
                'manager' => $swap->manager?->name,
                'rejection_reason' => $swap->rejection_reason,
            ];
        }

        return $statuses;
    }

    private function swapIdFromData(mixed $data): ?int
    {
        if (! is_array($data) || ! isset($data['receipt']) || ! is_array($data['receipt'])) {
            return null;
        }

        $swapId = $data['receipt']['shift_swap_id'] ?? null;

        if (is_numeric($swapId)) {
            return (int) $swapId;
        }

        if (! empty($data['receipt']['reference']) && preg_match('/(\d+)/', (string) $data['receipt']['reference'], $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
