<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ShiftSpreadsheetService
{
    public const SHEET_SHIFTS = 'Plantões';

    public const SHEET_SECTORS = 'Setores';

    public const SHEET_CATEGORIES = 'Categorias';

    /** @var list<string> */
    public const HEADERS = [
        'Data (DD/MM/AAAA)',
        'Início (HH:MM)',
        'Fim (HH:MM)',
        'Setor',
        'Categoria',
        'E-mail do profissional (opcional)',
    ];

    public function templatePath(): string
    {
        $path = $this->temporaryPath('modelo-plantoes');

        $writer = new Writer;
        $writer->openToFile($path);

        $writer->getCurrentSheet()->setName(self::SHEET_SHIFTS);
        $writer->addRow(Row::fromValues(self::HEADERS));

        foreach ($this->exampleRows() as $example) {
            $writer->addRow(Row::fromValues($example));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName(self::SHEET_SECTORS);
        $writer->addRow(Row::fromValues(['Setores cadastrados — use estes nomes exatos na aba Plantões']));
        foreach (Sector::query()->orderBy('name')->pluck('name') as $name) {
            $writer->addRow(Row::fromValues([$name]));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName(self::SHEET_CATEGORIES);
        $writer->addRow(Row::fromValues(['Categorias cadastradas — use estes nomes exatos na aba Plantões']));
        foreach (Category::query()->orderBy('name')->pluck('name') as $name) {
            $writer->addRow(Row::fromValues([$name]));
        }

        $writer->close();

        return $path;
    }

    public function monthExportPath(string $month): string
    {
        [$year, $monthNumber] = array_pad(explode('-', $month), 2, null);

        $shifts = Shift::query()
            ->with(['sector', 'category', 'user'])
            ->when($year && $monthNumber, fn ($query) => $query->whereYear('date', $year)->whereMonth('date', $monthNumber))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $path = $this->temporaryPath("plantoes-{$month}");

        $writer = new Writer;
        $writer->openToFile($path);

        $writer->getCurrentSheet()->setName(self::SHEET_SHIFTS);
        $writer->addRow(Row::fromValues(self::HEADERS));

        foreach ($shifts as $shift) {
            $writer->addRow(Row::fromValues([
                $shift->date->format('d/m/Y'),
                substr((string) $shift->start_time, 0, 5),
                substr((string) $shift->end_time, 0, 5),
                $shift->sector->name,
                $shift->category->name,
                $shift->user?->email ?? '',
            ]));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName(self::SHEET_SECTORS);
        $writer->addRow(Row::fromValues(['Setores cadastrados — use estes nomes exatos na aba Plantões']));
        foreach (Sector::query()->orderBy('name')->pluck('name') as $name) {
            $writer->addRow(Row::fromValues([$name]));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName(self::SHEET_CATEGORIES);
        $writer->addRow(Row::fromValues(['Categorias cadastradas — use estes nomes exatos na aba Plantões']));
        foreach (Category::query()->orderBy('name')->pluck('name') as $name) {
            $writer->addRow(Row::fromValues([$name]));
        }

        $writer->close();

        return $path;
    }

    /** @return list<list<string>> */
    private function exampleRows(): array
    {
        $date = today()->addMonth()->startOfMonth()->format('d/m/Y');
        $sector = Sector::query()->orderBy('name')->value('name') ?? 'Emergência';
        $category = Category::query()->orderBy('name')->value('name') ?? 'Médico Clínico';

        return [
            [$date, '07:00', '19:00', $sector, $category, ''],
            [$date, '19:00', '07:00', $sector, $category, 'profissional@exemplo.com'],
        ];
    }

    private function temporaryPath(string $prefix): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.$prefix.'-'.uniqid().'.xlsx';
    }
}
