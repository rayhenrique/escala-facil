<?php

namespace App\Console\Commands;

use App\Models\ShiftSwap;
use App\Models\User;
use App\Support\SwapReversionDiagnosis;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseSwapReversion extends Command
{
    protected $signature = 'app:diagnose-swap-reversion
                            {id : ID do pedido de troca}
                            {--manager= : ID do usuário para avaliar as Policies}
                            {--expected-snapshot= : Fingerprint da prévia, se disponível}
                            {--json : Emite diagnóstico estruturado}';

    protected $description = 'Diagnostica bloqueios de reversão somente em ambiente local/testing, sem alterar registros';

    public function handle(SwapReversionDiagnosis $diagnosis): int
    {
        // This investigation must never connect to a production database automatically.
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Execute em cópia isolada local/testing. Diagnóstico em produção não é permitido por este comando.');

            return self::INVALID;
        }
        foreach ([$this->argument('id'), $this->option('manager')] as $id) {
            if ($id !== null && (! ctype_digit((string) $id) || (int) $id < 1)) {
                $this->error('Informe IDs inteiros positivos.');

                return self::INVALID;
            }
        }
        $expected = $this->option('expected-snapshot');
        if ($expected !== null && ! preg_match('/^[a-f0-9]{64}$/', $expected)) {
            $this->error('Fingerprint inválido: use 64 caracteres hexadecimais minúsculos.');

            return self::INVALID;
        }
        $result = DB::connection()->transaction(function () use ($diagnosis, $expected): ?array {
            $swap = ShiftSwap::query()->find($this->argument('id'));
            if (! $swap) {
                return null;
            }
            $manager = $this->option('manager') ? User::query()->find($this->option('manager')) : null;
            if ($this->option('manager') && ! $manager) {
                return null;
            }

            return $diagnosis->inspect($swap, $manager, $expected);
        });
        if ($result === null) {
            $this->error('Pedido ou usuário solicitado não encontrado nesta base.');

            return self::INVALID;
        }
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info('Diagnóstico somente leitura do pedido #'.$result['swap']['id']);
            $this->line('Evidência: '.$result['approval']['source'].'; aprovações: '.$result['approval']['count']);
            foreach ($result['shifts'] as $shift) {
                $this->line('Plantão #'.$shift['id'].'; existe: '.($shift['exists'] ? 'sim' : 'não').'; diferenças: '.implode(', ', array_keys($shift['strict_differences'])));
            }
            foreach ($result['review']['blocks'] as $block) {
                $this->warn($block);
            }
            if (! $result['authorization']['evaluated']) {
                $this->line('Autorização não avaliada: nenhum --manager informado.');
            }
            foreach ($result['authorization'] as $ability => $permission) {
                if (is_array($permission) && ! $permission['allowed']) {
                    $this->warn('Policy '.$ability.': '.$permission['message']);
                }
            }
            if ($result['review']['expected_snapshot_matches'] === false) {
                $this->warn('O fingerprint informado difere da prévia atual.');
            }
            if ($result['review']['error']) {
                $this->error($result['review']['error']['class'].': '.$result['review']['error']['message']);
            }
            $this->line('Use --json para evidências e Policies. Nenhuma reversão foi executada.');
        }

        if ($result['review']['error']) {
            return 3;
        }
        $denied = collect($result['authorization'])->contains(fn ($value) => is_array($value) && ($value['allowed'] ?? true) === false);

        return ! $result['review']['can_revert'] || $denied || $result['review']['expected_snapshot_matches'] === false ? self::FAILURE : self::SUCCESS;
    }
}
