<?php

namespace App\Support;

use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Models\OperationalAuditEvent;
use Illuminate\Support\Facades\Auth;

class OperationalNavigation
{
    public const FIELDS = [
        'users' => ['search', 'roleFilter', 'page'],
        'sectors' => ['search', 'page'],
        'categories' => ['search', 'page'],
        'audit' => ['entity', 'record', 'action', 'author', 'dateFrom', 'dateTo', 'page'],
        'dashboard' => ['period', 'page'],
        'calendar' => ['month', 'selectedDate', 'sectorId', 'categoryId'],
        'shifts' => ['month', 'filter', 'focus', 'page', 'search', 'filterSectorId', 'filterCategoryId', 'dateFrom', 'dateTo'],
        'swaps' => ['request', 'page', 'swapStatusFilter', 'search', 'filterSectorId', 'filterCategoryId', 'dateFrom', 'dateTo'],
        'reports' => ['month', 'tab', 'sectorId', 'categoryId', 'searchProfessional', 'swapStatusFilter', 'professionalsPage', 'swapsPage', 'absencesPage', 'sectorsPage'],
    ];

    public static function id(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        abort_unless((is_int($value) || is_string($value)) && preg_match('/^[1-9][0-9]*$/', (string) $value), 404);
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);

        return $id;
    }

    /** Return state is descriptive; it never grants access to a record. */
    public static function clean(string $module, mixed $state, int $depth = 0): array
    {
        $result = [];
        foreach (self::FIELDS[$module] ?? [] as $key) {
            $value = is_array($state) ? ($state[$key] ?? null) : null;
            if (! is_scalar($value) || is_bool($value) || strlen((string) $value) > 250 || ($value === '' && ! ($module === 'swaps' && $key === 'swapStatusFilter'))) {
                continue;
            }
            $value = (string) $value;
            $valid = match ($key) {
                'record', 'focus', 'request', 'page', 'sectorId', 'categoryId', 'filterSectorId', 'filterCategoryId', 'professionalsPage', 'swapsPage', 'absencesPage', 'sectorsPage' => filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false,
                'month' => preg_match('/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/', $value),
                'selectedDate', 'dateFrom', 'dateTo' => preg_match('/^([1-9][0-9]{3})-(\d{2})-(\d{2})$/', $value, $parts) && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]),
                'roleFilter' => in_array($value, array_column(UserRole::cases(), 'value'), true),
                'entity' => isset(OperationalAuditEvent::ENTITIES[$value]),
                'action' => isset(OperationalAuditEvent::ACTIONS[$value]),
                'period' => in_array($value, ['1', '3', '7'], true),
                'filter' => in_array($value, ['scheduled', 'uncovered', 'absence_reported', 'uncovered_month'], true),
                'tab' => in_array($value, ['all', 'coverage', 'professionals', 'swaps', 'absences', 'wall'], true),
                'swapStatusFilter' => $value === '' || in_array($value, array_column(ShiftSwapStatus::cases(), 'value'), true),
                default => true,
            };
            if ($valid) {
                $result[$key] = $value;
            }
        }

        if ($depth < 3 && is_array($state) && is_string($state['from'] ?? null) && isset(self::FIELDS[$state['from']])) {
            $result['from'] = $state['from'];
            $result['context'] = self::clean($state['from'], $state['context'] ?? [], $depth + 1);
        }

        return $result;
    }

    public static function validStateShape(array $state, int $depth = 0): bool
    {
        if ($depth > 3 || count($state) > 16) {
            return false;
        }
        foreach ($state as $key => $value) {
            if ($key === 'context' && is_array($value)) {
                if (! self::validStateShape($value, $depth + 1)) {
                    return false;
                }
            } elseif ($value !== null && (! is_string($value) || strlen($value) > 250)) {
                return false;
            }
        }

        return true;
    }

    public static function url(string $module, array $parameters = [], ?string $from = null, array $context = []): string
    {
        $prefix = Auth::user()?->role === UserRole::Manager ? 'manager.' : 'professional.';
        abort_unless(isset(self::FIELDS[$module]), 404);
        abort_unless($prefix === 'manager.' || in_array($module, ['calendar', 'reports'], true), 403);
        if ($from !== null && isset(self::FIELDS[$from])) {
            $parameters += ['from' => $from, 'context' => self::clean($from, $context)];
        }

        return route($prefix.$module, $parameters);
    }

    public static function back(mixed $from, mixed $context): array
    {
        $manager = Auth::user()?->role === UserRole::Manager;
        $allowed = $manager ? array_keys(self::FIELDS) : ['calendar', 'reports'];
        $module = is_string($from) && in_array($from, $allowed, true) ? $from : ($manager ? 'dashboard' : 'calendar');
        $names = ['users' => 'Voltar aos usuários', 'sectors' => 'Voltar aos setores', 'categories' => 'Voltar às categorias', 'audit' => 'Voltar ao histórico', 'dashboard' => 'Voltar ao painel', 'calendar' => 'Voltar ao calendário', 'shifts' => 'Voltar à escala', 'swaps' => 'Voltar às trocas', 'reports' => 'Voltar ao relatório'];

        return ['url' => self::url($module, self::clean($module, $context)), 'label' => $names[$module]];
    }
}
