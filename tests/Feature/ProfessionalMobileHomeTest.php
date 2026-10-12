<?php

namespace Tests\Feature;

use App\Enums\ShiftSwapStatus;
use App\Livewire\Professional\MySchedule;
use App\Models\Absence;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class ProfessionalMobileHomeTest extends TestCase
{
    use RefreshDatabase;

    private function professional(): User
    {
        return User::factory()->create(['sector_id' => Sector::factory(), 'category_id' => Category::factory()]);
    }

    private function shift(User $user, array $attributes = []): Shift
    {
        return Shift::factory()->create($attributes + ['user_id' => $user->id, 'sector_id' => $user->sector_id, 'category_id' => $user->category_id]);
    }

    public function test_next_shift_is_independent_of_history_filters_and_shows_real_night_duration(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(12, 0));
        $user = $this->professional();
        $past = $this->shift($user, ['date' => '2026-10-31', 'start_time' => '07:00']);
        $next = $this->shift($user, ['date' => '2026-11-01', 'start_time' => '19:00', 'end_time' => '07:00']);
        $this->shift($this->professional(), ['date' => '2026-10-31', 'start_time' => '13:00']);
        Livewire::actingAs($user)->test(MySchedule::class)->set('month', '2026-09')->set('search', 'inexistente')
            ->assertViewHas('nextShift', fn ($shift) => $shift->id === $next->id)
            ->assertSee('dia seguinte, 02/11/2026')->assertSee('12h')
            ->call('showShiftDetails', $next->id)->assertSet('detailShiftId', $next->id)
            ->call('closeShiftDetails')->assertSet('detailShiftId', null)
            ->call('openSwap', $next->id)->assertDispatched('professional-form-opened', target: 'swap-form-title');
    }

    public function test_exact_start_is_included_and_already_started_shift_is_not_next(): void
    {
        $this->travelTo(now()->setTime(7, 0, 0));
        $user = $this->professional();
        $shift = $this->shift($user, ['date' => today()->toDateString(), 'start_time' => '07:00']);
        Livewire::actingAs($user)->test(MySchedule::class)->assertViewHas('nextShift', fn ($next) => $next->id === $shift->id);
        $this->travelTo(now()->addSecond());
        Livewire::actingAs($user)->test(MySchedule::class)->assertViewHas('nextShift', null);
    }

    public function test_details_are_owned_and_cannot_be_forged_or_leak_other_sectors(): void
    {
        $user = $this->professional();
        $other = $this->shift($this->professional());
        Livewire::actingAs($user)->test(MySchedule::class)->call('showShiftDetails', $other->id)->assertNotFound();
    }

    public function test_empty_and_incomplete_profile_and_absence_are_readable_without_history_writes(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->uncovered()->create();
        Absence::factory()->create(['user_id' => $user->id, 'shift_id' => $shift->id]);
        DB::enableQueryLog();
        Livewire::actingAs($user)->test(MySchedule::class)->assertSee('Nenhum próximo plantão agendado.')
            ->assertSee('Última ausência informada')->assertViewHas('nextShift', null);
        $writes = collect(DB::getQueryLog())->filter(fn ($query) => preg_match('/^(insert|update|delete)/i', $query['query']));
        $this->assertCount(0, $writes);
        DB::disableQueryLog();
        $next = $this->shift($this->professional());
        $next->update(['user_id' => $user->id]);
        Livewire::actingAs($user)->test(MySchedule::class)->assertSee('Complete o setor e a categoria')
            ->assertViewHas('nextSwapReason', fn ($reason) => $reason !== null);
    }

    public function test_received_and_sent_swaps_are_compact_and_response_count_survives_filters(): void
    {
        $user = $this->professional();
        $requester = $this->professional();
        $requester->update(['sector_id' => $user->sector_id, 'category_id' => $user->category_id]);
        $incoming = ShiftSwap::factory()->create(['requester_id' => $requester->id, 'substitute_id' => $user->id,
            'shift_id' => $this->shift($requester)->id, 'status' => ShiftSwapStatus::PendingProfessional]);
        $sent = ShiftSwap::factory()->create(['requester_id' => $user->id, 'substitute_id' => $requester->id,
            'shift_id' => $this->shift($user)->id, 'status' => ShiftSwapStatus::PendingManager]);
        $other = ShiftSwap::factory()->create();
        Livewire::actingAs($user)->test(MySchedule::class)->assertSee(sprintf('TROCA-%06d', $incoming->id))
            ->assertSee(sprintf('TROCA-%06d', $sent->id))->assertDontSee(sprintf('TROCA-%06d', $other->id))
            ->assertSee('Aguardando você')->assertSee('Enviada')->assertSee('Recebida')
            ->set('search', 'inexistente')->assertViewHas('pendingResponses', 1)->assertSee('Há pedidos fora dos filtros atuais.')
            ->call('clearFilters')->assertSee('Aceitar');
    }

    public function test_lists_are_bounded_and_load_relations_in_batches(): void
    {
        $user = $this->professional();
        $other = $this->professional();
        for ($i = 0; $i < 12; $i++) {
            ShiftSwap::factory()->create(['requester_id' => $user->id, 'substitute_id' => $other->id,
                'shift_id' => $this->shift($user)->id, 'status' => ShiftSwapStatus::PendingManager]);
        }
        Livewire::actingAs($user)->test(MySchedule::class)
            ->assertViewHas('sentSwaps', fn ($swaps) => $swaps->count() === 3 && $swaps->every(fn ($swap) => $swap->relationLoaded('substitute') && $swap->shift->relationLoaded('sector') && $swap->shift->relationLoaded('category')))
            ->assertViewHas('mySwaps', fn ($swaps) => $swaps->total() === 12 && $swaps->count() === 8)
            ->call('setPage', 2, 'swapsPage')->assertViewHas('mySwaps', fn ($swaps) => $swaps->count() === 4);
    }

    public function test_received_pagination_has_unique_keys_across_responsive_presentations(): void
    {
        $user = $this->professional();
        $other = $this->professional();
        for ($i = 0; $i < 12; $i++) {
            ShiftSwap::factory()->create(['requester_id' => $other->id, 'substitute_id' => $user->id,
                'shift_id' => $this->shift($other, ['sector_id' => $user->sector_id])->id, 'status' => ShiftSwapStatus::PendingProfessional]);
        }
        $component = Livewire::actingAs($user)->test(MySchedule::class)
            ->assertViewHas('incomingSwaps', fn ($swaps) => $swaps->total() === 12 && $swaps->count() === 10);
        preg_match_all('/wire:key="([^"]+)"/', $component->html(), $keys);
        $this->assertCount(count(array_unique($keys[1])), $keys[1]);
        $component->call('setPage', 2, 'incomingPage')->assertViewHas('incomingSwaps', fn ($swaps) => $swaps->count() === 2);
    }

    public function test_detail_id_is_locked_against_client_updates(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::actingAs($this->professional())->test(MySchedule::class)->set('detailShiftId', 123);
    }

    public function test_today_and_mutual_counterpart_actions_explain_unavailability(): void
    {
        $this->travelTo(now()->setTime(6, 0));
        $user = $this->professional();
        $shift = $this->shift($user, ['date' => today()->toDateString(), 'start_time' => '07:00', 'end_time' => '07:00', 'end_date' => now()->addDay()->toDateString()]);
        Livewire::actingAs($user)->test(MySchedule::class)->assertSee('24h')->assertSee('após o dia de hoje');
        $shift->update(['date' => now()->addDay()->toDateString(), 'end_date' => now()->addDays(2)->toDateString()]);
        ShiftSwap::factory()->create(['shift_id' => $this->shift($this->professional())->id, 'substitute_shift_id' => $shift->id,
            'requester_id' => $this->professional()->id, 'substitute_id' => $user->id, 'status' => ShiftSwapStatus::PendingManager]);
        Livewire::actingAs($user)->test(MySchedule::class)->assertViewHas('nextSwapReason', fn ($reason) => str_contains($reason, 'troca em andamento'));
    }
}
