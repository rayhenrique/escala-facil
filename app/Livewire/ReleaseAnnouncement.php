<?php

namespace App\Livewire;

use App\Support\ApplicationVersion;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ReleaseAnnouncement extends Component
{
    public bool $show = false;

    /** @var array{version: string, date: string, title: string, summary: string, highlights: array<int, string>} */
    public array $release = [];

    public function mount(ApplicationVersion $versions): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $this->release = $versions->currentRelease();
        $this->show = $versions->isUnseenBy($user);
    }

    public function acknowledge(ApplicationVersion $versions): void
    {
        $this->markAsSeen($versions);
        $this->show = false;
    }

    public function openHelp(ApplicationVersion $versions): mixed
    {
        $this->markAsSeen($versions);

        return $this->redirectRoute('help', navigate: true);
    }

    private function markAsSeen(ApplicationVersion $versions): void
    {
        auth()->user()->forceFill([
            'last_seen_version' => $versions->current(),
        ])->save();
    }

    public function render(): View
    {
        return view('livewire.release-announcement');
    }
}
