<?php

namespace Tests\Feature;

use App\Livewire\Manager\MonthlyShiftBuilder;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MonthlyShiftBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 11, 1)->startOfDay());
    }

    private function professional(array $attributes = []): User
    {
        return User::factory()->for(Sector::factory())->for(Category::factory())->create($attributes);
    }

    private function shift(User $professional, array $attributes = []): Shift
    {
        return Shift::factory()->create($attributes + [
            'user_id' => $professional->id, 'sector_id' => $professional->sector_id,
            'category_id' => $professional->category_id, 'date' => '2026-11-10',
            'start_time' => '08:00', 'end_time' => '12:00',
        ]);
    }

    public function test_route_permissions_and_calendar_scope(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $professional = $this->professional(['name' => 'Ana Pereira']);
        $other = $this->professional(['name' => 'Beatriz Souza']);
        $this->shift($professional);
        $this->shift($professional, ['date' => '2026-12-10', 'start_time' => '06:00']);
        $foreign = $this->shift($other, ['start_time' => '09:00']);

        $this->get(route('manager.shifts.builder'))->assertRedirect(route('login'));
        $this->actingAs($professional)->get(route('manager.shifts.builder'))->assertForbidden();
        $this->actingAs($manager)->get(route('manager.shifts'))->assertSee('Montar escala mensal');
        $this->get(route('manager.shifts.builder'))->assertOk();

        $screen = Livewire::test(MonthlyShiftBuilder::class)
            ->set('professionalSearch', 'Ana')->assertSee('Ana Pereira')->assertDontSee('Beatriz Souza')
            ->set('professionalId', (string) $professional->id)
            ->assertSee($professional->sector->name)->assertSee($professional->category->name)
            ->assertSee('08:00')->assertDontSee('06:00')->assertDontSee('09:00')
            ->call('nextMonth')->assertSet('month', '2026-12')->assertSee('06:00')->assertDontSee('08:00')
            ->call('previousMonth')->assertSet('month', '2026-11')
            ->set('month', '2026-10')->call('currentMonth')->assertSet('month', '2026-11');
        $screen->call('edit', $foreign->id)->assertNotFound();
    }

    public function test_create_and_edit_with_two_shifts_on_the_same_day_and_audits(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $professional = $this->professional();
        $other = $this->professional();
        $existing = $this->shift($professional);
        $foreign = $this->shift($other);
        $screen = Livewire::actingAs($manager)->test(MonthlyShiftBuilder::class)
            ->set('professionalId', (string) $professional->id)
            ->call('openCreateModal', '2026-11-10')->set('startTime', '14:00')
            ->call('save')->assertHasNoErrors()->assertSet('showFormModal', false)->assertSee('2 plantões');
        $created = $professional->shifts()->whereKeyNot($existing->id)->sole();
        $this->assertSame('2026-11-10', $created->date->toDateString());
        $this->assertSame($professional->sector_id, $created->sector_id);
        $this->assertSame($professional->category_id, $created->category_id);

        $screen->call('edit', $created->id)->assertSet('startTime', '14:00')
            ->set('date', '2026-11-11')->set('startTime', '19:00')->set('endTime', '19:00')
            ->set('endDate', '2026-11-12')->call('save')
            ->assertHasNoErrors()->assertSet('showFormModal', false)->assertSee('2 plantões no mês');
        $this->assertSame('2026-11-11', $created->refresh()->date->toDateString());
        $this->assertSame(1440, $created->period()->durationMinutes);
        $this->assertSame('08:00', $foreign->fresh()->period()->start->format('H:i'));
        $this->assertSame('08:00', $existing->fresh()->period()->start->format('H:i'));
        $this->assertDatabaseCount('shifts', 3);
        foreach (['created', 'updated'] as $action) {
            $this->assertDatabaseHas('operational_audit_events', [
                'entity_type' => 'shift', 'entity_id' => $created->id, 'action' => $action, 'author_id' => $manager->id,
            ]);
        }
    }

    public function test_calendar_warns_on_overlap_and_double_and_blocks_incompatible_or_incomplete_accounts(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $professional = $this->professional();
        $existing = $this->shift($professional);
        $double = $this->shift($professional, ['start_time' => '19:00', 'end_time' => '22:00']);
        $wrongSector = $this->shift($professional, ['sector_id' => Sector::factory()->create()->id]);
        $incomplete = User::factory()->create(['sector_id' => null, 'category_id' => null]);
        $screen = Livewire::actingAs($manager)->test(MonthlyShiftBuilder::class)
            ->set('professionalId', (string) $incomplete->id)->assertSee('Vincule setor e categoria')
            ->call('openCreateModal', '2026-11-10')->assertHasErrors('professionalId')
            ->set('professionalId', (string) $professional->id)
            ->call('edit', $wrongSector->id)->call('save')->assertHasErrors('user_id')
            ->call('openCreateModal', '2026-11-10')->call('save')
            ->assertSee('Conflito com o plantão #'.$existing->id)->assertSee('Dobra com o plantão #'.$double->id)
            ->assertSet('showFormModal', true);
        $this->assertDatabaseCount('shifts', 3);
        $screen->call('save')->assertHasNoErrors()->assertSet('showFormModal', false);
        $this->assertDatabaseCount('shifts', 4);
    }
}
