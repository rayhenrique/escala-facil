<?php

namespace App\Http\Controllers;

use App\Models\ShiftSwap;
use App\Support\ShiftSwapReceipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SwapReceiptPdfController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, ShiftSwap $swap, ShiftSwapReceipt $receipts): Response
    {
        $this->authorize('view', $swap);

        $receipt = $receipts->current($swap);

        return Pdf::loadView(($receipt['event'] ?? null) === 'administratively_rectified' ? 'reports.administrative-swap-receipt' : 'reports.swap-receipt', [
            'receipt' => $receipt,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait')->download("comprovante-{$receipt['reference']}.pdf");
    }
}
