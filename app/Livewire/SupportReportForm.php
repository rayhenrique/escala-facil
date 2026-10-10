<?php

namespace App\Livewire;

use App\Actions\Support\SubmitIssueReportAction;
use App\Models\IssueReport;
use App\Support\IssueReportModules;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class SupportReportForm extends Component
{
    use WithFileUploads;

    public string $module = '';

    public string $description = '';

    public string $expectedBehavior = '';

    public $attachment = null;

    public ?string $protocol = null;

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function submit(SubmitIssueReportAction $action): void
    {
        $this->authorize('create', IssueReport::class);

        $validated = $this->validate([
            'module' => ['required', 'string', Rule::in(IssueReportModules::keys())],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'expectedBehavior' => ['required', 'string', 'min:10', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'module.in' => 'Escolha um módulo válido da lista.',
            'description.min' => 'Descreva o problema com pelo menos 10 caracteres.',
            'expectedBehavior.min' => 'Descreva o comportamento esperado com pelo menos 10 caracteres.',
            'attachment.mimes' => 'O anexo deve ser PDF, JPG ou PNG.',
            'attachment.max' => 'O anexo deve ter no máximo 5 MB.',
        ], [
            'module' => 'módulo',
            'description' => 'descrição',
            'expectedBehavior' => 'comportamento esperado',
            'attachment' => 'anexo',
        ]);

        $report = $action->execute(auth()->user(), [
            'module' => $validated['module'],
            'description' => $validated['description'],
            'expected_behavior' => $validated['expectedBehavior'],
        ], $this->attachment);

        $this->reset(['module', 'description', 'expectedBehavior', 'attachment']);
        $this->protocol = $report->protocol;

        session()->flash('status', 'Relato enviado. Anote o protocolo para acompanhar com a gerência.');
    }

    public function reportAgain(): void
    {
        $this->reset(['protocol']);
    }

    public function render(): View
    {
        return view('livewire.support-report-form', [
            'modules' => IssueReportModules::all(),
        ]);
    }
}
