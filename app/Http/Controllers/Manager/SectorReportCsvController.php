<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportExportRequest;
use App\Models\Shift;
use App\Services\ShiftReportService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SectorReportCsvController extends Controller
{
    public function __invoke(ReportExportRequest $request, ShiftReportService $reports): StreamedResponse
    {
        Gate::authorize('viewAny', Shift::class);
        $month = $request->input('month') ?: now()->format('Y-m');
        $sector = $request->string('sector_id')->toString();
        $category = $request->string('category_id')->toString();
        $status = $request->string('swapStatusFilter')->toString();
        $query = $reports->sectorIndicators($month, $sector, $category, $request->user(), $status);

        return response()->streamDownload(function () use ($query, $reports, $month, $sector, $category, $status): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            $write = fn (array $row) => fputcsv($stream, $row, ';', '"', '');
            $write(['Escala Fácil', 'Indicadores por setor', 'Gerado em', now()->toIso8601String()]);
            $write(['Mês', $month, 'Setor ID', $sector ?: 'Todos', 'Categoria ID', $category ?: 'Todas', 'Situação das trocas', $status ?: 'Todas']);
            $write(['Situação atual', 'Plantões iniciados no mês; cobertura = agendados com profissional / total × 100. Sem cobertura = total menos cobertos. Descobertos = status descoberto. Não representa horas.']);
            $write(['Registros do período', 'Trocas: pedidos criados no mês, com filtro de situação atual. Ausências: registros vinculados a plantões iniciados no mês, mesmo após realocação. Setor/categoria atuais; não é um retrato histórico.']);
            $write(['Setor', 'Plantões', 'Cobertos', 'Cobertura (%)', 'Sem cobertura', 'Descobertos', 'Ausência informada (estado atual)', 'Pedidos de troca', 'Registros de ausência']);
            // Chunked retrieval also bounds memory with buffered PDO drivers.
            foreach ($query->lazy(200) as $record) {
                $row = $reports->indicatorRow($record);
                $name = preg_match('/^[\s]*[=+@-]/u', $row['name']) ? "'".$row['name'] : $row['name'];
                $write([$name, $row['total'], $row['covered'], $row['coverageRate'] ?? '—', $row['withoutCoverage'], $row['uncovered'], $row['absence_reported'], $row['swaps'], $row['absences']]);
            }
            fclose($stream);
        }, "indicadores-setores-{$month}.csv", ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
