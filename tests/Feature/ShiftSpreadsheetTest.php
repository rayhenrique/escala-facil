<?php

namespace Tests\Feature;

use App\Actions\Shifts\ImportShiftsAction;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\ShiftManager;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\User;
use App\Services\ShiftSpreadsheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File as FakeFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ShiftSpreadsheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_download_template_with_reference_sheets(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $sector = Sector::factory()->create(['name' => 'Emergência Modelo']);
        $category = Category::factory()->create(['name' => 'Medicina Modelo']);

        $response = $this->actingAs($manager)->get(route('manager.shifts.template'));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $path = sys_get_temp_dir().'/template-test-'.uniqid().'.xlsx';
        file_put_contents($path, $response->streamedContent());

        try {
            $sheets = $this->readSheets($path);

            $this->assertSame(
                [ShiftSpreadsheetService::SHEET_SHIFTS, ShiftSpreadsheetService::SHEET_SECTORS, ShiftSpreadsheetService::SHEET_CATEGORIES],
                array_keys($sheets),
            );
            $this->assertSame(ShiftSpreadsheetService::HEADERS, $sheets[ShiftSpreadsheetService::SHEET_SHIFTS][0]);
            $this->assertContains([$sector->name], $sheets[ShiftSpreadsheetService::SHEET_SECTORS]);
            $this->assertContains([$category->name], $sheets[ShiftSpreadsheetService::SHEET_CATEGORIES]);
        } finally {
            @unlink($path);
        }
    }

    public function test_professional_cannot_download_template_or_export(): void
    {
        $professional = User::factory()->create(['role' => UserRole::Professional]);

        $this->actingAs($professional)->get(route('manager.shifts.template'))->assertForbidden();
        $this->actingAs($professional)->get(route('manager.shifts.export'))->assertForbidden();
    }

    public function test_valid_import_creates_shifts(): void
    {
        [$manager, $professional, $sector, $category] = $this->team();
        $date = today()->addDays(10)->format('d/m/Y');

        $path = $this->writeWorkbook([
            ShiftSpreadsheetService::HEADERS,
            [$date, '07:00', '19:00', $sector->name, $category->name, $professional->email],
            [$date, '19:00', '07:00', $sector->name, $category->name, ''],
        ]);

        try {
            $result = app(ImportShiftsAction::class)->execute($manager, $path);
        } finally {
            @unlink($path);
        }

        $this->assertSame(['created' => 2, 'skipped' => 0], $result);
        $this->assertDatabaseCount('shifts', 2);
        $this->assertDatabaseHas('shifts', [
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $professional->id,
        ]);
    }

    public function test_renamed_sector_rejects_the_entire_file(): void
    {
        [$manager, $professional, $sector, $category] = $this->team();
        $date = today()->addDays(10)->format('d/m/Y');

        $path = $this->writeWorkbook([
            ShiftSpreadsheetService::HEADERS,
            [$date, '07:00', '19:00', $sector->name, $category->name, $professional->email],
            [$date, '19:00', '07:00', $sector->name.' Renomeado', $category->name, ''],
        ]);

        try {
            app(ImportShiftsAction::class)->execute($manager, $path);
            $this->fail('A importação com setor renomeado deveria ter sido rejeitada.');
        } catch (ValidationException $exception) {
            $messages = collect($exception->errors())->flatten()->all();
            $this->assertCount(1, $messages);
            $this->assertStringContainsString('Linha 3', $messages[0]);
            $this->assertStringContainsString('setor', mb_strtolower($messages[0]));
        } finally {
            @unlink($path);
        }

        $this->assertDatabaseCount('shifts', 0);
    }

    public function test_duplicates_are_skipped_without_creating_new_records(): void
    {
        [$manager, $professional, $sector, $category] = $this->team();
        $date = today()->addDays(10);

        Shift::factory()->create([
            'date' => $date->format('Y-m-d'),
            'start_time' => '07:00',
            'end_time' => '19:00',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $professional->id,
            'status' => ShiftStatus::Scheduled,
        ]);

        $path = $this->writeWorkbook([
            ShiftSpreadsheetService::HEADERS,
            [$date->format('d/m/Y'), '07:00', '19:00', $sector->name, $category->name, $professional->email],
            [$date->format('d/m/Y'), '19:00', '07:00', $sector->name, $category->name, ''],
        ]);

        try {
            $result = app(ImportShiftsAction::class)->execute($manager, $path);
        } finally {
            @unlink($path);
        }

        $this->assertSame(['created' => 1, 'skipped' => 1], $result);
        $this->assertDatabaseCount('shifts', 2);
    }

    public function test_export_contains_only_the_requested_month(): void
    {
        [$manager, $professional, $sector, $category] = $this->team();

        foreach (['2026-11-12', '2026-12-12'] as $date) {
            Shift::factory()->create([
                'date' => $date,
                'start_time' => '07:00',
                'end_time' => '19:00',
                'sector_id' => $sector->id,
                'category_id' => $category->id,
                'user_id' => $professional->id,
                'status' => ShiftStatus::Scheduled,
            ]);
        }

        $response = $this->actingAs($manager)->get(route('manager.shifts.export', ['month' => '2026-11']));

        $response->assertOk();

        $path = sys_get_temp_dir().'/export-test-'.uniqid().'.xlsx';
        file_put_contents($path, $response->streamedContent());

        try {
            $sheets = $this->readSheets($path);
            $rows = $sheets[ShiftSpreadsheetService::SHEET_SHIFTS];

            $this->assertCount(2, $rows);
            $this->assertSame('12/11/2026', $rows[1][0]);
            $this->assertSame($professional->email, $rows[1][5]);
        } finally {
            @unlink($path);
        }
    }

    public function test_manager_can_import_shifts_through_the_schedule_interface(): void
    {
        [$manager, $professional, $sector, $category] = $this->team();
        $date = today()->addDays(10)->format('d/m/Y');

        $source = $this->writeWorkbook([
            ShiftSpreadsheetService::HEADERS,
            [$date, '07:00', '19:00', $sector->name, $category->name, $professional->email],
        ]);
        $upload = FakeFile::createWithContent('plantoes.xlsx', (string) file_get_contents($source));

        Livewire::actingAs($manager)
            ->test(ShiftManager::class)
            ->call('openImportModal')
            ->assertSet('showImportModal', true)
            ->upload('importFile', [$upload])
            ->call('confirmImport')
            ->assertHasNoErrors()
            ->assertSet('showImportModal', false)
            ->assertSee('Importação concluída: 1 plantão(ões) criado(s).');

        $this->assertDatabaseCount('shifts', 1);

        @unlink($source);
    }

    public function test_interface_reports_row_errors_without_creating_shifts(): void
    {
        [$manager, , $sector, $category] = $this->team();
        $date = today()->addDays(10)->format('d/m/Y');

        $source = $this->writeWorkbook([
            ShiftSpreadsheetService::HEADERS,
            [$date, '07:00', '19:00', 'Setor Inexistente', $category->name, ''],
        ]);
        $upload = FakeFile::createWithContent('plantoes.xlsx', (string) file_get_contents($source));

        Livewire::actingAs($manager)
            ->test(ShiftManager::class)
            ->call('openImportModal')
            ->upload('importFile', [$upload])
            ->call('confirmImport')
            ->assertSet('showImportModal', true)
            ->assertSee('A importação foi cancelada')
            ->assertSee('Linha 2');

        $this->assertDatabaseCount('shifts', 0);

        @unlink($source);
    }

    /** @return array{User, User, Sector, Category} */
    private function team(): array
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $category = Category::factory()->create();
        $sector = Sector::factory()->create();
        $professional = User::factory()->create([
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);

        return [$manager, $professional, $sector, $category];
    }

    /** @param list<list<string>> $rows */
    private function writeWorkbook(array $rows): string
    {
        $path = sys_get_temp_dir().'/import-test-'.uniqid().'.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);

        foreach ($rows as $values) {
            $writer->addRow(Row::fromValues($values));
        }

        $writer->close();

        return $path;
    }

    /** @return array<string, list<list<string>>> */
    private function readSheets(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);

        $sheets = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $rows = [];

                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(fn ($cell): string => trim((string) $cell), $row->toArray());
                }

                $sheets[$sheet->getName()] = $rows;
            }
        } finally {
            $reader->close();
        }

        return $sheets;
    }
}
