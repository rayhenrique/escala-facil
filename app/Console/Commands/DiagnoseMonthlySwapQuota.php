<?php

namespace App\Console\Commands;

use App\Models\OperationalAuditEvent;
use App\Models\ShiftSwap;
use App\Support\MonthlySwapQuotaDiagnosis;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseMonthlySwapQuota extends Command
{
    protected $signature = 'app:diagnose-monthly-swap-quota
                            {--swap=* : IDs de trocas a examinar (pode repetir)}
                            {--jsonl : Emite contexto, achados e resumo em JSON Lines}';

    protected $description = 'Diagnostica datas de aprovação divergentes ou incertas sem alterar históricos ou cotas';

    public function handle(MonthlySwapQuotaDiagnosis $diagnosis): int
    {
        if (config('app.timezone') !== MonthlySwapQuotaDiagnosis::TIMEZONE) {
            $this->error('O diagnóstico exige APP_TIMEZONE=America/Maceio; não converta timestamps históricos por suposição.');

            return self::INVALID;
        }
        $ids = $this->option('swap');
        foreach ($ids as $id) {
            if (! ctype_digit((string) $id) || (int) $id < 1) {
                $this->error('Cada --swap deve conter um ID inteiro positivo.');

                return self::INVALID;
            }
        }
        $counts = ['checked' => 0, 'divergent' => 0, 'uncertain' => 0];

        // No explicit row locks, updates, saves, DDL or corrections. A read transaction keeps
        // the scan consistent on SQLite and the default MySQL REPEATABLE READ.
        DB::connection()->transaction(function () use ($ids, $diagnosis, &$counts): void {
            // Establish the snapshot before reading the clock, so approvals
            // committed while starting the scan are not mistaken for future dates.
            $upperId = ShiftSwap::query()->max('id') ?? 0;
            $asOf = CarbonImmutable::now(MonthlySwapQuotaDiagnosis::TIMEZONE);
            $this->emit(['type' => 'context', 'timezone' => MonthlySwapQuotaDiagnosis::TIMEZONE, 'as_of' => $asOf->toIso8601String(), 'read_only' => true]);
            ShiftSwap::query()->where('id', '<=', $upperId)->when($ids !== [], fn ($query) => $query->whereKey($ids))
                ->chunkById(200, function ($swaps) use ($diagnosis, $asOf, &$counts): void {
                    $events = OperationalAuditEvent::query()->where('entity_type', 'swap')->where('action', 'approved')
                        ->whereIn('entity_id', $swaps->modelKeys())->orderBy('id')->get()->groupBy('entity_id');
                    foreach ($swaps as $swap) {
                        $counts['checked']++;
                        $finding = $diagnosis->inspect($swap, $events->get($swap->id, collect()), $asOf);
                        if ($finding) {
                            $counts[$finding['classification']]++;
                            $this->emit(['type' => 'finding'] + $finding);
                        }
                    }
                });
        });
        $this->emit(['type' => 'summary'] + $counts);

        return $counts['divergent'] + $counts['uncertain'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function emit(array $record): void
    {
        if ($this->option('jsonl')) {
            $this->line(json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

            return;
        }
        if ($record['type'] === 'context') {
            $this->info('Diagnóstico somente leitura — America/Maceio. Nenhuma correção será aplicada.');
        } elseif ($record['type'] === 'finding') {
            $this->line('Troca #'.$record['swap_id'].' ['.$record['classification'].'] approved_at='.($record['approved_at'] ?? 'NULL').': '.implode(', ', $record['issues']));
        } else {
            $this->info("Examinadas: {$record['checked']}; divergentes: {$record['divergent']}; incertas: {$record['uncertain']}.");
        }
    }
}
