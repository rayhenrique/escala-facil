<?php

namespace App\Livewire\Manager;

use App\Enums\UserRole;
use App\Http\Requests\OperationalFiltersRequest;
use App\Livewire\Concerns\HasOperationalContext;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Services\ShiftReportService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Relatórios'])]
class Reports extends Component
{
    use HasOperationalContext;
    use WithPagination;

    #[Url]
    public string $month = '';

    #[Url]
    public string $tab = 'all';

    #[Url]
    public string $sectorId = '';

    #[Url]
    public string $categoryId = '';

    #[Url]
    public string $searchProfessional = '';

    #[Url]
    public string $swapStatusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewCalendar', Shift::class);

        $user = auth()->user();
        if ($user?->role === UserRole::Professional) {
            $this->sectorId = (string) ($user->sector_id ?? '');
        }

        $this->month = $this->normalizeMonth($this->month);
        $this->tab = $this->normalizeTab($this->tab);
        $this->updatedSectorId($this->sectorId);
        $this->updatedCategoryId($this->categoryId);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['month', 'sectorId', 'categoryId', 'searchProfessional', 'swapStatusFilter'], true)) {
            foreach (['professionalsPage', 'swapsPage', 'absencesPage', 'sectorsPage'] as $page) {
                $this->resetPage($page);
            }
        }
    }

    public function updatedMonth(): void
    {
        $this->month = $this->normalizeMonth($this->month);
    }

    public function updatedTab(): void
    {
        $this->tab = $this->normalizeTab($this->tab);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $this->normalizeTab($tab);
    }

    public function updatedSectorId(string $sectorId): void
    {
        $user = auth()->user();
        if ($user?->role === UserRole::Professional) {
            $this->sectorId = (string) ($user->sector_id ?? '');

            return;
        }

        $this->sectorId = ctype_digit($sectorId) && Sector::query()->whereKey($sectorId)->exists() ? $sectorId : '';
    }

    public function updatedCategoryId(string $categoryId): void
    {
        $this->categoryId = ctype_digit($categoryId) && Category::query()->whereKey($categoryId)->exists() ? $categoryId : '';
    }

    public function clearFilters(): void
    {
        foreach (['professionalsPage', 'swapsPage', 'absencesPage', 'sectorsPage'] as $page) {
            $this->resetPage($page);
        }
        $user = auth()->user();
        $this->searchProfessional = '';
        $this->swapStatusFilter = '';

        if ($user?->role === UserRole::Professional) {
            $this->reset('categoryId');
            $this->sectorId = (string) ($user->sector_id ?? '');

            return;
        }

        $this->reset('sectorId', 'categoryId');
    }

    #[Computed]
    public function sectors(): EloquentCollection
    {
        $user = auth()->user();
        if ($user?->role === UserRole::Professional) {
            return $user->sector_id
                ? Sector::query()->whereKey($user->sector_id)->get()
                : new EloquentCollection;
        }

        return Sector::query()->orderBy('name')->get();
    }

    #[Computed]
    public function categories(): EloquentCollection
    {
        return Category::query()->orderBy('name')->get();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function report(): array
    {
        $user = auth()->user();
        $effectiveSectorId = ($user?->role === UserRole::Professional)
            ? (string) ($user->sector_id ?? 0)
            : $this->sectorId;

        $request = new OperationalFiltersRequest;
        $validator = Validator::make($this->only(['searchProfessional', 'swapStatusFilter']), $request->rules(), $request->messages());
        $this->resetValidation(['searchProfessional', 'swapStatusFilter']);
        foreach ($validator->errors()->messages() as $key => $messages) {
            $this->addError($key, $messages[0]);
        }

        return app(ShiftReportService::class)->forScreen(
            $this->month, $effectiveSectorId, $this->categoryId, $user,
            $this->searchProfessional, $this->swapStatusFilter,
            mb_strlen($this->searchProfessional) <= 250,
        );
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function wallRoster(): array
    {
        $user = auth()->user();
        $effectiveSectorId = ($user?->role === UserRole::Professional)
            ? (string) ($user->sector_id ?? 0)
            : $this->sectorId;

        return app(ShiftReportService::class)->wallRoster(
            $this->month,
            $effectiveSectorId,
            $this->categoryId,
            $user
        );
    }

    #[Computed]
    public function sectorIndicators(): LengthAwarePaginator
    {
        $this->authorize('viewAny', Shift::class);
        $reports = app(ShiftReportService::class);

        return $reports->sectorIndicators($this->month, $this->sectorId, $this->categoryId, auth()->user(), $this->swapStatusFilter)
            ->paginate(10, ['*'], 'sectorsPage')->through($reports->indicatorRow(...));
    }

    private function normalizeTab(string $tab): string
    {
        return in_array($tab, ['all', 'coverage', 'professionals', 'swaps', 'absences', 'wall'], true)
            ? $tab
            : 'all';
    }

    private function normalizeMonth(string $month): string
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return now()->format('Y-m');
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01');

        return $date !== false && $date->format('Y-m') === $month
            ? $month
            : now()->format('Y-m');
    }

    public function render(): View
    {
        $this->authorize('viewCalendar', Shift::class);
        $user = auth()->user();

        return view('livewire.manager.reports', [
            'routePrefix' => $user?->role === UserRole::Professional ? 'professional.' : 'manager.',
            'isProfessional' => $user?->role === UserRole::Professional,
        ]);
    }
}
