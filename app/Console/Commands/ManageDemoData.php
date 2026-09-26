<?php

namespace App\Console\Commands;

use App\Services\DemoDataService;
use DomainException;
use Illuminate\Console\Command;

class ManageDemoData extends Command
{
    protected $signature = 'demo:data
                            {action=seed : Ação: seed, clear ou refresh}
                            {--force : Permite executar em produção}';

    protected $description = 'Instala, remove ou recria o lote isolado de dados fictícios';

    public function handle(DemoDataService $demoData): int
    {
        $action = mb_strtolower((string) $this->argument('action'));

        if (! in_array($action, ['seed', 'clear', 'refresh'], true)) {
            $this->error('Ação inválida. Use seed, clear ou refresh.');

            return self::INVALID;
        }

        if ($this->laravel->environment('production') && ! $this->option('force')) {
            $this->error('Em produção, informe --force para alterar os dados de demonstração.');

            return self::FAILURE;
        }

        try {
            $allowInProduction = (bool) $this->option('force');

            if ($action === 'refresh') {
                try {
                    $demoData->clear($allowInProduction);
                } catch (DomainException) {
                    // Um refresh sem lote anterior equivale à primeira instalação.
                }
                $summary = $demoData->seed($allowInProduction);
                $message = 'Dados de demonstração recriados com sucesso.';
            } elseif ($action === 'clear') {
                $summary = $demoData->clear($allowInProduction);
                $message = 'Dados de demonstração removidos com sucesso.';
            } else {
                $summary = $demoData->seed($allowInProduction);
                $message = 'Dados de demonstração instalados com sucesso.';
            }
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($message);
        $this->table(['Tipo', 'Quantidade'], [
            ['Categorias', $summary['categories']],
            ['Setores', $summary['sectors']],
            ['Usuários', $summary['users']],
            ['Plantões', $summary['shifts']],
            ['Trocas', $summary['shift_swaps']],
            ['Ausências', $summary['absences']],
        ]);

        return self::SUCCESS;
    }
}
