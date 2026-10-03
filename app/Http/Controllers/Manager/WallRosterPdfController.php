<?php

namespace App\Http\Controllers\Manager;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Services\ShiftReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WallRosterPdfController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, ShiftReportService $reports): Response
    {
        $this->authorize('viewCalendar', Shift::class);

        $month = $request->string('month')->toString();
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : now()->format('Y-m');

        $user = $request->user();
        if ($user?->role === UserRole::Professional) {
            $sectorId = (string) ($user->sector_id ?? '');
        } else {
            $sectorId = $request->string('sector_id')->toString();
            $sectorId = ctype_digit($sectorId) && Sector::query()->whereKey($sectorId)->exists() ? $sectorId : '';
        }

        $categoryId = $request->string('category_id')->toString();
        $categoryId = ctype_digit($categoryId) && Category::query()->whereKey($categoryId)->exists() ? $categoryId : '';

        $roster = $reports->wallRoster($month, $sectorId, $categoryId, $user);

        return Pdf::loadView('reports.wall-roster', [
            'roster' => $roster,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape')->download("escala-para-afixar-{$month}.pdf");
    }
}
