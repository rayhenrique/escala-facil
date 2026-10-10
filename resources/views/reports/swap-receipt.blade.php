@php
    $receipt = \App\Support\ShiftSwapReceipt::withTimeLabels($receipt);
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Comprovante {{ $receipt['reference'] }} — Escala Fácil</title>
    <style>
        @page { size: a4 portrait; margin: 15mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #12333a;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #005f56;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: middle; border: none; padding: 0; }
        .brand-title {
            font-size: 20px;
            font-weight: bold;
            color: #005f56;
            margin: 0;
        }
        .brand-subtitle {
            font-size: 10px;
            color: #5e7377;
            margin: 2px 0 0;
        }
        .doc-reference {
            font-size: 13px;
            font-weight: bold;
            color: #005f56;
            font-family: monospace;
            text-align: right;
            margin: 0;
        }
        .doc-meta {
            font-size: 10px;
            color: #5e7377;
            text-align: right;
            margin: 2px 0 0;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #005f56;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 16px;
            margin-bottom: 6px;
            border-bottom: 1px solid #c9dad7;
            padding-bottom: 3px;
        }
        table.data-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        table.data-grid th, table.data-grid td {
            border: 1px solid #c9dad7;
            padding: 7px 10px;
            text-align: left;
            font-size: 11px;
        }
        table.data-grid th {
            background-color: #f3f8f7;
            font-size: 10px;
            text-transform: uppercase;
            color: #4a6367;
            width: 28%;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
        }
        .badge-approved { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .badge-pending { background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .badge-rejected { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .movement-box {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 10px 12px;
            margin-top: 6px;
            margin-bottom: 14px;
        }
        .movement-item {
            margin-bottom: 6px;
            font-size: 11px;
        }
        .movement-item strong { color: #0f172a; }
        .movement-item:last-child { margin-bottom: 0; }
        .rejection-box {
            background-color: #fef2f2;
            border: 1px solid #f87171;
            border-radius: 6px;
            padding: 10px 12px;
            margin-top: 10px;
            color: #991b1b;
        }
        .signatures {
            margin-top: 40px;
            width: 100%;
            border-collapse: collapse;
        }
        .signatures td {
            border: none;
            text-align: center;
            padding: 0 15px;
            vertical-align: top;
            width: 33.33%;
        }
        .sign-line {
            border-top: 1px solid #12333a;
            padding-top: 5px;
            font-size: 10px;
            color: #12333a;
            font-weight: bold;
        }
        .sign-role {
            font-size: 9px;
            color: #5e7377;
            margin-top: 2px;
        }
        .footer-note {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px dashed #c9dad7;
            font-size: 9px;
            color: #5e7377;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td>
                    <h1 class="brand-title">Escala Fácil</h1>
                    <p class="brand-subtitle">Comprovante Oficial de Troca de Plantão</p>
                </td>
                <td>
                    <p class="doc-reference">{{ $receipt['reference'] }}</p>
                    <p class="doc-meta">Evento: {{ $receipt['event_label'] }} · Gerado em {{ $generatedAt }}</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">Informações Gerais</div>
    <table class="data-grid">
        <tr>
            <th>Situação da Troca</th>
            <td>
                @php
                    $badgeClass = match($receipt['status']) {
                        'approved' => 'badge-approved',
                        'rejected' => 'badge-rejected',
                        default => 'badge-pending',
                    };
                @endphp
                <span class="badge {{ $badgeClass }}">{{ $receipt['status_label'] }}</span>
            </td>
        </tr>
        <tr>
            <th>Data e Hora do Registro</th>
            <td>{{ \Carbon\CarbonImmutable::parse($receipt['recorded_at'])->format('d/m/Y \à\s H:i') }}</td>
        </tr>
        @if (! empty($receipt['manager']))
            <tr>
                <th>Responsável pela Decisão</th>
                <td>{{ $receipt['manager'] }}</td>
            </tr>
        @endif
    </table>

    <div class="section-title">Plantões Envolvidos</div>
    <table class="data-grid">
        <tr>
            <th>Plantão Original (Cedido)</th>
            <td>
                <strong>{{ \Carbon\CarbonImmutable::parse($receipt['shift_date'])->format('d/m/Y') }}</strong>, das {{ $receipt['start_time'] }} às {{ $receipt['end_time_label'] }}<br>
                <span>{{ $receipt['sector'] }} · {{ $receipt['category'] }}</span><br>
                <small style="color: #005f56; font-weight: bold;">Plantonista solicitante: {{ $receipt['requester'] }}</small>
            </td>
        </tr>
        @if (! empty($receipt['has_counterpart']) && ! empty($receipt['counterpart_shift_date']))
            <tr>
                <th>Plantão de Contrapartida</th>
                <td>
                    <strong>{{ \Carbon\CarbonImmutable::parse($receipt['counterpart_shift_date'])->format('d/m/Y') }}</strong>, das {{ $receipt['counterpart_start_time'] }} às {{ $receipt['counterpart_end_time_label'] }}<br>
                    <span>{{ $receipt['counterpart_sector'] }} · {{ $receipt['counterpart_category'] }}</span><br>
                    <small style="color: #005f56; font-weight: bold;">Plantonista substituto: {{ $receipt['substitute'] }}</small>
                </td>
            </tr>
        @endif
    </table>

    <div class="section-title">Participantes</div>
    <table class="data-grid">
        <tr>
            <th>Solicitante</th>
            <td><strong>{{ $receipt['requester'] }}</strong></td>
        </tr>
        <tr>
            <th>Substituto</th>
            <td><strong>{{ $receipt['substitute'] }}</strong></td>
        </tr>
    </table>

    <div class="section-title">Movimentação da Escala</div>
    <div class="movement-box">
        @if (! empty($receipt['has_counterpart']) && ! empty($receipt['counterpart_shift_date']))
            <div class="movement-item">
                ⇄ <strong>{{ $receipt['requester'] }}</strong> assume o plantão do dia <strong>{{ \Carbon\CarbonImmutable::parse($receipt['counterpart_shift_date'])->format('d/m/Y') }}</strong>, das {{ $receipt['counterpart_start_time'] }} às {{ $receipt['counterpart_end_time_label'] }} ({{ $receipt['counterpart_sector'] }} · {{ $receipt['counterpart_category'] }}).
            </div>
            <div class="movement-item" style="margin-top: 6px;">
                ⇄ <strong>{{ $receipt['substitute'] }}</strong> assume o plantão do dia <strong>{{ \Carbon\CarbonImmutable::parse($receipt['shift_date'])->format('d/m/Y') }}</strong>, das {{ $receipt['start_time'] }} às {{ $receipt['end_time_label'] }} ({{ $receipt['sector'] }} · {{ $receipt['category'] }}).
            </div>
        @else
            <div class="movement-item">
                → <strong>{{ $receipt['substitute'] }}</strong> assume o plantão cedido por <strong>{{ $receipt['requester'] }}</strong> no dia <strong>{{ \Carbon\CarbonImmutable::parse($receipt['shift_date'])->format('d/m/Y') }}</strong>, das {{ $receipt['start_time'] }} às {{ $receipt['end_time_label'] }} ({{ $receipt['sector'] }} · {{ $receipt['category'] }}).
            </div>
        @endif
    </div>

    @if (! empty($receipt['rejection_reason']))
        <div class="rejection-box">
            <strong>Justificativa da Gerência:</strong>
            <p style="margin: 4px 0 0;">{{ $receipt['rejection_reason'] }}</p>
        </div>
    @endif

    <table class="signatures">
        <tr>
            <td>
                <div class="sign-line">{{ $receipt['requester'] }}</div>
                <div class="sign-role">Plantonista Solicitante</div>
            </td>
            <td>
                <div class="sign-line">{{ $receipt['substitute'] }}</div>
                <div class="sign-role">Plantonista Substituto</div>
            </td>
            <td>
                <div class="sign-line">{{ $receipt['manager'] ?? 'Gerência / Chefia' }}</div>
                <div class="sign-role">Gerência / Coordenação</div>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        Escala Fácil · Documento eletrônico oficial para conferência operacional e arquivamento · Autenticação: {{ $receipt['reference'] }}
    </div>
</body>
</html>
