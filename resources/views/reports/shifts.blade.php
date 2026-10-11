<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de plantões — {{ $report['monthLabel'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #12333a; }
        h1 { font-size: 20px; margin-bottom: 0; }
        h2 { font-size: 14px; margin-top: 24px; border-bottom: 1px solid #c9dad7; padding-bottom: 4px; page-break-after: avoid; }
        .meta { font-size: 10px; color: #5e7377; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #c9dad7; padding: 5px 7px; text-align: left; }
        th { background: #dff1f1; }
        td.num, th.num { text-align: right; }
        ul.summary { list-style: none; padding: 0; }
        ul.summary li { margin: 3px 0; }
        tr { page-break-inside: avoid; }
        thead { display: table-header-group; }
        .report-section { page-break-inside: avoid; }
    </style>
</head>
<body>
    <h1>Relatório de plantões</h1>
    <p class="meta">Escala Fácil · Período: {{ $report['monthLabel'] }} · Gerado em {{ $generatedAt }}</p>
    <p class="meta">Filtros: setor {{ $report['filters']['sector_id'] ?: 'todos' }} · categoria {{ $report['filters']['category_id'] ?: 'todas' }} · situação das trocas {{ $report['filters']['swapStatusFilter'] ?: 'todas' }} · pesquisa de profissionais {{ $report['filters']['searchProfessional'] ?: 'sem pesquisa' }}.</p>
    <x-report-indicator-definitions class="meta" />

    <h2>Resumo</h2>
    <ul class="summary">
        <li>Total de plantões: <strong>{{ $report['summary']['total'] }}</strong></li>
        <li>Cobertura: <strong>{{ $report['summary']['coverageRate'] === null ? '—' : $report['summary']['coverageRate'].'%' }}</strong> ({{ $report['summary']['covered'] }} de {{ $report['summary']['total'] }})</li>
        <li>Descobertos: <strong>{{ $report['summary']['uncovered'] }}</strong> · Ausência informada: <strong>{{ $report['summary']['absenceReported'] }}</strong></li>
        <li>Ausências: <strong>{{ $report['summary']['absences'] }}</strong></li>
        <li>Trocas — aguardando profissional: <strong>{{ $report['summary']['swapsPendingProfessional'] }}</strong> · aguardando gerência: <strong>{{ $report['summary']['swapsPendingManager'] }}</strong> · aprovadas: <strong>{{ $report['summary']['swapsApproved'] }}</strong> · rejeitadas: <strong>{{ $report['summary']['swapsRejected'] }}</strong></li>
    </ul>

    @if ($report['isManager'])
    <h2>Plantões por setor - situação atual</h2>
    <table>
        <thead><tr><th>Setor</th><th class="num">Plantões</th><th class="num">Cobertos</th><th class="num">Cobertura</th><th class="num">Sem cobertura</th><th class="num">Descobertos</th><th class="num">Ausência informada</th></tr></thead>
        <tbody>
            @forelse ($report['sectorIndicators'] as $row)
                <tr><td>{{ $row['name'] }}</td><td class="num">{{ $row['total'] }}</td><td class="num">{{ $row['covered'] }}</td><td class="num">{{ $row['coverageRate'] === null ? '—' : $row['coverageRate'].'%' }}</td><td class="num">{{ $row['withoutCoverage'] }}</td><td class="num">{{ $row['uncovered'] }}</td><td class="num">{{ $row['absence_reported'] }}</td></tr>
            @empty
                <tr><td colspan="7">Nenhum setor corresponde aos filtros.</td></tr>
            @endforelse
        </tbody>
    </table>
    <h2>Registros do período por setor</h2>
    <table>
        <thead><tr><th>Setor</th><th class="num">Pedidos de troca</th><th class="num">Registros de ausência</th></tr></thead>
        <tbody>
            @foreach ($report['sectorIndicators'] as $row)
                <tr><td>{{ $row['name'] }}</td><td class="num">{{ $row['swaps'] }}</td><td class="num">{{ $row['absences'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
    @else
    <h2>Plantões por setor</h2>
    <table>
        <thead><tr><th>Setor</th><th class="num">Plantões</th><th class="num">Descobertos</th></tr></thead>
        <tbody>
            @forelse ($report['bySector'] as $row)
                <tr><td>{{ $row['name'] }}</td><td class="num">{{ $row['total'] }}</td><td class="num">{{ $row['uncovered'] }}</td></tr>
            @empty
                <tr><td colspan="3">Nenhum plantão no período.</td></tr>
            @endforelse
        </tbody>
    </table>
    @endif

    <h2>Plantões por categoria</h2>
    <table>
        <thead><tr><th>Categoria</th><th class="num">Plantões</th><th class="num">Descobertos</th></tr></thead>
        <tbody>
            @forelse ($report['byCategory'] as $row)
                <tr><td>{{ $row['name'] }}</td><td class="num">{{ $row['total'] }}</td><td class="num">{{ $row['uncovered'] }}</td></tr>
            @empty
                <tr><td colspan="3">Nenhum plantão no período.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Plantões por profissional</h2>
    <table>
        <thead><tr><th>Profissional</th><th>Setor / categoria</th><th class="num">Plantões</th><th class="num">Trocas aprovadas</th><th class="num">Ausências</th></tr></thead>
        <tbody>
            @forelse ($report['byProfessional'] as $row)
                <tr><td>{{ $row['name'] }}</td><td>{{ $row['team'] }}</td><td class="num">{{ $row['shifts'] }}</td><td class="num">{{ $row['approvedSwaps'] }} de 4</td><td class="num">{{ $row['absences'] }}</td></tr>
            @empty
                <tr><td colspan="5">Nenhum profissional alocado no período.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="report-section">
    <h2>{{ ($report['isManager'] ?? true) ? 'Trocas criadas no mês' : 'Minhas trocas no mês' }}</h2>
    <table>
        <thead><tr><th>Plantão</th><th>Solicitante → substituto</th><th>Status</th></tr></thead>
        <tbody>
            @forelse ($report['swaps'] as $swap)
                <tr>
                    <td>{{ $swap->shift->date->format('d/m/Y') }} · {{ $swap->shift->time_label }} · {{ $swap->shift->sector->name }}</td>
                    <td>{{ $swap->requester->name }} → {{ $swap->substitute->name }}</td>
                    <td>{{ $swap->status->label() }}</td>
                </tr>
            @empty
                <tr><td colspan="3">{{ ($report['isManager'] ?? true) ? 'Nenhuma troca criada neste mês.' : 'Você não possui trocas neste mês.' }}</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
</body>
</html>
