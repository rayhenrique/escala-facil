<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\SwapApprovals;
use App\Models\Absence;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Support\ApplicationVersion;
use App\Support\OperationalNavigation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class ManagerSwapHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11 12:00:00');
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager, 'last_seen_version' => app(ApplicationVersion::class)->current()]));
    }

    private function fixtureSwap(ShiftSwapStatus $status, array $shiftData = [], array $swapData = []): ShiftSwap
    {
        $requester = User::factory()->create(['name' => 'Solicitante Alfa']);
        $substitute = User::factory()->create(['name' => 'Substituto Beta']);
        $shift = Shift::factory()->create($shiftData + [
            'date' => '2026-10-31', 'start_time' => '19:00', 'end_time' => '07:00',
            'user_id' => $status === ShiftSwapStatus::Approved ? $substitute->id : $requester->id,
            'status' => ShiftStatus::Scheduled,
        ]);

        return ShiftSwap::factory()->create($swapData + ['shift_id' => $shift->id, 'requester_id' => $requester->id, 'substitute_id' => $substitute->id, 'status' => $status]);
    }

    public function test_history_includes_all_statuses_and_old_records_beyond_ten_using_sql_pagination(): void
    {
        $ids = [];
        foreach (range(1, 25) as $n) {
            $ids[] = $this->fixtureSwap(ShiftSwapStatus::cases()[($n - 1) % 5], ['date' => '2020-01-01'], ['created_at' => '2020-01-01 10:00:00'])->id;
        }
        $component = Livewire::test(SwapApprovals::class)->call('showHistory')
            ->assertSet('swapStatusFilter', '')->assertSet('sort', 'newest')
            ->assertViewHas('swaps', fn ($rows) => $rows->total() === 25 && $rows->count() === 10 && $rows->pluck('id')->all() === array_slice(array_reverse($ids), 0, 10))
            ->call('gotoPage', 3)
            ->assertViewHas('swaps', fn ($rows) => $rows->count() === 5 && $rows->pluck('id')->all() === array_reverse(array_slice($ids, 0, 5)))
            ->set('sort', 'oldest')->assertSet('paginators.page', 1)
            ->assertViewHas('swaps', fn ($rows) => $rows->pluck('id')->all() === array_slice($ids, 0, 10));
        foreach (ShiftSwapStatus::cases() as $status) {
            $component->set('swapStatusFilter', $status->value)
                ->assertViewHas('swaps', fn ($rows) => $rows->total() === 5 && $rows->every(fn ($swap) => $swap->status === $status));
        }
        $component->call('showPending')->assertViewHas('swaps', fn ($rows) => $rows->total() === 5 && $rows->every(fn ($swap) => $swap->status === ShiftSwapStatus::PendingManager));
    }

    public function test_combined_participant_sector_category_and_inclusive_period_filters(): void
    {
        $wanted = $this->fixtureSwap(ShiftSwapStatus::Rejected);
        $this->fixtureSwap(ShiftSwapStatus::Rejected, ['date' => '2026-11-01']);
        $wanted->requester->update(['name' => 'Ana 100%_Enfermagem']);
        $wanted->substitute->update(['name' => 'Beatriz Souza']);
        Livewire::test(SwapApprovals::class)->call('showHistory')
            ->set('swapStatusFilter', 'rejected')->set('search', 'Souza')
            ->set('requesterSearch', '%_')->set('substituteSearch', 'Beatriz')
            ->set('filterSectorId', (string) $wanted->shift->sector_id)->set('filterCategoryId', (string) $wanted->shift->category_id)
            ->set('dateFrom', '2026-10-31')->set('dateTo', '2026-10-31')
            ->assertViewHas('swaps', fn ($rows) => $rows->pluck('id')->all() === [$wanted->id])
            ->set('requesterSearch', 'Beatriz')->assertViewHas('swaps', fn ($rows) => $rows->total() === 0)
            ->call('clearFilters')->assertSet('listView', 'history')->assertSet('swapStatusFilter', '')
            ->assertSet('requesterSearch', '')->assertSet('substituteSearch', '')
            ->assertViewHas('swaps', fn ($rows) => $rows->total() === 2);
    }

    public function test_invalid_filters_fail_closed_and_can_be_cleared(): void
    {
        $this->fixtureSwap(ShiftSwapStatus::Approved);
        foreach (['listView' => 'forged', 'sort' => 'id desc', 'requesterSearch' => str_repeat('x', 101), 'substituteSearch' => str_repeat('x', 101), 'filterSectorId' => '999999', 'filterCategoryId' => '-1', 'dateFrom' => '2026-02-30', 'swapStatusFilter' => 'unknown'] as $field => $value) {
            Livewire::test(SwapApprovals::class)->call('showHistory')->set($field, $value)
                ->assertHasErrors($field)->assertViewHas('swaps', fn ($rows) => $rows->total() === 0)
                ->call('clearFilters')->assertHasNoErrors();
        }
        Livewire::test(SwapApprovals::class)->call('showHistory')->set('dateFrom', '2026-11-01')->set('dateTo', '2026-10-31')
            ->assertHasErrors('dateTo')->assertViewHas('swaps', fn ($rows) => $rows->total() === 0);
    }

    public function test_mutual_details_show_both_periods_professionals_and_current_receipt_without_writes(): void
    {
        $counterpart = Shift::factory()->create(['date' => '2026-11-01', 'end_date' => '2026-11-02', 'start_time' => '07:00', 'end_time' => '07:00']);
        $swap = $this->fixtureSwap(ShiftSwapStatus::Reverted, [], ['substitute_shift_id' => $counterpart->id]);
        $before = $swap->fresh()->getAttributes();
        $state = ['listView' => 'history', 'swapStatusFilter' => '', 'sort' => 'newest', 'requesterSearch' => 'Alfa', 'substituteSearch' => 'Beta', 'page' => '2'];
        $back = route('manager.swaps', OperationalNavigation::clean('swaps', $state));
        $this->get(route('manager.swaps', ['request' => $swap->id, 'from' => 'swaps', 'context' => $state]))
            ->assertOk()->assertSee('Troca mútua')->assertSee('dia seguinte, 01/11/2026')->assertSee('24h')
            ->assertSee('Solicitante Alfa')->assertSee('Substituto Beta')
            ->assertSee('href="'.e($back).'"', false)->assertSee('Consultar comprovante da situação atual')
            ->assertSee('Desfeita')->assertDontSeeHtml('wire:click="requestRevert(');
        $this->get(route('manager.swaps', ['request' => $swap->id]))
            ->assertOk()->assertSee('Situação: Desfeita.')
            ->assertDontSee('Pedidos aceitos pelo substituto que aguardam decisão')
            ->assertDontSeeHtml('id="list-status"');
        $this->get(route('swaps.receipt.pdf', $swap))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame($before, $swap->fresh()->getAttributes());
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_history_url_and_legacy_status_urls_restore_filters_and_order(): void
    {
        $swap = $this->fixtureSwap(ShiftSwapStatus::Approved);
        $this->get(route('manager.swaps', ['listView' => 'history']))->assertOk()->assertSee('data-swap-record="'.$swap->id.'"', false);
        $this->get(route('manager.swaps', ['swapStatusFilter' => 'approved', 'requesterSearch' => 'Alfa', 'sort' => 'oldest']))
            ->assertOk()->assertSee('data-swap-record="'.$swap->id.'"', false);
        $this->assertSame(['listView' => 'history', 'sort' => 'oldest', 'requesterSearch' => 'Alfa', 'substituteSearch' => 'Beta'],
            OperationalNavigation::clean('swaps', ['listView' => 'history', 'sort' => 'oldest', 'requesterSearch' => 'Alfa', 'substituteSearch' => 'Beta']));
        $this->assertSame([], OperationalNavigation::clean('swaps', ['listView' => 'bad', 'sort' => 'bad']));
        foreach (['listView' => ['history'], 'sort' => ['newest'], 'requesterSearch' => ['Alfa'], 'substituteSearch' => ['Beta']] as $field => $value) {
            $this->get(route('manager.swaps', [$field => $value]))->assertNotFound();
        }
    }

    public function test_manager_history_and_unrelated_receipts_remain_forbidden_to_professionals(): void
    {
        $swap = $this->fixtureSwap(ShiftSwapStatus::Approved);
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('manager.swaps', ['listView' => 'history']))->assertForbidden();
        Livewire::actingAs($outsider)->test(SwapApprovals::class, ['request' => $swap->id])->assertForbidden();
        $this->get(route('swaps.receipt.pdf', $swap))->assertForbidden();
        $this->actingAs($swap->requester)->get(route('swaps.receipt.pdf', $swap))->assertOk();
    }

    public function test_revert_visibility_and_reasons_match_existing_policy_conditions(): void
    {
        $allowed = $this->fixtureSwap(ShiftSwapStatus::Approved);
        $past = $this->fixtureSwap(ShiftSwapStatus::Approved, ['date' => '2026-10-10']);
        $changed = $this->fixtureSwap(ShiftSwapStatus::Approved, ['user_id' => null]);
        $absence = $this->fixtureSwap(ShiftSwapStatus::Approved);
        Absence::factory()->create(['shift_id' => $absence->shift_id]);
        $pending = $this->fixtureSwap(ShiftSwapStatus::Approved);
        ShiftSwap::factory()->create(['shift_id' => $pending->shift_id, 'status' => ShiftSwapStatus::PendingManager]);
        $unscheduled = $this->fixtureSwap(ShiftSwapStatus::Approved, ['status' => ShiftStatus::AbsenceReported]);
        $screen = Livewire::test(SwapApprovals::class)->call('showHistory')
            ->assertSeeHtml('wire:click="requestRevert('.$allowed->id.')"')
            ->assertSee('A data do plantão já passou')->assertSee('A atribuição do plantão foi alterada')
            ->assertSee('Existe um registro de ausência')->assertSee('Existe outro pedido pendente')
            ->assertSee('O plantão não está agendado');
        foreach ([$past, $changed, $absence, $pending, $unscheduled] as $blocked) {
            $screen->assertDontSeeHtml('wire:click="requestRevert('.$blocked->id.')"');
            $this->assertTrue(Gate::inspect('revert', $blocked)->denied());
            $screen->call('requestRevert', $blocked->id)->assertForbidden();
            $screen = Livewire::test(SwapApprovals::class)->call('showHistory');
        }
        $this->assertTrue(Gate::inspect('revert', $allowed)->allowed());
    }

    public function test_page_reads_eager_load_relations_and_revert_exists_without_per_record_queries(): void
    {
        foreach (range(1, 15) as $n) {
            $this->fixtureSwap(ShiftSwapStatus::Approved);
        }
        Model::preventLazyLoading();
        DB::enableQueryLog();
        try {
            Livewire::withQueryParams(['listView' => 'history'])->test(SwapApprovals::class)
                ->assertViewHas('swaps', fn ($rows) => $rows->total() === 15 && $rows->count() === 10);
            $queries = collect(DB::getQueryLog())->pluck('query');
            $this->assertTrue($queries->contains(fn ($sql) => str_contains($sql, 'from "shift_swaps"') && str_contains($sql, 'limit 10')));
            $exists = $queries->filter(fn ($sql) => str_contains($sql, 'pending_swaps_exists'));
            $this->assertCount(1, $exists);
            $this->assertSame(0, $queries->filter(fn ($sql) => str_starts_with($sql, 'select exists') && (str_contains($sql, '"absences"') || str_contains($sql, '"shift_swaps"')))->count());
        } finally {
            DB::disableQueryLog();
            Model::preventLazyLoading(false);
        }
    }

    public function test_new_blocker_after_preview_is_revalidated_without_reverting_or_notifying(): void
    {
        $swap = $this->fixtureSwap(ShiftSwapStatus::Approved);
        $screen = Livewire::test(SwapApprovals::class)->call('showHistory')->call('requestRevert', $swap->id);
        Absence::factory()->create(['shift_id' => $swap->shift_id]);
        $screen->call('confirmRevert')->assertForbidden();
        $this->assertSame(ShiftSwapStatus::Approved, $swap->fresh()->status);
        $this->assertSame($swap->substitute_id, $swap->shift->fresh()->user_id);
        $this->assertSame(0, DB::table('notifications')->count());
    }
}
