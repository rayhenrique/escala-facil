<?php

namespace App\Http\Controllers;

use App\Enums\ShiftSwapEvent;
use App\Enums\ShiftSwapStatus;
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

        $event = match ($swap->status) {
            ShiftSwapStatus::PendingProfessional => ShiftSwapEvent::Requested,
            ShiftSwapStatus::PendingManager => ShiftSwapEvent::Accepted,
            ShiftSwapStatus::Approved => ShiftSwapEvent::Approved,
            ShiftSwapStatus::Rejected => ShiftSwapEvent::Rejected,
            default => ShiftSwapEvent::Reverted,
        };

        $receipt = $receipts->make($swap, $event);

        return Pdf::loadView('reports.swap-receipt', [
            'receipt' => $receipt,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait')->download("comprovante-{$receipt['reference']}.pdf");
    }
}
