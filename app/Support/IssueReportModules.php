<?php

namespace App\Support;

class IssueReportModules
{
    /** @return array<string, string> */
    public static function all(): array
    {
        return [
            'agenda' => 'Minha escala',
            'calendario' => 'Calendário',
            'trocas' => 'Trocas de plantão',
            'ausencias' => 'Ausências',
            'notificacoes' => 'Notificações',
            'relatorios' => 'Relatórios',
            'escalas' => 'Escalas (gerência)',
            'cadastros' => 'Usuários, setores e categorias',
            'ajuda' => 'Ajuda e versões',
            'aplicativo' => 'Aplicativo instalado (PWA)',
            'outro' => 'Outro',
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function valid(string $module): bool
    {
        return array_key_exists($module, self::all());
    }

    public static function label(string $module): string
    {
        return self::all()[$module] ?? 'Outro';
    }
}
