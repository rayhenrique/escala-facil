<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationNavItem extends Component
{
    #[On('notifications-updated')]
    public function refreshCount(): void {}

    public function render(): View
    {
        return view('livewire.notification-nav-item', [
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
        ]);
    }
}
