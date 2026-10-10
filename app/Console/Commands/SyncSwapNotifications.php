<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncSwapNotifications extends Command
{
    protected $signature = 'app:sync-swap-notifications';

    protected $description = 'Verifica notificações de trocas sem alterar comprovantes históricos (imutáveis desde a v1.16.3)';

    public function handle(): int
    {
        $total = DB::table('notifications')->count();

        if ($total === 0) {
            $this->info('Nenhuma notificação de troca encontrada.');
        } else {
            $this->info("{$total} notificações preservadas. Comprovantes históricos são imutáveis; o estado atual é exibido em tempo de execução.");
        }

        $this->info('0 notificações sincronizadas com sucesso.');

        return self::SUCCESS;
    }
}
