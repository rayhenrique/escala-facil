<?php

namespace App\Livewire;

use App\Support\ApplicationVersion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Ajuda e versões'])]
class HelpCenter extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function render(ApplicationVersion $versions): View
    {
        return view('livewire.help-center', [
            'currentRelease' => $versions->currentRelease(),
            'releases' => $versions->releases(),
        ]);
    }
}
