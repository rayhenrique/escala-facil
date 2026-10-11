<?php

namespace App\Livewire;

use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Models\ShiftSwap;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class MobileNavigation extends Component
{
    #[Locked]
    public string $routeName = '';

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
        $this->routeName = request()->route()?->getName() ?? '';
    }

    #[On('notifications-updated')]
    public function refreshCounts(): void {}

    public function render(): View
    {
        abort_unless(auth()->check(), 403);
        $user = auth()->user();
        $isManager = $user->role === UserRole::Manager;
        $pendingCount = $isManager
            ? (Gate::allows('viewAny', ShiftSwap::class) ? ShiftSwap::where('status', ShiftSwapStatus::PendingManager)->count() : 0)
            : ShiftSwap::where('substitute_id', $user->id)->where('status', ShiftSwapStatus::PendingProfessional)->count();
        $activeGroup = match (true) {
            Str::is(['manager.dashboard*', 'professional.dashboard*'], $this->routeName) => 'home',
            Str::is('manager.calendar*', $this->routeName) => 'calendar',
            Str::is('manager.swaps*', $this->routeName) => 'swaps',
            default => 'menu',
        };

        return view('livewire.mobile-navigation', [
            'isManager' => $isManager,
            'pendingCount' => $pendingCount,
            'unreadCount' => $user->unreadNotifications()->count(),
            'activeGroup' => $activeGroup,
        ]);
    }
}
