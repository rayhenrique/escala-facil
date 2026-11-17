<?php

namespace App\Http\Controllers;

use App\Models\ShiftSwap;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpFoundation\Response;

class AdministrativeSwapReceiptPdfController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(ShiftSwap $swap): Response
    {
        $this->authorize('view', $swap);
        $record = $swap->rectification()->firstOrFail();

        return Pdf::loadView('reports.administrative-swap-receipt', ['receipt' => $record->receipt])
            ->setPaper('a4', 'portrait')->download('retificacao-TROCA-'.str_pad((string) $swap->id, 6, '0', STR_PAD_LEFT).'.pdf');
    }
}
