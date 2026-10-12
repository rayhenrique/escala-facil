<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Livewire\Professional\MySchedule;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class GuidedSwapRequestTest extends TestCase
{
    use RefreshDatabase;

    private function team(): array
    {
        $user = User::factory()->create(['sector_id' => Sector::factory(), 'category_id' => Category::factory()]);
        $sub = User::factory()->create(['sector_id' => $user->sector_id, 'category_id' => $user->category_id]);

        return [$user, $sub, $this->shift($user), $this->shift($sub, ['date' => today()->addDays(5)])];
    }

    private function shift(User $user, array $attributes = []): Shift
    {
        return Shift::factory()->create($attributes + ['user_id' => $user->id, 'sector_id' => $user->sector_id,
            'category_id' => $user->category_id, 'date' => today()->addDays(3), 'start_time' => '07:00', 'end_time' => '19:00']);
    }

    private function choose(User $user, User $sub, Shift $shift)
    {
        return Livewire::actingAs($user)->test(MySchedule::class)->call('openSwap', $shift->id)
            ->assertSet('swapStep', 1)->call('nextSwapStep')->assertSet('swapStep', 2)
            ->call('nextSwapStep')->assertSet('swapStep', 3)->set('substituteId', (string) $sub->id);
    }

    public function test_simple_flow_preserves_inputs_and_creates_only_after_server_confirmation(): void
    {
        [$user, $sub, $shift] = $this->team();
        $flow = $this->choose($user, $sub, $shift)->call('nextSwapStep')->assertSet('swapStep', 4)
            ->assertSee($sub->name)->call('previousSwapStep')->assertSet('substituteId', (string) $sub->id)
            ->call('nextSwapStep')->call('nextSwapStep')->assertSet('swapStep', 5);
        $this->assertDatabaseCount('shift_swaps', 0);
        $flow->call('confirmSwapRequest')->assertHasNoErrors()->assertSet('swapShiftId', null)->assertSee('Solicitação enviada');
        $swap = ShiftSwap::query()->sole();
        $this->assertSame(ShiftSwapStatus::PendingProfessional, $swap->status);
        $this->assertNull($swap->substitute_shift_id);
        $this->assertSame($user->id, $shift->fresh()->user_id);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_mutual_flow_requires_counterpart_and_reviews_both_night_and_24_hour_shifts(): void
    {
        [$user, $sub, $shift, $counterpart] = $this->team();
        $shift->update(['start_time' => '19:00', 'end_time' => '07:00']);
        $counterpart->update(['end_time' => '07:00', 'end_date' => $counterpart->date->addDay()]);
        $flow = Livewire::actingAs($user)->test(MySchedule::class)->call('openSwap', $shift->id)->call('nextSwapStep')
            ->set('swapMode', 'mutual')->call('nextSwapStep')->set('substituteId', (string) $sub->id)
            ->call('nextSwapStep')->assertHasErrors(['substitute_shift_id'])->assertSet('swapStep', 3)
            ->set('substituteShiftId', (string) $counterpart->id)->call('nextSwapStep')->assertSet('swapStep', 4)
            ->assertSee('Troca mútua')->assertSee('dia seguinte')->assertSee('24h')->assertSee($user->name)->assertSee($sub->name)
            ->call('previousSwapStep')->assertSet('substituteShiftId', (string) $counterpart->id)
            ->call('nextSwapStep')->call('nextSwapStep')->call('confirmSwapRequest')->assertHasNoErrors();
        $this->assertSame($counterpart->id, ShiftSwap::query()->sole()->substitute_shift_id);
    }

    public function test_unavailable_shift_and_other_users_shift_cannot_advance(): void
    {
        [$user, $sub, $shift, $other] = $this->team();
        $flow = Livewire::actingAs($user)->test(MySchedule::class)->call('openSwap', $shift->id);
        $shift->update(['status' => ShiftStatus::Uncovered]);
        $flow->call('nextSwapStep')->assertHasErrors(['shift_id'])->assertSet('swapStep', 1)
            ->set('chosenShiftId', (string) $other->id)->call('nextSwapStep')->assertHasErrors(['shift_id']);
        Livewire::actingAs($user)->test(MySchedule::class)->call('openSwap', $other->id)->assertNotFound();
        $this->assertDatabaseCount('shift_swaps', 0);
    }

    public function test_search_and_pagination_are_scoped_and_no_writes_occur_during_review(): void
    {
        [$user, $sub, $shift] = $this->team();
        User::factory()->count(12)->create(['sector_id' => $user->sector_id, 'category_id' => $user->category_id]);
        $outsider = User::factory()->create(['name' => 'Fora do setor']);
        $flow = $this->choose($user, $sub, $shift);
        $this->assertSame(13, $flow->instance()->eligibleSubstitutes->total());
        $this->assertCount(10, $flow->instance()->eligibleSubstitutes->items());
        $flow->call('setPage', 2, 'professionalPage');
        $this->assertCount(3, $flow->instance()->eligibleSubstitutes->items());
        $flow->set('professionalSearch', $sub->name)->assertSet('paginators.professionalPage', 1)->assertDontSee($outsider->name);
        DB::enableQueryLog();
        $flow->call('nextSwapStep')->call('nextSwapStep');
        $this->assertCount(0, collect(DB::getQueryLog())->filter(fn ($q) => preg_match('/^(insert|update|delete)/i', $q['query'])));
        DB::disableQueryLog();
    }

    public function test_quota_reached_after_review_is_revalidated_and_inputs_remain(): void
    {
        [$user, $sub, $shift] = $this->team();
        $flow = $this->choose($user, $sub, $shift)->call('nextSwapStep')->call('nextSwapStep');
        ShiftSwap::factory()->count(4)->create(['substitute_id' => $sub->id, 'status' => ShiftSwapStatus::Approved, 'created_at' => now()]);
        $flow->call('confirmSwapRequest')->assertHasErrors(['substitute_id'])->assertSee('limite mensal')
            ->assertSet('substituteId', (string) $sub->id)->assertSet('swapStep', 5);
        $this->assertDatabaseCount('shift_swaps', 4);
    }

    public function test_three_of_four_is_eligible_but_four_of_four_is_not(): void
    {
        [$user, $sub, $shift] = $this->team();
        ShiftSwap::factory()->count(3)->create(['substitute_id' => $sub->id, 'status' => ShiftSwapStatus::Approved, 'created_at' => now()]);
        $flow = $this->choose($user, $sub, $shift)->call('nextSwapStep')->assertSee('3 de 4');
        $this->assertContains($sub->id, $flow->instance()->eligibleSubstitutes->pluck('id')->all());
        ShiftSwap::factory()->create(['requester_id' => $sub->id, 'status' => ShiftSwapStatus::Approved, 'created_at' => now()]);
        $flow->call('previousSwapStep');
        $this->assertNotContains($sub->id, $flow->instance()->eligibleSubstitutes->pluck('id')->all());
    }

    public function test_changed_payload_cannot_be_submitted_without_new_review(): void
    {
        [$user, $sub, $shift] = $this->team();
        $flow = $this->choose($user, $sub, $shift)->call('nextSwapStep')->call('nextSwapStep');
        $flow->set('substituteShiftId', '99999')->call('confirmSwapRequest')->assertHasErrors(['shift_id'])->assertSee('Os dados mudaram');
        $this->assertDatabaseCount('shift_swaps', 0);
    }

    public function test_two_open_sessions_cannot_create_duplicate_requests(): void
    {
        [$user, $sub, $shift] = $this->team();
        $first = $this->choose($user, $sub, $shift)->call('nextSwapStep')->call('nextSwapStep');
        $second = $this->choose($user, $sub, $shift)->call('nextSwapStep')->call('nextSwapStep');
        $first->call('confirmSwapRequest')->assertHasNoErrors();
        $second->call('confirmSwapRequest')->assertHasErrors(['shift_id'])->assertSee('solicitação de troca ativa');
        $this->assertDatabaseCount('shift_swaps', 1);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_overlap_and_adjacent_shifts_are_explained_without_new_institutional_rules(): void
    {
        [$user, $sub, $shift, $counterpart] = $this->team();
        $this->shift($sub, ['date' => $shift->date, 'start_time' => '19:00', 'end_time' => '07:00']);
        $flow = $this->choose($user, $sub, $shift)->call('nextSwapStep')->assertSee('Dobra:');
        $this->shift($sub, ['date' => $shift->date, 'start_time' => '08:00', 'end_time' => '12:00']);
        $flow->call('previousSwapStep')->call('nextSwapStep');
        $flow->assertSee('Conflito de horários:')->assertSee('Dobra:');
    }

    public function test_counterpart_active_as_second_shift_is_excluded_and_server_rejects_forgery(): void
    {
        [$user, $sub, $shift, $counterpart] = $this->team();
        ShiftSwap::factory()->create(['substitute_shift_id' => $counterpart->id, 'status' => ShiftSwapStatus::PendingManager]);
        $flow = $this->choose($user, $sub, $shift)->set('swapMode', 'mutual')->set('substituteShiftId', (string) $counterpart->id);
        $this->assertNotContains($counterpart->id, $flow->instance()->eligibleSubstituteShifts->pluck('id')->all());
        $flow->call('nextSwapStep')->assertHasErrors(['substitute_shift_id']);
        $this->assertDatabaseCount('shift_swaps', 1);
    }

    public function test_requester_quota_and_changed_assignments_are_explained_at_confirmation(): void
    {
        [$user, $sub, $shift] = $this->team();
        $flow = $this->choose($user, $sub, $shift)->call('nextSwapStep')->call('nextSwapStep');
        $shift->update(['user_id' => $sub->id]);
        $flow->call('confirmSwapRequest')->assertHasErrors(['shift_id'])->assertSee('atribuído a você');
        $shift->update(['user_id' => $user->id]);
        ShiftSwap::factory()->count(4)->create(['requester_id' => $user->id, 'status' => ShiftSwapStatus::Approved, 'created_at' => now()]);
        $flow->call('confirmSwapRequest')->assertHasErrors(['shift_id'])->assertSee('limite mensal de quatro');
        $this->assertDatabaseCount('shift_swaps', 4);
    }

    public function test_selected_shift_beyond_first_page_is_visible_and_preserved(): void
    {
        [$user, $sub, $shift] = $this->team();
        for ($i = 0; $i < 12; $i++) {
            $this->shift($user, ['date' => today()->addDay()]);
        }
        $flow = Livewire::actingAs($user)->test(MySchedule::class)->call('openSwap', $shift->id)
            ->assertSet('paginators.chooseShiftPage', 2)->assertSet('chosenShiftId', (string) $shift->id);
        $this->assertContains($shift->id, $flow->instance()->selectableSwapShifts->pluck('id')->all());
    }
}
