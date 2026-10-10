<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Escala para afixar — {{ $roster['monthLabel'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #12333a; }
        h1 { font-size: 22px; margin-bottom: 0; text-align: center; }
        p.meta { font-size: 10px; color: #5e7377; text-align: center; }
        h2.day { font-size: 13px; margin: 16px 0 4px; background: #dff1f1; padding: 4px 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #c9dad7; padding: 5px 8px; text-align: left; }
        th { background: #f3f8f7; font-size: 10px; text-transform: uppercase; }
        .swap-mark { font-weight: bold; color: #b45309; }
        .legend { margin-top: 18px; font-size: 10px; }
        .legend span { margin-right: 16px; }
        .signatures { margin-top: 36px; width: 100%; }
        .signatures td { border: none; padding-top: 28px; font-size: 10px; text-align: center; }
        .sign-line { border-top: 1px solid #12333a; padding-top: 4px; margin: 0 24px; }
    </style>
</head>
<body>
    <h1>Escala de plantões — {{ $roster['monthLabel'] }}</h1>
    <p class="meta">Escala Fácil · {{ $roster['total'] }} plantão(ões) · Gerado em {{ $generatedAt }}</p>

    @forelse ($roster['days'] as $date => $shifts)
        <h2 class="day">{{ \Carbon\CarbonImmutable::parse($date)->translatedFormat('l, d \d\e F') }}</h2>
        <table>
            <thead><tr><th>Horário</th><th>Profissional</th><th>Setor</th><th>Categoria</th><th>Situação</th></tr></thead>
            <tbody>
                @foreach ($shifts as $shift)
                    <tr>
                        <td>{{ $shift->time_label }}</td>
                        <td>{{ $shift->user?->name ?? 'Sem profissional' }}</td>
                        <td>{{ $shift->sector->name }}</td>
                        <td>{{ $shift->category->name }}</td>
                        <td>
                            {{ $shift->status->label() }}
                            @if (in_array($shift->id, $roster['swappedShiftIds'], true))
                                <span class="swap-mark">● Troca</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p>Nenhum plantão neste período.</p>
    @endforelse

    <p class="legend">
        <span><strong>Legenda:</strong></span>
        <span>Agendado = com profissional</span>
        <span>Descoberto = sem profissional</span>
        <span class="swap-mark">● Troca = plantão transferido por troca aprovada</span>
    </p>

    <table class="signatures">
        <tr>
            <td><div class="sign-line">Gerência (assinatura e data)</div></td>
            <td><div class="sign-line">Responsável pela escala (assinatura e data)</div></td>
        </tr>
    </table>
</body>
</html>
