<?php

namespace Tests\Feature;

use App\Actions\ShiftSwaps\CreateShiftSwapAction;
use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Actions\ShiftSwaps\RespondToShiftSwapAction;
use App\Actions\ShiftSwaps\RevertShiftSwapAction;
use App\Enums\ShiftSwapStatus;
use App\Livewire\Professional\MySchedule;
use App\Models\Category;
use App\Models\OperationalAuditEvent;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Support\ShiftSwapReversion;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class MonthlySwapQuotaTest extends TestCase
{
    use RefreshDatabase;

    private function team(): array
    {
        config(['app.timezone' => 'America/Maceio']);
        $profile = ['sector_id' => Sector::factory()->create()->id, 'category_id' => Category::factory()->create()->id];

        return [
            User::factory()->create(['role' => 'manager']),
            User::factory()->create($profile + ['name' => 'Profissional solicitante']),
            User::factory()->create($profile + ['name' => 'Profissional substituta']),
            User::factory()->create($profile + ['name' => 'Profissional colega']),
        ];
    }

    private function shift(User $user, string $date = '2026-11-20'): Shift
    {
        return Shift::factory()->create([
            'user_id' => $user->id, 'sector_id' => $user->sector_id, 'category_id' => $user->category_id,
            'date' => $date, 'start_time' => '07:00', 'end_time' => '19:00',
        ]);
    }

    private function pending(User $requester, User $substitute, string $date = '2026-11-20'): ShiftSwap
    {
        return app(CreateShiftSwapAction::class)->execute($requester, [
            'shift_id' => $this->shift($requester, $date)->id,
            'substitute_id' => $substitute->id,
        ]);
    }

    private function approvedFixtures(User $requester, User $substitute, int $count): void
    {
        for ($index = 0; $index < $count; $index++) {
            [$first, $second] = $index % 2 === 0 ? [$requester, $substitute] : [$substitute, $requester];
            ShiftSwap::factory()->approved()->create([
                'requester_id' => $first->id, 'substitute_id' => $second->id,
                'created_at' => '2026-09-15', 'approved_at' => now(config('app.timezone')),
            ]);
        }
    }

    public function test_rollover_uses_maceio_midnight_for_requests_eligibility_acceptance_and_approval(): void
    {
        [$manager, $requester, $substitute, $colleague] = $this->team();
        $this->travelTo(CarbonImmutable::parse('2026-10-31T20:00:00Z'));
        $pending = $this->pending($requester, $substitute);
        $this->travelTo(CarbonImmutable::parse('2026-11-01T02:59:59Z')); // 31/10 at 23:59:59 in Maceió.
        $this->approvedFixtures($requester, $substitute, 4);

        $this->assertSame(4, $requester->approvedSwapCount());
        $this->assertFalse($substitute->can('accept', $pending));
        $this->assertThrows(fn () => $this->pending($requester, $colleague, '2026-11-23'), ValidationException::class);
        $colleagueShift = $this->shift($colleague);
        $guided = Livewire::actingAs($colleague)->test(MySchedule::class)
            ->call('openSwap', $colleagueShift->id)->call('nextSwapStep')->call('nextSwapStep');
        $this->assertNotContains($substitute->id, $guided->instance()->eligibleSubstitutes->pluck('id')->all());

        $this->travelTo(CarbonImmutable::parse('2026-11-01T03:00:00Z')); // 01/11 at 00:00:00 in Maceió.
        $this->assertSame(0, $requester->approvedSwapCount());
        $this->assertSame(0, $substitute->approvedSwapCount());
        $guided->call('$refresh');
        $this->assertContains($substitute->id, $guided->instance()->eligibleSubstitutes->pluck('id')->all());
        Livewire::actingAs($requester)->test(MySchedule::class)
            ->assertSee('Você ainda possui 4 de 4 trocas disponíveis neste mês.');
        $this->assertSame(ShiftSwapStatus::PendingProfessional, $this->pending($requester, $substitute)->status);
        $accepted = app(RespondToShiftSwapAction::class)->execute($substitute, $pending->id, 'accept');
        $this->assertNull($accepted->approved_at);
        $this->assertSame(0, $requester->approvedSwapCount());

        $approved = app(DecideShiftSwapAction::class)->approve($manager, $pending->id);
        $this->assertSame('2026-10', $approved->created_at->format('Y-m'));
        $this->assertSame('2026-11-01 00:00:00', $approved->approved_at->toDateTimeString());
        $this->assertSame(1, $requester->approvedSwapCount());
        $this->assertSame(1, $substitute->approvedSwapCount());
        $this->assertSame(4, $requester->approvedSwapCount(10, 2026));
    }

    public function test_fourth_approval_blocks_fifth_and_reversal_releases_quota_with_timestamp_intact(): void
    {
        [$manager, $requester, $substitute, $colleague] = $this->team();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:30:00', 'America/Maceio'));
        $fourth = $this->pending($requester, $substitute);
        $fifth = $this->pending($requester, $colleague, '2026-11-21');
        $reject = $this->pending($requester, $colleague, '2026-11-22');
        $this->approvedFixtures($requester, $substitute, 3);
        foreach ([$fourth, $fifth, $reject] as $swap) {
            app(RespondToShiftSwapAction::class)->execute($swap->substitute, $swap->id, 'accept');
        }
        $decision = app(DecideShiftSwapAction::class);
        $this->assertNull($decision->reject($manager, $reject->id, 'Manter escala original.')->approved_at);
        $this->assertSame(3, $requester->approvedSwapCount());
        $fourth = $decision->approve($manager, $fourth->id);
        $this->assertSame(4, $requester->approvedSwapCount());
        $this->assertThrows(fn () => $decision->approve($manager, $fifth->id), AuthorizationException::class);
        $this->assertThrows(fn () => $this->pending($requester, $colleague, '2026-11-23'), ValidationException::class);

        $this->travel(1)->day();
        $snapshot = app(ShiftSwapReversion::class)->inspect($fourth)['fingerprint'];
        $reverted = app(RevertShiftSwapAction::class)->execute($manager, $fourth->id, 'Correção da escala.', $snapshot);
        $this->assertSame(ShiftSwapStatus::Reverted, $reverted->status);
        $this->assertEquals($fourth->approved_at, $reverted->approved_at);
        $this->assertSame(3, $requester->approvedSwapCount());
        $this->assertSame(ShiftSwapStatus::Approved, $decision->approve($manager, $fifth->id)->status);
    }

    public function test_all_quota_queries_use_the_same_local_month_for_a_utc_reference(): void
    {
        [, $requester, $substitute] = $this->team();
        $this->travelTo(CarbonImmutable::parse('2026-10-31T23:00:00-03:00'));
        $this->approvedFixtures($requester, $substitute, 2);
        $this->travelTo(CarbonImmutable::parse('2026-11-01T00:00:00-03:00'));
        $this->approvedFixtures($requester, $substitute, 1);
        $reference = CarbonImmutable::parse('2026-11-01T02:59:59Z');
        foreach ([$requester, $substitute] as $professional) {
            $this->assertSame(1, $professional->approvedSwapCount());
            $this->assertSame(2, $professional->approvedSwapCount(10, 2026));
            $this->assertSame(2, $professional->swapCountForMonth($reference));
            $row = User::query()->whereKey($professional->id)
                ->select('users.id')->selectSub(User::approvedSwapCountForUsers($reference), 'quota')->firstOrFail();
            $this->assertSame(2, (int) $row->quota);
        }
        $this->assertSame(2, ShiftSwap::query()->approvedInMonth($reference)->count());
    }

    public function test_migration_backfills_legacy_approved_rows_using_update_then_creation_time(): void
    {
        // Reproduce the pre-migration schema in an isolated fixture. Never run
        // down() over the active schema: MySQL DDL commits the test transaction.
        $default = DB::getDefaultConnection();
        config(['database.connections.quota_legacy_fixture' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        try {
            DB::setDefaultConnection('quota_legacy_fixture');
            $connection = DB::connection();
            $connection->getSchemaBuilder()->create('shift_swaps', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('requester_id');
                $table->unsignedBigInteger('substitute_id');
                $table->string('status');
                $table->timestamps();
            });
            $connection->table('shift_swaps')->insert([
                ['id' => 1, 'requester_id' => 1, 'substitute_id' => 2, 'status' => 'approved', 'created_at' => '2026-10-28 07:00:00', 'updated_at' => '2026-11-04 10:00:00'],
                ['id' => 2, 'requester_id' => 1, 'substitute_id' => 2, 'status' => 'approved', 'created_at' => '2026-10-28 07:00:00', 'updated_at' => null],
                ['id' => 3, 'requester_id' => 1, 'substitute_id' => 2, 'status' => 'pending_manager', 'created_at' => '2026-10-28 07:00:00', 'updated_at' => null],
                ['id' => 4, 'requester_id' => 1, 'substitute_id' => 2, 'status' => 'reverted', 'created_at' => '2026-10-28 07:00:00', 'updated_at' => '2026-11-04 10:00:00'],
            ]);
            $before = $connection->table('shift_swaps')->orderBy('id')->get(['id', 'status', 'created_at', 'updated_at'])->toJson();
            (require database_path('migrations/2026_11_06_000000_add_approved_at_to_shift_swaps_table.php'))->up();
            $this->assertSame('2026-11-04 10:00:00', $connection->table('shift_swaps')->where('id', 1)->value('approved_at'));
            $this->assertSame('2026-10-28 07:00:00', $connection->table('shift_swaps')->where('id', 2)->value('approved_at'));
            $this->assertNull($connection->table('shift_swaps')->where('id', 3)->value('approved_at'));
            $this->assertNull($connection->table('shift_swaps')->where('id', 4)->value('approved_at'));
            $this->assertSame($before, $connection->table('shift_swaps')->orderBy('id')->get(['id', 'status', 'created_at', 'updated_at'])->toJson());
        } finally {
            DB::setDefaultConnection($default);
            DB::purge('quota_legacy_fixture');
        }
    }

    public function test_old_approvals_keep_their_month_and_audit_after_later_edits_and_reversion(): void
    {
        [$manager, $requester, $substitute] = $this->team();
        $this->travelTo(CarbonImmutable::parse('2026-10-31 23:59:59', 'America/Maceio'));
        $pending = $this->pending($requester, $substitute);
        app(RespondToShiftSwapAction::class)->execute($substitute, $pending->id, 'accept');
        $approved = app(DecideShiftSwapAction::class)->approve($manager, $pending->id);
        $approval = OperationalAuditEvent::where('entity_type', 'swap')->where('entity_id', $approved->id)->where('action', 'approved')->sole();
        $this->assertSame('2026-10-31T23:59:59-03:00', $approval->changes['approved_at']['after']);
        $original = $approval->getRawOriginal();

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00', 'America/Maceio'));
        $approved->update(['rejection_reason' => 'Metadado administrativo posterior.']);
        $this->assertSame(1, $requester->approvedSwapCount(10, 2026));
        $this->assertSame(1, $substitute->approvedSwapCount(10, 2026));
        $this->assertSame(0, $requester->approvedSwapCount());
        $this->assertSame('2026-10-31 23:59:59', $approved->fresh()->approved_at->toDateTimeString());
        $review = app(ShiftSwapReversion::class)->inspect($approved->fresh());
        $reverted = app(RevertShiftSwapAction::class)->execute($manager, $approved->id, 'Ajuste administrativo.', $review['fingerprint']);
        $this->assertSame(0, $requester->approvedSwapCount(10, 2026));
        $this->assertSame(0, $substitute->approvedSwapCount(10, 2026));
        $this->assertSame('2026-10-31 23:59:59', $reverted->approved_at->toDateTimeString());
        $this->assertSame($original, $approval->fresh()->getRawOriginal());
    }
}
