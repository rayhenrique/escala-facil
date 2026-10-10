<?php

namespace Tests\Feature;

use App\Actions\Shifts\DuplicateMonthAction;
use App\Actions\Shifts\ImportShiftsAction;
use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapEvent;
use App\Enums\UserRole;
use App\Http\Requests\SaveShiftRequest;
use App\Livewire\Manager\ShiftManager;
use App\Livewire\MonthlyCalendar;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Services\ShiftReportService;
use App\Services\ShiftSpreadsheetService;
use App\Support\ShiftCoverage;
use App\Support\ShiftPeriod;
use App\Support\ShiftSwapReceipt;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShiftTimingTest extends TestCase
{
    use RefreshDatabase;

    public static function periods(): array
    {
        return [
            'one minute is never 24h' => ['07:00', '07:01', null, 1, '2027-01-31'],
            'day shift' => ['07:00', '19:00', null, 720, '2027-01-31'],
            'overnight month boundary' => ['19:00', '07:00', null, 720, '2027-02-01'],
            'midnight end' => ['19:00', '00:00', null, 300, '2027-02-01'],
            'midnight start' => ['00:00', '07:00', null, 420, '2027-01-31'],
            'minimum overnight' => ['23:59', '00:00', null, 1, '2027-02-01'],
            'explicit 24h' => ['07:00', '07:00', '2027-02-01', 1440, '2027-02-01'],
            'midnight 24h' => ['00:00', '00:00', '2027-02-01', 1440, '2027-02-01'],
            'legacy equal times preserved' => ['07:00', '07:00', null, 1440, '2027-02-01'],
            'legacy SQL time seconds' => ['19:00:00', '07:00:00', null, 720, '2027-02-01'],
        ];
    }

    #[DataProvider('periods')]
    public function test_dates_and_duration_have_one_semantics(string $start, string $end, ?string $endDate, int $minutes, string $effectiveEnd): void
    {
        $shift = new Shift(['date' => '2027-01-31', 'start_time' => $start, 'end_time' => $end, 'end_date' => $endDate]);
        $attributes = $shift->getAttributes();
        $period = $shift->period();
        $this->assertSame($minutes, $period->durationMinutes);
        $this->assertSame($effectiveEnd, $period->end->toDateString());
        $this->assertSame($attributes, $shift->getAttributes());
        $this->assertStringContainsString($period->durationLabel(), $shift->time_label);
        if ($effectiveEnd !== '2027-01-31') {
            $this->assertStringContainsString('dia seguinte, 01/02/2027', $shift->time_label);
        }
    }

    public function test_24h_can_be_created_and_edited_with_an_explicit_end_date(): void
    {
        [$manager, $user, $sector, $category] = $this->team();
        $component = Livewire::actingAs($manager)->test(ShiftManager::class)
            ->set('date', '2027-01-31')->set('startTime', '07:00')->set('endTime', '07:00')
            ->set('endDate', '2027-02-01')->set('sectorId', (string) $sector->id)
            ->set('categoryId', (string) $category->id)->set('userId', (string) $user->id)
            ->call('save')->assertHasNoErrors();
        $shift = Shift::query()->sole();
        $this->assertSame(1440, $shift->period()->durationMinutes);
        $component->call('edit', $shift->id)->assertSet('endDate', '2027-02-01')->call('save')->assertHasNoErrors();
        $this->assertSame(1440, $shift->refresh()->period()->durationMinutes);
    }

    public function test_validation_requires_explicit_24h_and_preserves_the_one_minute_shift(): void
    {
        [$manager, , $sector, $category] = $this->team();
        $base = ['date' => '2027-01-31', 'start_time' => '07:00', 'end_time' => '07:01', 'sector_id' => $sector->id, 'category_id' => $category->id, 'user_id' => null, 'status' => 'scheduled'];
        $validated = (new SaveShiftRequest)->validatePayload($base, $manager);
        $this->assertSame('2027-01-31', $validated['end_date']);
        $this->assertSame(1, (new Shift($validated))->period()->durationMinutes);
        foreach ([
            ['end_time' => '07:00'],
            ['end_time' => '07:00', 'end_date' => '2027-01-31'],
            ['end_date' => '2027-02-01'], // 24h + 1 minute, not 24h
            ['end_date' => '2027-01-30'],
            ['end_date' => '2027-02-02'],
            ['end_date' => 'not-a-date'],
        ] as $invalid) {
            try {
                (new SaveShiftRequest)->validatePayload(array_replace($base, $invalid), $manager);
                $this->fail('Invalid end must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_additive_migration_leaves_old_values_untouched(): void
    {
        // DDL deve ficar fora da conexão da suíte: MySQL faz commit implícito.
        $default = DB::getDefaultConnection();
        config(['database.connections.timing_migration' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::setDefaultConnection('timing_migration');
        try {
            $original = require database_path('migrations/2026_09_25_000003_create_shifts_table.php');
            $original->up();
            $id = DB::table('shifts')->insertGetId(['date' => '2027-01-31', 'start_time' => '07:00', 'end_time' => '07:01', 'sector_id' => 1, 'category_id' => 1, 'user_id' => 1, 'status' => 'scheduled']);
            $before = (array) DB::table('shifts')->find($id);
            $migration = require database_path('migrations/2026_10_10_000015_add_end_date_to_shifts.php');
            $migration->up();
            $after = (array) DB::table('shifts')->find($id);
            $this->assertNull($after['end_date']);
            unset($after['end_date']);
            $this->assertSame($before, $after);
            $this->assertSame(1, Shift::findOrFail($id)->period()->durationMinutes);
        } finally {
            DB::setDefaultConnection($default);
            DB::purge('timing_migration');
        }
    }

    public function test_coverage_splits_at_midnight_and_merges_overlaps_without_double_counting(): void
    {
        [, $user, $sector, $category] = $this->team();
        $night = $this->shift($user, $sector, $category, '2027-01-31', '19:00', '07:00');
        $day = $this->shift($user, $sector, $category, '2027-02-01', '07:00', '19:00');
        $late = $this->shift($user, $sector, $category, '2027-02-01', '19:00', '00:00');
        $duplicate = $this->shift($user, $sector, $category, '2027-02-01', '08:00', '12:00');
        $this->assertSame([[1140, 1440]], ShiftCoverage::intervalsOnDate($night, '2027-01-31'));
        $this->assertSame([[0, 420]], ShiftCoverage::intervalsOnDate($night, '2027-02-01'));
        $this->assertSame([], ShiftCoverage::intervalsOnDate($late, '2027-02-02'));
        $this->assertSame(1440, ShiftCoverage::coveredMinutes(collect([$night, $day, $late, $duplicate]), '2027-02-01'));
        $full = $this->shift($user, $sector, $category, '2027-02-03', '07:00', '07:00', '2027-02-04');
        $this->assertSame(1020, ShiftCoverage::coveredMinutes(collect([$full]), '2027-02-03'));
        $this->assertSame(420, ShiftCoverage::coveredMinutes(collect([$full]), '2027-02-04'));
    }

    public function test_doubles_include_next_day_but_consecutive_shifts_do_not_overlap(): void
    {
        [, $user, $sector, $category] = $this->team();
        $night = $this->shift($user, $sector, $category, '2027-01-31', '19:00', '07:00');
        $day = $this->shift($user, $sector, $category, '2027-02-01', '07:00', '19:00');
        $this->assertFalse($night->period()->overlaps($day->period()));
        $this->assertTrue($night->period()->overlaps($day->period(), includeAdjacent: true));
        $this->assertTrue(ShiftCoverage::findDoubleShift($user, $night)->is($day));
        $this->assertTrue(ShiftCoverage::findDoubleShift($user, $day)->is($night));
        $overlap = new ShiftPeriod('2027-02-01', '06:59', '08:00');
        $gap = new ShiftPeriod('2027-02-01', '07:01', '08:00');
        $this->assertTrue($night->period()->overlaps($overlap));
        $this->assertFalse($night->period()->overlaps($gap, includeAdjacent: true));
    }

    public function test_copy_detects_conflicts_from_neighboring_months_and_preserves_24h_end_date(): void
    {
        [$manager, $user, $sector, $category] = $this->team();
        $source = $this->shift($user, $sector, $category, '2027-01-31', '19:00', '07:00');
        $this->shift($user, $sector, $category, '2027-04-01', '06:00', '08:00');
        $preview = app(DuplicateMonthAction::class)->preview($manager, '2027-01', '2027-03');
        $this->assertSame('blocked', $preview['items'][0]['status']);
        $previous = $this->shift($user, $sector, $category, '2027-01-01', '06:00', '08:00');
        $this->shift($user, $sector, $category, '2027-02-28', '19:00', '07:00');
        $preview = app(DuplicateMonthAction::class)->preview($manager, '2027-01', '2027-03');
        $this->assertSame('blocked', collect($preview['items'])->firstWhere('source_id', $previous->id)['status']);
        $full = $this->shift($user, $sector, $category, '2027-01-12', '07:00', '07:00', '2027-01-13');
        app(DuplicateMonthAction::class)->execute($manager, '2027-01', '2027-03', 'day', [$full->id]);
        $copied = Shift::whereDate('date', '2027-03-12')->sole();
        $this->assertSame('2027-03-13', $copied->end_date->toDateString());
        $this->assertSame(1440, $copied->period()->durationMinutes);
    }

    public function test_calendar_dashboard_and_wall_roster_show_end_date_and_duration(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-01-31 06:00:00'));
        [$manager, $user, $sector, $category] = $this->team();
        $shift = $this->shift($user, $sector, $category, '2027-01-31', '07:00', '07:00', '2027-02-01');
        Livewire::actingAs($manager)->test(MonthlyCalendar::class)
            ->set('month', '2027-01')->call('selectDate', '2027-01-31')->assertSee($shift->time_label);
        $shift->update(['status' => ShiftStatus::Uncovered, 'user_id' => null]);
        $this->actingAs($manager)->get(route('manager.dashboard'))->assertOk()->assertSee($shift->time_label);
        $roster = app(ShiftReportService::class)->wallRoster('2027-01');
        $this->assertStringContainsString($shift->time_label, view('reports.wall-roster', ['roster' => $roster, 'generatedAt' => '10/10/2026'])->render());
    }

    public function test_receipts_include_period_fields_and_legacy_snapshots_are_read_without_mutation(): void
    {
        [$manager, $user, $sector, $category] = $this->team();
        $shift = $this->shift($user, $sector, $category, '2027-01-31', '07:00', '07:00', '2027-02-01');
        $substitute = User::factory()->create(['sector_id' => $sector->id, 'category_id' => $category->id]);
        $swap = ShiftSwap::factory()->create(['shift_id' => $shift->id, 'requester_id' => $user->id, 'substitute_id' => $substitute->id, 'manager_id' => $manager->id]);
        $receipt = app(ShiftSwapReceipt::class)->make($swap, ShiftSwapEvent::Approved);
        $this->assertSame('2027-02-01', $receipt['end_date']);
        $this->assertSame(1440, $receipt['duration_minutes']);
        $this->assertStringContainsString('dia seguinte, 01/02/2027', view('reports.swap-receipt', ['receipt' => $receipt, 'generatedAt' => '10/10/2026'])->render());
        unset($receipt['end_date'], $receipt['duration_minutes'], $receipt['end_time_label'], $receipt['time_label']);
        $snapshot = $receipt;
        $rendered = ShiftSwapReceipt::withTimeLabels($receipt);
        $this->assertSame($snapshot, $receipt);
        $this->assertSame(1440, $rendered['duration_minutes']);
    }

    public function test_xlsx_round_trip_preserves_24h_and_one_minute_and_accepts_legacy_headers(): void
    {
        [$manager, $user, $sector, $category] = $this->team();
        $full = $this->shift($user, $sector, $category, '2027-01-31', '07:00', '07:00', '2027-02-01');
        $short = $this->shift($user, $sector, $category, '2027-01-30', '07:00', '07:01');
        $path = app(ShiftSpreadsheetService::class)->monthExportPath('2027-01');
        try {
            $reader = new Reader;
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                $rows = [];
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
                break;
            }
            $reader->close();
            $this->assertSame('01/02/2027', $rows[2][6]);
            $this->assertSame(1440, $rows[2][7]);
            $this->assertSame(1, $rows[1][7]);
            $full->delete();
            $short->delete();
            $this->assertSame(2, app(ImportShiftsAction::class)->execute($manager, $path)['created']);
            $this->assertSame(1440, Shift::whereDate('date', '2027-01-31')->sole()->period()->durationMinutes);
            $this->assertSame(1, Shift::whereDate('date', '2027-01-30')->sole()->period()->durationMinutes);
        } finally {
            @unlink($path);
        }
        $this->importRows($manager, [array_slice(ShiftSpreadsheetService::HEADERS, 0, 6), ['02/02/2027', '19:00', '07:00', $sector->name, $category->name, $user->email]]);
        $this->assertSame(720, Shift::whereDate('date', '2027-02-02')->sole()->period()->durationMinutes);
    }

    public function test_xlsx_duration_cannot_reinterpret_a_one_minute_row_as_24h(): void
    {
        [$manager, $user, $sector, $category] = $this->team();
        try {
            $this->importRows($manager, [ShiftSpreadsheetService::HEADERS, ['31/01/2027', '07:00', '07:01', $sector->name, $category->name, $user->email, '31/01/2027', 1440]]);
            $this->fail('Inconsistent duration must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('1 minutos', $exception->errors()['arquivo'][0]);
        }
        $this->assertDatabaseCount('shifts', 0);
    }

    public function test_xlsx_recognizes_explicit_and_legacy_sql_times_as_the_same_interval(): void
    {
        [$manager, $user, $sector, $category] = $this->team();
        $this->shift($user, $sector, $category, '2027-01-31', '19:00:00', '07:00:00');
        $this->importRows($manager, [ShiftSpreadsheetService::HEADERS,
            ['31/01/2027', '19:00', '07:00', $sector->name, $category->name, $user->email, '01/02/2027', 720],
            ['02/02/2027', '07:00', '19:00', $sector->name, $category->name, $user->email, '', ''],
        ]);
        $this->assertDatabaseCount('shifts', 2);
        $this->assertSame(1, Shift::whereDate('date', '2027-01-31')->count());
    }

    public function test_copy_checks_overnight_conflicts_inside_the_batch_and_allows_consecutive_shifts(): void
    {
        [$manager, $user, $sector, $category] = $this->team();
        $night = $this->shift($user, $sector, $category, '2027-01-10', '19:00', '07:00');
        $conflict = $this->shift($user, $sector, $category, '2027-01-11', '06:00', '06:59');
        $consecutive = $this->shift($user, $sector, $category, '2027-01-11', '07:00', '19:00');
        $items = collect(app(DuplicateMonthAction::class)->preview($manager, '2027-01', '2027-03')['items'])->keyBy('source_id');
        $this->assertSame('valid', $items[$night->id]['status']);
        $this->assertSame('blocked', $items[$conflict->id]['status']);
        $this->assertSame('valid', $items[$consecutive->id]['status']);
    }

    private function team(): array
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $sector = Sector::factory()->create();
        $category = Category::factory()->create();
        $user = User::factory()->create(['sector_id' => $sector->id, 'category_id' => $category->id]);

        return [$manager, $user, $sector, $category];
    }

    private function shift(User $user, Sector $sector, Category $category, string $date, string $start, string $end, ?string $endDate = null): Shift
    {
        return Shift::factory()->create(['date' => $date, 'start_time' => $start, 'end_time' => $end, 'end_date' => $endDate, 'sector_id' => $sector->id, 'category_id' => $category->id, 'user_id' => $user->id, 'status' => ShiftStatus::Scheduled]);
    }

    private function importRows(User $manager, array $rows): void
    {
        $path = sys_get_temp_dir().'/timing-import-'.uniqid().'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();
        try {
            app(ImportShiftsAction::class)->execute($manager, $path);
        } finally {
            @unlink($path);
        }
    }
}
