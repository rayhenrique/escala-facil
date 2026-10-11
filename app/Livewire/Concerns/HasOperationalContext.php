<?php

namespace App\Livewire\Concerns;

use App\Support\OperationalNavigation;
use Livewire\Attributes\Url;

trait HasOperationalContext
{
    #[Url]
    public $from = '';

    #[Url]
    public $context = [];

    public function navigationContext(): array
    {
        $module = match (class_basename($this)) {
            'UserManager' => 'users', 'SectorManager' => 'sectors', 'CategoryManager' => 'categories', 'AuditHistory' => 'audit', 'Dashboard' => 'dashboard', 'ShiftManager' => 'shifts', 'SwapApprovals' => 'swaps',
            'MonthlyCalendar' => 'calendar', 'Reports' => 'reports',
        };
        $state = [];
        foreach (OperationalNavigation::FIELDS[$module] as $field) {
            $state[$field] = ($field === 'page' || str_ends_with($field, 'Page')) ? $this->getPage($field) : $this->{$field};
        }
        $state['from'] = $this->from;
        $state['context'] = $this->context;

        return OperationalNavigation::clean($module, $state);
    }

    public function backNavigation(): array
    {
        return OperationalNavigation::back($this->from, $this->context);
    }
}
