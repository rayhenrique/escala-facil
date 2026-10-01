<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Services\ShiftSpreadsheetService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ShiftSpreadsheetController extends Controller
{
    use AuthorizesRequests;

    public function template(ShiftSpreadsheetService $spreadsheets): BinaryFileResponse
    {
        $this->authorize('create', Shift::class);

        return response()
            ->download($spreadsheets->templatePath(), 'modelo-plantoes.xlsx')
            ->deleteFileAfterSend();
    }

    public function export(Request $request, ShiftSpreadsheetService $spreadsheets): BinaryFileResponse
    {
        $this->authorize('create', Shift::class);

        $month = $request->string('month')->toString();
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : now()->format('Y-m');

        return response()
            ->download($spreadsheets->monthExportPath($month), "plantoes-{$month}.xlsx")
            ->deleteFileAfterSend();
    }
}
