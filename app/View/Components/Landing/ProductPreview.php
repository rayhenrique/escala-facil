<?php

namespace App\View\Components\Landing;

use App\Enums\ShiftStatus;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ProductPreview extends Component
{
    public Shift $shift;

    public function __construct()
    {
        // Exemplo público independente do banco e de dados de profissionais.
        $this->shift = new Shift([
            'date' => '2026-11-14',
            'start_time' => '19:00',
            'end_time' => '07:00',
            'end_date' => '2026-11-15',
            'status' => ShiftStatus::Scheduled,
        ]);
        $this->shift->setRelation('sector', new Sector(['name' => 'UTI Adulto']));
        $this->shift->setRelation('category', new Category(['name' => 'Médico']));
    }

    public function render(): View
    {
        return view('components.landing.product-preview');
    }
}
