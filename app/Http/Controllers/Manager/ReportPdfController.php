<?php

namespace App\Http\Controllers\Manager;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReportExportRequest;
use App\Models\Shift;
use App\Services\ShiftReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpFoundation\Response;

class ReportPdfController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(ReportExportRequest $request, ShiftReportService $reports): Response
    {
        $this->authorize('viewCalendar', Shift::class);

        $month = $request->input('month') ?: now()->format('Y-m');
        $user = $request->user();
        $sectorId = $user->role === UserRole::Professional
            ? (string) ($user->sector_id ?? 0)
            : $request->string('sector_id')->toString();
        $categoryId = $request->string('category_id')->toString();

        $report = $reports->forPeriod($month, $sectorId, $categoryId, $request->user(),
            $request->string('searchProfessional')->toString(), $request->string('swapStatusFilter')->toString());

        return Pdf::loadView('reports.shifts', [
            'report' => $report,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->download("relatorio-plantoes-{$month}.pdf");
    }
}
