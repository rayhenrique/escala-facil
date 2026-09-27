<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
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
        return view('livewire.notifications-center', [
            'notifications' => auth()->user()->notifications()->paginate(10),
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
        ]);
    }
}
