<?php

namespace App\Actions\Shifts;

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Http\Requests\SaveShiftRequest;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\User;
use App\Services\ShiftSpreadsheetService;
use App\Support\OperationalAuditContext;
use App\Support\ShiftPeriod;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Reader;

class ImportShiftsAction
{
    public const MAX_ROWS = 2000;

    /**
     * @return array{created: int, skipped: int}
     *
     * @throws ValidationException
     */
    public function execute(User $manager, string $path): array
    {
        Gate::forUser($manager)->authorize('create', Shift::class);

        $rows = $this->readDataRows($path);
        $sectors = $this->referenceMap(Sector::query()->orderBy('name')->pluck('name', 'id'));
        $categories = $this->referenceMap(Category::query()->orderBy('name')->pluck('name', 'id'));

        $errors = [];
        $validated = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $parsed = $this->parseRow($row, $line, $sectors, $categories);

            if (! is_array($parsed)) {
                $errors[] = $parsed;

                continue;
            }

            [$payload, $signature] = $parsed;

            if (isset($seen[$signature]) || $this->duplicateExists($payload)) {
                $seen[$signature] = true;

                continue;
            }

            $seen[$signature] = true;

            try {
                $validated[] = (new SaveShiftRequest)->validatePayload($payload, $manager);
            } catch (ValidationException $exception) {
                $errors[] = "Linha {$line}: ".collect($exception->errors())->flatten()->first();
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['arquivo' => $errors]);
        }

        if ($validated === []) {
            throw ValidationException::withMessages([
                'arquivo' => ['O arquivo não contém linhas de plantão para importar.'],
            ]);
        }

        $created = 0;

        app(OperationalAuditContext::class)->transaction($manager, function () use ($validated, &$created): void {
            foreach ($validated as $attributes) {
                Shift::query()->create($attributes);
                $created++;
            }
        });

        return ['created' => $created, 'skipped' => count($seen) - $created];
    }

    /** @return list<list<mixed>> */
    private function readDataRows(string $path): array
    {
        $reader = new Reader;

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                $rows = [];

                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();

                    if (count($rows) > self::MAX_ROWS + 1) {
                        throw ValidationException::withMessages([
                            'arquivo' => ['O arquivo excede o limite de '.self::MAX_ROWS.' linhas de plantão.'],
                        ]);
                    }
                }

                $this->assertHeader($rows[0] ?? []);

                return array_slice($rows, 1);
            }
        } finally {
            $reader->close();
        }

        throw ValidationException::withMessages([
            'arquivo' => ['Não foi possível ler a primeira aba da planilha.'],
        ]);
    }

    /** @param list<mixed> $header */
    private function assertHeader(array $header): void
    {
        $normalized = array_map(fn ($cell): string => $this->normalizeText($this->cellToString($cell)), $header);
        $expected = array_map(fn ($cell): string => $this->normalizeText($cell), ShiftSpreadsheetService::HEADERS);

        if (! in_array($normalized, [$expected, array_slice($expected, 0, 7), array_slice($expected, 0, 6)], true)) {
            throw ValidationException::withMessages([
                'arquivo' => ['O cabeçalho não segue o modelo padrão. Baixe o modelo em Gestão de escalas e tente novamente.'],
            ]);
        }
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int>  $sectors
     * @param  array<string, int>  $categories
     * @return array{array<string, mixed>, string}|string payload+assinatura ou mensagem de erro
     */
    private function parseRow(array $row, int $line, array $sectors, array $categories): array|string
    {
        $cells = array_pad(array_map(fn ($cell): string => trim($this->cellToString($cell)), $row), 6, '');
        [$dateRaw, $startRaw, $endRaw, $sectorRaw, $categoryRaw, $emailRaw] = $cells;

        $date = $this->parseDate($row[0] ?? null);

        if ($date === null) {
            return "Linha {$line}: informe a data no formato DD/MM/AAAA.";
        }

        $start = $this->parseTime($row[1] ?? null);

        if ($start === null) {
            return "Linha {$line}: informe o início no formato HH:MM (24 horas).";
        }

        $end = $this->parseTime($row[2] ?? null);

        if ($end === null) {
            return "Linha {$line}: informe o fim no formato HH:MM (24 horas).";
        }

        $endDate = null;
        if (trim($this->cellToString($row[6] ?? null)) !== '') {
            $endDate = $this->parseDate($row[6]);
            if ($endDate === null) {
                return "Linha {$line}: informe a data de término no formato DD/MM/AAAA.";
            }
        }
        $period = new ShiftPeriod($date, $start, $end, $endDate);
        $duration = trim($this->cellToString($row[7] ?? null));
        if ($duration !== '' && (! ctype_digit($duration) || (int) $duration !== $period->durationMinutes)) {
            return "Linha {$line}: a duração deve corresponder às datas e aos horários informados ({$period->durationMinutes} minutos).";
        }

        $sectorId = $sectors[$this->normalizeText($sectorRaw)] ?? null;

        if ($sectorId === null) {
            return "Linha {$line}: setor '{$sectorRaw}' não encontrado. Confira a aba Setores do modelo.";
        }

        $categoryId = $categories[$this->normalizeText($categoryRaw)] ?? null;

        if ($categoryId === null) {
            return "Linha {$line}: categoria '{$categoryRaw}' não encontrada. Confira a aba Categorias do modelo.";
        }

        $userId = null;

        if ($emailRaw !== '') {
            $professional = User::query()
                ->where('role', UserRole::Professional)
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($emailRaw)])
                ->first(['id']);

            if (! $professional) {
                return "Linha {$line}: nenhum profissional encontrado com o e-mail '{$emailRaw}'.";
            }

            $userId = $professional->id;
        }

        $payload = [
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'end_date' => $endDate,
            'sector_id' => $sectorId,
            'category_id' => $categoryId,
            'user_id' => $userId,
            'status' => ShiftStatus::Scheduled->value,
        ];

        $signature = implode('|', [$date, $start, $period->end->format('Y-m-d H:i'), $sectorId, $categoryId, $userId ?? '']);

        return [$payload, $signature];
    }

    /** @param array<string, mixed> $payload */
    private function duplicateExists(array $payload): bool
    {
        $period = new ShiftPeriod($payload['date'], $payload['start_time'], $payload['end_time'], $payload['end_date']);

        return Shift::query()
            ->whereDate('date', $payload['date'])
            ->where('sector_id', $payload['sector_id'])
            ->where('category_id', $payload['category_id'])
            ->when($payload['user_id'] === null,
                fn ($query) => $query->whereNull('user_id'),
                fn ($query) => $query->where('user_id', $payload['user_id']))
            ->get()
            ->contains(fn (Shift $shift): bool => $shift->period()->start->equalTo($period->start)
                && $shift->period()->end->equalTo($period->end));
    }

    /**
     * @param  Collection<int|string, string>  $names
     * @return array<string, int>
     */
    private function referenceMap($names): array
    {
        $map = [];

        foreach ($names as $id => $name) {
            $map[$this->normalizeText($name)] ??= (int) $id;
        }

        return $map;
    }

    private function normalizeText(string $value): string
    {
        return Str::ascii(mb_strtolower((string) preg_replace('/\s+/', ' ', trim($value))));
    }

    private function cellToString(mixed $cell): string
    {
        if ($cell instanceof DateTimeInterface) {
            return $cell->format('d/m/Y H:i');
        }

        if (is_bool($cell)) {
            return $cell ? '1' : '';
        }

        if ($cell === null) {
            return '';
        }

        return (string) $cell;
    }

    /** @param list<mixed> $row */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim($this->cellToString($cell)) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return (new DateTimeImmutable($value->format('Y-m-d')))->format('Y-m-d');
        }

        if (is_int($value) || is_float($value)) {
            if ($value < 20000 || $value > 80000) {
                return null;
            }

            return (new DateTimeImmutable('1899-12-30'))->modify('+'.(int) round($value).' days')->format('Y-m-d');
        }

        $text = trim((string) $value);

        foreach (['!d/m/Y', '!Y-m-d', '!d-m-Y'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $text);

            if ($parsed && $parsed->format($format === '!d/m/Y' ? 'd/m/Y' : ($format === '!Y-m-d' ? 'Y-m-d' : 'd-m-Y')) === $text) {
                return $parsed->format('Y-m-d');
            }
        }

        return null;
    }

    private function parseTime(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i');
        }

        if (is_int($value) || is_float($value)) {
            if ($value < 0 || $value >= 1) {
                return null;
            }

            $minutes = (int) round($value * 1440) % 1440;

            return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        $text = trim((string) $value);

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $text, $matches)) {
            $hour = (int) $matches[1];
            $minute = (int) $matches[2];

            if ($hour <= 23 && $minute <= 59) {
                return sprintf('%02d:%02d', $hour, $minute);
            }

            return null;
        }

        if (preg_match('/^(\d{3,4})$/', $text, $matches)) {
            $digits = str_pad($matches[1], 4, '0', STR_PAD_LEFT);
            $hour = (int) substr($digits, 0, 2);
            $minute = (int) substr($digits, 2, 2);

            if ($hour <= 23 && $minute <= 59) {
                return sprintf('%02d:%02d', $hour, $minute);
            }
        }

        return null;
    }
}
