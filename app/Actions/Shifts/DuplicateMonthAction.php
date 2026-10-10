<?php

namespace App\Actions\Shifts;

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Shift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DuplicateMonthAction
{
    public const MODE_DAY = 'day';

    public const MODE_WEEKDAY = 'weekday';

    /** @return array{source_month: string, target_month: string, mode: string, items: list<array<string, mixed>>, totals: array{total: int, valid: int, adjusted: int, blocked: int}} */
    public function preview(User $manager, string $sourceMonth, string $targetMonth, string $mode = self::MODE_DAY): array
    {
        Gate::forUser($manager)->authorize('create', Shift::class);

        [$sourceYear, $sourceMonthNumber] = $this->parseMonth($sourceMonth, 'source_month');
        [$targetYear, $targetMonthNumber] = $this->parseMonth($targetMonth, 'target_month');

        if ($sourceMonth === $targetMonth) {
            throw ValidationException::withMessages([
                'target_month' => ['O mês de destino deve ser diferente do mês de origem.'],
            ]);
        }

        if (! in_array($mode, [self::MODE_DAY, self::MODE_WEEKDAY], true)) {
            throw ValidationException::withMessages([
                'mode' => ['Escolha um mapeamento de datas válido.'],
            ]);
        }

        $sourceShifts = Shift::query()
            ->with(['sector', 'category', 'user'])
            ->whereYear('date', $sourceYear)
            ->whereMonth('date', $sourceMonthNumber)
            ->orderBy('date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        $existing = Shift::query()
            ->with('user')
            ->whereYear('date', $targetYear)
            ->whereMonth('date', $targetMonthNumber)
            ->get();

        $items = $this->buildItems($sourceShifts, $existing, $targetYear, $targetMonthNumber, $mode);

        return [
            'source_month' => $sourceMonth,
            'target_month' => $targetMonth,
            'mode' => $mode,
            'items' => $items,
            'totals' => [
                'total' => count($items),
                'valid' => collect($items)->where('status', 'valid')->count(),
                'adjusted' => collect($items)->where('status', 'adjusted')->count(),
                'blocked' => collect($items)->where('status', 'blocked')->count(),
            ],
        ];
    }

    /** @return array{created: int, ignored: int, adjusted: int} */
    public function execute(User $manager, string $sourceMonth, string $targetMonth, string $mode, array $selectedIds): array
    {
        Gate::forUser($manager)->authorize('create', Shift::class);

        $selectedIds = collect($selectedIds)->map(fn ($id) => (int) $id)->unique()->values()->all();

        if ($selectedIds === []) {
            return ['created' => 0, 'ignored' => 0, 'adjusted' => 0];
        }

        return DB::transaction(function () use ($targetMonth, $mode, $selectedIds): array {
            [$targetYear, $targetMonthNumber] = $this->parseMonth($targetMonth, 'target_month');

            $existing = Shift::query()
                ->with('user')
                ->whereYear('date', $targetYear)
                ->whereMonth('date', $targetMonthNumber)
                ->lockForUpdate()
                ->get();

            $sourceShifts = Shift::query()
                ->with(['sector', 'category', 'user'])
                ->whereKey($selectedIds)
                ->orderBy('date')
                ->orderBy('start_time')
                ->orderBy('id')
                ->get()
                ->keyBy('id');

            $ordered = collect($selectedIds)->map(fn (int $id) => $sourceShifts->get($id))->filter();
            $items = $this->buildItems($ordered, $existing, $targetYear, $targetMonthNumber, $mode);

            $created = 0;
            $adjusted = 0;

            foreach ($items as $item) {
                if (! in_array($item['status'], ['valid', 'adjusted'], true)) {
                    continue;
                }

                Shift::query()->create([
                    'date' => $item['date'],
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],
                    'sector_id' => $item['sector_id'],
                    'category_id' => $item['category_id'],
                    'user_id' => $item['user_id'],
                    'status' => $item['target_status'],
                ]);

                $created++;
                $adjusted += $item['status'] === 'adjusted' ? 1 : 0;
            }

            return ['created' => $created, 'ignored' => count($items) - $created, 'adjusted' => $adjusted];
        });
    }

    /** @return array{int, int} */
    private function parseMonth(string $month, string $field): array
    {
        if (! preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month, $matches)) {
            throw ValidationException::withMessages([
                $field => ['Informe um mês válido no formato AAAA-MM.'],
            ]);
        }

        return [(int) $matches[1], (int) $matches[2]];
    }

    /**
     * @param  Collection<int, Shift>  $sourceShifts
     * @param  Collection<int, Shift>  $existing
     * @return list<array<string, mixed>>
     */
    private function buildItems(Collection $sourceShifts, Collection $existing, int $targetYear, int $targetMonth, string $mode): array
    {
        $users = User::query()
            ->whereKey($sourceShifts->pluck('user_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        $today = today()->format('Y-m-d');
        $acceptedSignatures = [];
        $acceptedSlots = [];
        $items = [];

        foreach ($sourceShifts as $shift) {
            $targetDate = $this->mapDate($shift->date->format('Y-m-d'), $targetYear, $targetMonth, $mode);
            $start = substr((string) $shift->start_time, 0, 5);
            $end = substr((string) $shift->end_time, 0, 5);

            $item = [
                'source_id' => $shift->id,
                'date' => $targetDate,
                'date_label' => $targetDate ? CarbonImmutable::parse($targetDate)->format('d/m/Y') : '—',
                'start_time' => $start,
                'end_time' => $end,
                'sector_id' => $shift->sector_id,
                'category_id' => $shift->category_id,
                'user_id' => $shift->user_id,
                'sector' => $shift->sector->name,
                'category' => $shift->category->name,
                'professional' => $shift->user?->name ?? 'Sem profissional alocado',
                'status' => 'valid',
                'status_label' => 'Pronto para copiar',
                'target_status' => $shift->status->value,
                'reasons' => [],
            ];

            if ($targetDate === null) {
                $item = $this->block($item, 'Data inexistente no mês de destino para este mapeamento.');
            } elseif ($targetDate < $today) {
                $item = $this->block($item, 'Data no passado: o destino precisa ser hoje ou uma data futura.');
            }

            if ($item['status'] !== 'blocked') {
                $signature = implode('|', [$targetDate, $start, $end, $shift->sector_id, $shift->category_id, $shift->user_id ?? '']);

                if ($this->signatureExists($existing, $signature) || isset($acceptedSignatures[$signature])) {
                    $item = $this->block($item, 'Plantão idêntico já existe no destino; nada será sobrescrito.');
                }
            }

            if ($item['status'] !== 'blocked' && $shift->user_id !== null) {
                $professional = $users->get($shift->user_id);

                if (! $professional) {
                    $item = $this->block($item, 'Profissional original não existe mais no sistema.');
                } elseif ($professional->role !== UserRole::Professional) {
                    $item = $this->block($item, 'O responsável original não possui mais perfil profissional.');
                } elseif ($professional->category_id !== $shift->category_id || $professional->sector_id !== $shift->sector_id) {
                    $item = $this->block($item, 'Profissional incompatível com o setor ou a categoria atuais.');
                } elseif ($this->hasTimeConflict($existing, $acceptedSlots, $shift->user_id, $targetDate, $start, $end)) {
                    $item = $this->block($item, 'Conflito de horário com outro plantão do profissional nesta data.');
                }
            }

            if ($item['status'] !== 'blocked' && $shift->status === ShiftStatus::AbsenceReported) {
                $item['status'] = 'adjusted';
                $item['status_label'] = 'Copiar como descoberto';
                $item['target_status'] = ShiftStatus::Uncovered->value;
                $item['reasons'][] = 'Ausências não são copiadas: o plantão será criado como Descoberto.';
            }

            if ($item['status'] !== 'blocked') {
                $acceptedSignatures[implode('|', [$targetDate, $start, $end, $shift->sector_id, $shift->category_id, $shift->user_id ?? ''])] = true;
                $acceptedSlots[] = ['user_id' => $shift->user_id, 'date' => $targetDate, 'start' => $start, 'end' => $end];
            }

            $items[] = $item;
        }

        return $items;
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function block(array $item, string $reason): array
    {
        $item['status'] = 'blocked';
        $item['status_label'] = 'Não será copiado';
        $item['reasons'][] = $reason;

        return $item;
    }

    /** @param Collection<int, Shift> $shifts */
    private function signatureExists(Collection $shifts, string $signature): bool
    {
        foreach ($shifts as $shift) {
            $candidate = implode('|', [
                $shift->date instanceof \DateTimeInterface ? $shift->date->format('Y-m-d') : (string) $shift->date,
                substr((string) $shift->start_time, 0, 5),
                substr((string) $shift->end_time, 0, 5),
                $shift->sector_id,
                $shift->category_id,
                $shift->user_id ?? '',
            ]);

            if ($candidate === $signature) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, Shift>  $shifts
     * @param  list<array{user_id: int|null, date: string|null, start: string, end: string}>  $batchSlots
     */
    private function hasTimeConflict(Collection $shifts, array $batchSlots, int $userId, ?string $date, string $start, string $end): bool
    {
        if ($date === null) {
            return false;
        }

        [$candidateStart, $candidateEnd] = $this->interval($start, $end);

        foreach ($shifts as $shift) {
            if ($shift->user_id !== $userId) {
                continue;
            }

            $shiftDate = $shift->date instanceof \DateTimeInterface ? $shift->date->format('Y-m-d') : (string) $shift->date;

            if ($shiftDate !== $date) {
                continue;
            }

            [$otherStart, $otherEnd] = $this->interval(
                substr((string) $shift->start_time, 0, 5),
                substr((string) $shift->end_time, 0, 5)
            );

            if ($candidateStart < $otherEnd && $otherStart < $candidateEnd) {
                return true;
            }
        }

        foreach ($batchSlots as $slot) {
            if ($slot['user_id'] !== $userId || $slot['date'] !== $date) {
                continue;
            }

            [$otherStart, $otherEnd] = $this->interval($slot['start'], $slot['end']);

            if ($candidateStart < $otherEnd && $otherStart < $candidateEnd) {
                return true;
            }
        }

        return false;
    }

    /** @return array{int, int} */
    private function interval(string $start, string $end): array
    {
        [$startHour, $startMinute] = array_map('intval', explode(':', $start));
        [$endHour, $endMinute] = array_map('intval', explode(':', $end));

        $from = $startHour * 60 + $startMinute;
        $to = $endHour * 60 + $endMinute;

        if ($to <= $from) {
            $to += 24 * 60;
        }

        return [$from, $to];
    }

    private function mapDate(string $sourceDate, int $targetYear, int $targetMonth, string $mode): ?string
    {
        $source = CarbonImmutable::createFromFormat('!Y-m-d', $sourceDate);

        if ($source === false) {
            return null;
        }

        if ($mode === self::MODE_WEEKDAY) {
            $occurrence = intdiv($source->day - 1, 7) + 1;
            $candidate = CarbonImmutable::createFromFormat('!Y-m-d', sprintf('%04d-%02d-01', $targetYear, $targetMonth));

            $daysToAdd = ($source->dayOfWeek - $candidate->dayOfWeek + 7) % 7;
            $candidate = $candidate->addDays($daysToAdd)->addWeeks($occurrence - 1);

            return $candidate->month === $targetMonth ? $candidate->format('Y-m-d') : null;
        }

        if (! checkdate($targetMonth, $source->day, $targetYear)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $targetYear, $targetMonth, $source->day);
    }
}
