<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\Dashboard;
use App\Livewire\Manager\ShiftManager;
use App\Livewire\Manager\SwapApprovals;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Support\ManagerAttention;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ManagerAttentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2027-01-31 15:30:20'));
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));
    }

    private function shift(string $date, string $time = '07:00', array $extra = []): Shift
    {
        return Shift::factory()->create(array_merge([
            'date' => $date, 'start_time' => $time, 'end_time' => '23:59',
            'end_date' => $date, 'status' => ShiftStatus::Uncovered, 'user_id' => null,
        ], $extra));
    }

    public function test_calendar_filters_cross_months_and_rolling_72_hours_use_exact_boundaries(): void
    {
        $today = $this->shift('2027-01-31', '16:00');
        $dayTwo = $this->shift('2027-02-01');
        $dayThree = $this->shift('2027-02-02');
        $before72 = $this->shift('2027-02-03', '15:30');
        $after72 = $this->shift('2027-02-03', '15:31');
        $daySeven = $this->shift('2027-02-06');
        $this->shift('2027-02-07');
        $this->shift('2027-01-31', '15:30', ['end_time' => '15:31']); // already started, not in next 72h
        $exact = $this->shift('2027-02-03', '15:30:20');

        $component = Livewire::test(Dashboard::class)->assertSet('period', '3')
            ->assertViewHas('queue', fn ($queue) => $queue->total() === 4)
            ->assertViewHas('uncovered72Count', 4)->assertViewHas('uncoveredCount', 9)
            ->assertSee('3 dias de calendário, incluindo hoje: 31/01/2027 a 02/02/2027')
            ->assertDontSee('data-attention="uncovered-'.$before72->id.'"', false);
        $component->set('period', '1')->assertViewHas('queue', fn ($queue) => $queue->total() === 2)
            ->assertSee('data-attention="uncovered-'.$today->id.'"', false)
            ->assertDontSee('data-attention="uncovered-'.$dayTwo->id.'"', false);
        $component->set('period', '7')->assertViewHas('queue', fn ($queue) => $queue->total() === 8)
            ->assertSee('data-attention="uncovered-'.$daySeven->id.'"', false)
            ->assertSee('data-attention="uncovered-'.$after72->id.'"', false)
            ->assertSee('data-attention="uncovered-'.$exact->id.'"', false);
        $component->set('period', 'invalid')->assertSet('period', '3');
        $this->get(route('manager.dashboard', ['period' => '7']))->assertOk()->assertSee('7 dias de calendário');
        $this->get(route('manager.dashboard', ['period' => 'oops']))->assertOk()->assertSee('3 dias de calendário');
    }

    public function test_ongoing_legacy_and_explicit_overnights_are_included_but_finished_shifts_are_not(): void
    {
        $legacy = $this->shift('2027-01-30', '19:00', ['end_time' => '17:00', 'end_date' => null]);
        $full = $this->shift('2027-01-30', '16:00', ['end_time' => '16:00', 'end_date' => '2027-01-31']);
        $legacyEqual = $this->shift('2027-01-30', '16:00', ['end_time' => '16:00:00', 'end_date' => null]);
        $this->shift('2027-01-30', '07:00', ['end_time' => '07:00', 'end_date' => '2027-01-31']);
        $this->shift('2027-01-31', '07:00', ['end_time' => '15:30:20']);
        $midnight = $this->shift('2027-01-31', '19:00', ['end_time' => '00:00', 'end_date' => null]);
        Livewire::test(Dashboard::class)->set('period', '1')
            ->assertViewHas('uncoveredCount', 4)->assertViewHas('uncovered72Count', 1)
            ->assertViewHas('queue', fn ($queue) => $queue->total() === 4)
            ->assertViewHas('queue', fn ($queue) => $queue->pluck('id')->all() === [$full->id, $legacyEqual->id, $legacy->id, $midnight->id])
            ->assertSee('Plantão já iniciado')->assertSee('24h');
    }

    public function test_mutual_swaps_use_earlier_counterpart_and_started_requests_remain_visible(): void
    {
        $later = $this->shift('2027-02-05', '07:00', ['status' => ShiftStatus::Scheduled]);
        $earlier = $this->shift('2027-01-31', '16:00', ['status' => ShiftStatus::Scheduled]);
        $mutual = ShiftSwap::factory()->create(['shift_id' => $later->id, 'substitute_shift_id' => $earlier->id, 'status' => ShiftSwapStatus::PendingManager]);
        $past = $this->shift('2027-01-29', '07:00', ['status' => ShiftStatus::Scheduled]);
        $overdue = ShiftSwap::factory()->create(['shift_id' => $past->id, 'status' => ShiftSwapStatus::PendingManager]);
        ShiftSwap::factory()->create(['shift_id' => $earlier->id, 'status' => ShiftSwapStatus::PendingProfessional]);
        ShiftSwap::factory()->create(['shift_id' => $earlier->id, 'status' => ShiftSwapStatus::Approved]);
        Livewire::test(Dashboard::class)->set('period', '1')
            ->assertViewHas('pendingSwapCount', 2)->assertViewHas('queue', fn ($queue) => $queue->total() === 2)
            ->assertSeeInOrder(['Pedido #'.$overdue->id, 'Pedido #'.$mutual->id])
            ->assertSee('Troca mútua')->assertSee('Outro plantão: 05/02/2027')
            ->assertDontSee('wire:click="approve', false)->assertDontSee('wire:click="requestDecision', false);
    }

    public function test_pagination_has_no_duplicates_and_resets_when_filter_changes(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->shift('2027-02-01');
        }
        $component = Livewire::test(Dashboard::class)->assertViewHas('queue', fn ($queue) => $queue->total() === 12 && $queue->count() === 8);
        $first = $component->viewData('queue')->pluck('id')->all();
        $component->call('gotoPage', 2)->assertViewHas('queue', fn ($queue) => $queue->currentPage() === 2 && $queue->count() === 4);
        $this->assertSame([], array_intersect($first, $component->viewData('queue')->pluck('id')->all()));
        $component->set('period', '1')->assertViewHas('queue', fn ($queue) => $queue->currentPage() === 1 && $queue->total() === 0);
    }

    public function test_indicator_and_row_destinations_filter_real_records(): void
    {
        $open = $this->shift('2027-02-05');
        $scheduled = $this->shift('2027-01-31', '16:00', ['status' => ShiftStatus::Scheduled]);
        $other = $this->shift('2027-01-31', '17:00');
        Livewire::test(ShiftManager::class, ['filter' => 'uncovered'])
            ->assertViewHas('shifts', fn ($shifts) => $shifts->pluck('id')->all() === [$other->id, $open->id]);
        Livewire::test(ShiftManager::class, ['filter' => 'scheduled', 'month' => '2027-01'])
            ->assertViewHas('shifts', fn ($shifts) => $shifts->pluck('id')->all() === [$scheduled->id]);
        Livewire::test(ShiftManager::class, ['focus' => $open->id, 'month' => '2027-02'])
            ->assertViewHas('shifts', fn ($shifts) => $shifts->pluck('id')->all() === [$open->id]);
        $request = ShiftSwap::factory()->create(['shift_id' => $scheduled->id, 'status' => ShiftSwapStatus::PendingManager]);
        ShiftSwap::factory()->create(['shift_id' => $other->id, 'status' => ShiftSwapStatus::PendingManager]);
        Livewire::test(SwapApprovals::class, ['request' => $request->id])
            ->assertViewHas('swaps', fn ($swaps) => $swaps->pluck('id')->all() === [$request->id]);
        $professional = User::factory()->create();
        $this->actingAs($professional)->get(route('manager.shifts', ['focus' => $open->id]))->assertForbidden();
        $this->get(route('manager.swaps', ['request' => $request->id]))->assertForbidden();
    }

    public function test_query_count_does_not_grow_with_pending_work(): void
    {
        $this->shift('2027-02-01');
        ShiftSwap::factory()->create(['shift_id' => $this->shift('2027-02-01', '07:00', ['status' => ShiftStatus::Scheduled])->id, 'status' => ShiftSwapStatus::PendingManager]);
        DB::enableQueryLog();
        app(ManagerAttention::class)->queue(now(), 3);
        $small = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 0; $i < 50; $i++) {
            ShiftSwap::factory()->create(['shift_id' => $this->shift('2027-01-31', '18:00', ['status' => ShiftStatus::Scheduled])->id, 'status' => ShiftSwapStatus::PendingManager]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $queue = app(ManagerAttention::class)->queue(now(), 3);
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(52, $queue->total());
        $this->assertLessThanOrEqual($small, $large);
        $this->assertLessThanOrEqual(14, $large);
    }
}
