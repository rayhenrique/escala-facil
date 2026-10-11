@props(['rows', 'swapStatus' => ''])
<section class="mt-8" aria-labelledby="sector-indicators-title">
    <div class="section-heading">
        <div><p class="eyebrow">Indicadores operacionais</p><h2 id="sector-indicators-title">Plantões por setor</h2></div>
        <span class="text-sm text-muted">{{ $rows->total() }} setor(es)</span>
    </div>
    <p class="mt-3 text-sm text-muted">Plantões iniciados no mês. Cobertura: agendados com profissional ÷ total. Trocas: mês de criação do pedido; ausências: mês de início do plantão. Quantidades de plantões não representam horas.</p>
    <details class="mt-3 rounded-lg border border-line bg-white p-4">
        <summary class="cursor-pointer text-sm font-semibold text-primary">Como calcular e interpretar os indicadores</summary>
        <x-report-indicator-definitions class="mt-3 space-y-2 text-sm text-muted" />
    </details>
    @if ($swapStatus !== '')<p class="mt-3 text-sm font-semibold">Situação atual dos pedidos filtrados: {{ \App\Enums\ShiftSwapStatus::tryFrom($swapStatus)?->label() ?? 'Filtro inválido' }}.</p>@endif
    <h3 class="mt-6 font-semibold">Situação atual dos plantões do mês</h3>
    <div class="mt-3 hidden overflow-x-auto md:block" tabindex="0" role="region" aria-label="Tabela da situação atual por setor">
        <table class="data-table">
            <caption class="sr-only">Situação atual por setor: quantidades de plantões e proporção atribuída</caption>
            <thead><tr><th scope="col">Setor</th><th scope="col" class="text-right">Plantões</th><th scope="col" class="text-right">Cobertos</th><th scope="col" class="text-right">Cobertura</th><th scope="col" class="text-right">Sem cobertura</th><th scope="col" class="text-right">Descobertos</th><th scope="col" class="text-right">Ausência informada</th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr><th scope="row">{{ $row['name'] }}</th><td class="text-right">{{ $row['total'] }}</td><td class="text-right">{{ $row['covered'] }}</td><td class="text-right">{{ $row['coverageRate'] === null ? '—' : $row['coverageRate'].'%' }}</td><td class="text-right">{{ $row['withoutCoverage'] }}</td><td class="text-right">{{ $row['uncovered'] }}</td><td class="text-right">{{ $row['absence_reported'] }}</td></tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">Nenhum setor corresponde aos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <h3 class="mt-6 hidden font-semibold md:block">Registros do período por setor</h3>
    <div class="mt-3 hidden overflow-x-auto md:block" tabindex="0" role="region" aria-label="Tabela de registros do período por setor">
        <table class="data-table">
            <caption class="sr-only">Pedidos criados no mês e ausências de plantões iniciados no mês</caption>
            <thead><tr><th scope="col">Setor</th><th scope="col" class="text-right">Pedidos de troca</th><th scope="col" class="text-right">Registros de ausência</th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr><th scope="row">{{ $row['name'] }}</th><td class="text-right">{{ $row['swaps'] }}</td><td class="text-right">{{ $row['absences'] }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted">Nenhum setor corresponde aos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3 space-y-4 md:hidden">
        @forelse ($rows as $row)
            <article class="record-card">
                <h3 class="font-semibold">{{ $row['name'] }}</h3>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-muted">Plantões</dt><dd>{{ $row['total'] }}</dd></div>
                    <div><dt class="text-muted">Cobertura</dt><dd>{{ $row['coverageRate'] === null ? '— (sem plantões)' : $row['coverageRate'].'% ('.$row['covered'].'/'.$row['total'].')' }}</dd></div>
                    <div><dt class="text-muted">Sem cobertura</dt><dd>{{ $row['withoutCoverage'] }}</dd></div>
                    <div><dt class="text-muted">Descobertos</dt><dd>{{ $row['uncovered'] }}</dd></div>
                    <div class="col-span-2"><dt class="text-muted">Ausência informada (estado atual)</dt><dd>{{ $row['absence_reported'] }}</dd></div>
                </dl>
                <p class="mt-4 border-t border-line pt-3 text-sm font-semibold">Registros do período</p>
                <dl class="mt-2 grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-muted">Pedidos de troca</dt><dd>{{ $row['swaps'] }}</dd></div>
                    <div><dt class="text-muted">Registros de ausência</dt><dd>{{ $row['absences'] }}</dd></div>
                </dl>
                @if ($row['total'] + $row['swaps'] + $row['absences'] === 0)<p class="mt-3 text-sm text-muted">Sem plantões ou registros nos filtros deste período.</p>@endif
            </article>
        @empty
            <div class="empty-state compact-empty"><p>Nenhum setor corresponde aos filtros.</p></div>
        @endforelse
    </div>
    <p class="mt-3 text-sm text-muted">Setores sem movimentos aparecem com zero; “—” significa que não há plantões para calcular a proporção.</p>
    <div class="mt-4">{{ $rows->links() }}</div>
</section>
