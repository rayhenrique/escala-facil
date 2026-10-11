<?php

namespace App\Livewire\Manager;

use App\Http\Requests\AuditHistoryRequest;
use App\Livewire\Concerns\HasOperationalContext;
use App\Models\OperationalAuditEvent;
use App\Support\OperationalFilters;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Histórico operacional'])]
class AuditHistory extends Component
{
    use HasOperationalContext;
    use WithPagination;

    #[Url]
    public string $entity = '';

    #[Url]
    public string $record = '';

    #[Url]
    public string $action = '';

    #[Url]
    public string $author = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public function mount(): void
    {
        $this->authorize('viewAny', OperationalAuditEvent::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['entity', 'record', 'action', 'author', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['entity', 'record', 'action', 'author', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function render()
    {
        $this->authorize('viewAny', OperationalAuditEvent::class);
        $input = $this->only(['entity', 'record', 'action', 'author', 'dateFrom', 'dateTo']);
        $request = new AuditHistoryRequest;
        $request->replace($input);
        $validator = Validator::make($input, $request->rules(), $request->messages());
        $this->resetValidation(array_keys($input));
        foreach ($validator->errors()->messages() as $key => $messages) {
            $this->addError($key, $messages[0]);
        }
        $query = OperationalAuditEvent::query()->with('author:id,name');
        if ($validator->fails()) {
            $query->whereRaw('1 = 0');
        } else {
            $query->when($this->entity !== '', fn ($q) => $q->where('entity_type', $this->entity))
                ->when($this->record !== '', fn ($q) => $q->where('entity_id', $this->record))
                ->when($this->action !== '', fn ($q) => $q->where('action', $this->action))
                ->when(trim($this->author) !== '', fn ($q) => $q->whereHas('author', fn ($user) => OperationalFilters::name($user, $this->author)))
                ->when($this->dateFrom !== '', fn ($q) => $q->where('occurred_at', '>=', CarbonImmutable::parse($this->dateFrom)->startOfDay()))
                ->when($this->dateTo !== '', fn ($q) => $q->where('occurred_at', '<', CarbonImmutable::parse($this->dateTo)->addDay()->startOfDay()));
        }

        return view('livewire.manager.audit-history', ['events' => $query->orderByDesc('occurred_at')->orderByDesc('id')->paginate(10)]);
    }
}
