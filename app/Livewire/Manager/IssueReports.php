<?php

namespace App\Livewire\Manager;

use App\Models\IssueReport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Relatos de problema'])]
class IssueReports extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('viewAny', IssueReport::class);
    }

    public function render(): View
    {
        return view('livewire.manager.issue-reports', [
            'reports' => IssueReport::query()
                ->with('reporter')
                ->latest()
                ->paginate(10),
        ]);
    }
}
