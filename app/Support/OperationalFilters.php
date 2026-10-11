<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class OperationalFilters
{
    /** Bound parameters and a portable explicit escape keep % and _ literal. */
    public static function name(Builder $query, string $term): Builder
    {
        $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($term))).'%';

        return $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term]);
    }

    /** Period refers to the start date of the primary shift, including both limits. */
    public static function shifts(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['filterSectorId'] !== '', fn ($q) => $q->where('sector_id', $filters['filterSectorId']))
            ->when($filters['filterCategoryId'] !== '', fn ($q) => $q->where('category_id', $filters['filterCategoryId']))
            ->when($filters['dateFrom'] !== '', fn ($q) => $q->where('date', '>=', $filters['dateFrom']))
            ->when($filters['dateTo'] !== '', fn ($q) => $q->where('date', '<', CarbonImmutable::parse($filters['dateTo'])->addDay()->format('Y-m-d')));
    }

    public static function swaps(Builder $query, array $filters): Builder
    {
        return $query->whereHas('shift', fn ($q) => self::shifts($q, $filters))
            ->when(trim($filters['search']) !== '', fn ($q) => $q->where(fn ($names) => $names
                ->whereHas('requester', fn ($user) => self::name($user, $filters['search']))
                ->orWhereHas('substitute', fn ($user) => self::name($user, $filters['search']))));
    }
}
