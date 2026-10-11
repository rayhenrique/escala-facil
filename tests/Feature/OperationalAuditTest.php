<?php

namespace Tests\Feature;

use App\Actions\Absences\ReportAbsenceAction;
use App\Actions\Shifts\ReallocateShiftAction;
use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\AuditHistory;
use App\Models\Category;
use App\Models\OperationalAuditEvent as Event;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Support\OperationalAudit;
use App\Support\OperationalAuditContext;
use App\Support\OperationalNavigation;
use App\Support\ShiftReallocation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use LogicException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OperationalAuditTest extends TestCase
{
    use RefreshDatabase;

    private function team(): array
    {
        $manager = User::factory()->create(['role' => UserRole::Manager, 'name' => 'Gerente Auditor']);
        $sector = Sector::factory()->create(['name' => 'Emergência']);
        $category = Category::factory()->create(['name' => 'Medicina']);
        $ana = User::factory()->create(['name' => 'Ana Auditada', 'sector_id' => $sector->id, 'category_id' => $category->id]);
        $bruno = User::factory()->create(['name' => 'Bruno Auditado', 'sector_id' => $sector->id, 'category_id' => $category->id]);
        $this->actingAs($manager);

        return [$manager, $ana, $bruno, $sector, $category];
    }

    private function shift(User $professional, array $values = []): Shift
    {
        return Shift::factory()->create($values + ['user_id' => $professional->id, 'sector_id' => $professional->sector_id, 'category_id' => $professional->category_id, 'date' => today()->addDay()]);
    }

    public function test_backend_creation_edit_quiet_write_and_deletion_are_audited(): void
    {
        [$manager, $ana] = $this->team();
        $shift = $this->shift($ana, ['end_date' => today()->addDays(2), 'start_time' => '07:00', 'end_time' => '07:00']);
        $events = fn () => Event::where('entity_type', 'shift')->where('entity_id', $shift->id)->orderBy('id')->get();
        $created = $events()->first();
        $this->assertSame($manager->id, $created->author_id);
        $this->assertSame('created', $created->action);
        $this->assertSame(today()->addDays(2)->format('Y-m-d'), $created->changes['end_date']['after']);
        $shift->updateQuietly(['end_time' => '06:00']);
        $updated = $events()->last();
        $this->assertSame('updated', $updated->action);
        $this->assertSame(['end_time' => ['before' => '07:00', 'after' => '06:00']], $updated->changes);
        $shift->save();
        $this->assertCount(2, $events());
        $id = $shift->id;
        $shift->deleteQuietly();
        $this->assertDatabaseMissing('shifts', ['id' => $id]);
        $this->assertSame('deleted', $events()->last()->action);
        $this->assertSame('06:00', $events()->last()->changes['end_time']['before']);
        $this->assertCount(3, $events());
    }

    public function test_stale_original_does_not_invent_changes_and_real_before_is_preserved(): void
    {
        [, $ana] = $this->team();
        $shift = $this->shift($ana);
        $stale = $shift->fresh();
        $shift->update(['start_time' => '08:00']);
        $stale->update(['end_time' => '20:00']);
        $event = Event::where('entity_type', 'shift')->latest('id')->first();
        $this->assertSame(['end_time' => ['before' => '19:00', 'after' => '20:00']], $event->changes);
        $stale->update(['start_time' => '09:00']);
        $event = Event::where('entity_type', 'shift')->latest('id')->first();
        $this->assertSame('08:00', $event->changes['start_time']['before']);
    }

    public function test_audit_failure_rolls_back_the_write_even_when_outer_caller_catches_it(): void
    {
        [, $ana] = $this->team();
        $shift = $this->shift($ana);
        $count = Event::count();
        $audit = Mockery::mock(OperationalAudit::class)->makePartial();
        $audit->shouldReceive('record')->andThrow(new RuntimeException('Audit unavailable'));
        $this->app->instance(OperationalAudit::class, $audit);
        DB::transaction(function () use ($shift): void {
            try {
                $shift->update(['start_time' => '08:00']);
                $this->fail('The write must fail with its audit.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Audit unavailable', $exception->getMessage());
            }
        });
        $this->assertSame('07:00', substr($shift->fresh()->start_time, 0, 5));
        $this->assertSame($count, Event::count());
    }

    public function test_outer_rollback_removes_successful_inner_audit_and_context_is_restored(): void
    {
        [$manager, $ana] = $this->team();
        $shift = $this->shift($ana);
        $count = Event::count();
        try {
            app(OperationalAuditContext::class)->transaction($ana, function () use ($shift): void {
                $shift->update(['start_time' => '08:00']);
                throw new RuntimeException('Abort operation');
            });
        } catch (RuntimeException) {
        }
        $this->assertSame($count, Event::count());
        $shift->fresh()->update(['end_time' => '20:00']);
        $this->assertSame($manager->id, Event::latest('id')->first()->author_id);
    }

    public function test_user_changes_exclude_password_tokens_email_and_version_metadata(): void
    {
        [$manager, $ana] = $this->team();
        $before = Event::count();
        $ana->forceFill(['password' => 'Sensitive-password-2026', 'remember_token' => 'Sensitive-token', 'last_seen_version' => '1.29.0'])->save();
        $this->assertSame($before, Event::count());
        $ana->update(['name' => 'Ana Revisada', 'email' => 'private-address@example.test']);
        $event = Event::latest('id')->first();
        $this->assertSame($manager->id, $event->author_id);
        $this->assertSame(['name', 'email_changed'], array_keys($event->changes));
        $ana->save();
        $this->assertSame($before + 1, Event::count());
        $json = Event::all()->toJson();
        foreach (['Sensitive-password', 'Sensitive-token', 'private-address', 'remember_token', 'last_seen_version', '"password"'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
    }

    public function test_swaps_record_explicit_action_actor_and_preserve_notifications_without_copying_receipt(): void
    {
        [$manager, $ana, $bruno] = $this->team();
        $shift = $this->shift($ana);
        $swap = ShiftSwap::factory()->pendingManager()->create(['shift_id' => $shift->id, 'requester_id' => $ana->id, 'substitute_id' => $bruno->id]);
        auth()->logout();
        app(DecideShiftSwapAction::class)->approve($manager, $swap->id);
        $decision = Event::where('entity_type', 'swap')->where('entity_id', $swap->id)->where('action', 'approved')->sole();
        $transfer = Event::where('entity_type', 'shift')->where('entity_id', $shift->id)->where('action', 'reallocated')->sole();
        $this->assertSame($manager->id, $decision->author_id);
        $this->assertSame($decision->operation_id, $transfer->operation_id);
        $this->assertSame($ana->id, $transfer->changes['user_id']['before']);
        $this->assertSame($bruno->id, $transfer->changes['user_id']['after']);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertArrayNotHasKey('receipt', $decision->changes);
        $this->assertArrayNotHasKey('rejection_reason', $decision->changes);
    }

    public function test_rejection_does_not_copy_free_text_reason_and_failed_decision_adds_no_event(): void
    {
        [$manager, $ana, $bruno] = $this->team();
        $shift = $this->shift($ana);
        $swap = ShiftSwap::factory()->pendingManager()->create(['shift_id' => $shift->id, 'requester_id' => $ana->id, 'substitute_id' => $bruno->id]);
        $reason = 'Texto de justificativa que não deve ser copiado na auditoria.';
        app(DecideShiftSwapAction::class)->reject($manager, $swap->id, $reason);
        $this->assertSame($reason, $swap->fresh()->rejection_reason);
        $this->assertStringNotContainsString($reason, Event::all()->toJson());
        $this->assertSame(1, Event::where('entity_type', 'swap')->where('entity_id', $swap->id)->where('action', 'rejected')->count());
        $count = Event::count();
        try {
            app(DecideShiftSwapAction::class)->approve($manager, $swap->id);
            $this->fail('Already decided');
        } catch (AuthorizationException) {
        }
        $this->assertSame($count, Event::count());
    }

    public function test_absence_bulk_rejection_is_audited_with_same_operation_and_no_sensitive_attachment(): void
    {
        [, $ana, $bruno] = $this->team();
        $shift = $this->shift($ana);
        foreach ([ShiftSwapStatus::PendingProfessional, ShiftSwapStatus::PendingManager] as $status) {
            ShiftSwap::factory()->create(['shift_id' => $shift->id, 'requester_id' => $ana->id, 'substitute_id' => $bruno->id, 'status' => $status]);
        }
        app(ReportAbsenceAction::class)->execute($ana, ['shift_id' => $shift->id, 'reason' => 'Motivo privado da ausência']);
        $events = Event::where('entity_type', 'swap')->where('action', 'rejected')->get();
        $this->assertCount(2, $events);
        $this->assertSame([$ana->id], $events->pluck('author_id')->unique()->all());
        $this->assertSame(1, $events->pluck('operation_id')->unique()->count());
        $this->assertStringNotContainsString('Motivo privado', Event::all()->toJson());
        $this->assertSame(ShiftStatus::Uncovered, $shift->fresh()->status);
    }

    public function test_reallocation_keeps_specialized_history_and_adds_only_minimal_generic_event(): void
    {
        [$manager, $ana] = $this->team();
        $shift = $this->shift($ana, ['user_id' => null, 'status' => ShiftStatus::Uncovered]);
        $preview = app(ShiftReallocation::class)->preview($shift->fresh(), $ana->fresh());
        app(ReallocateShiftAction::class)->execute($manager, $shift->id, $ana->id, $preview['fingerprint']);
        $event = Event::where('entity_type', 'shift')->where('entity_id', $shift->id)->where('action', 'reallocated')->sole();
        $this->assertSame(['user_id', 'status'], array_keys($event->changes));
        $this->assertSame($manager->id, $event->author_id);
        $this->assertDatabaseCount('shift_reallocations', 1);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_append_only_is_enforced_for_models_quiet_writes_bulk_queries_and_raw_sql(): void
    {
        $this->team();
        $event = Event::first();
        $original = $event->toArray();
        foreach ([
            fn () => $event->update(['action' => 'deleted']),
            fn () => $event->delete(),
            fn () => $event->updateQuietly(['action' => 'deleted']),
            fn () => Event::whereKey($event->id)->delete(),
            fn () => DB::table('operational_audit_events')->where('id', $event->id)->update(['author_id' => 999]),
            fn () => DB::table('operational_audit_events')->where('id', $event->id)->delete(),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('History mutation must be refused.');
            } catch (LogicException|QueryException) {
            }
            $this->assertSame($original, $event->fresh()->toArray());
        }
    }

    public function test_backend_bulk_writes_cannot_silently_bypass_model_audit(): void
    {
        [, $ana] = $this->team();
        $shift = $this->shift($ana);
        $count = Event::count();
        foreach ([fn () => Shift::whereKey($shift->id)->update(['start_time' => '09:00']), fn () => Shift::whereKey($shift->id)->delete(), fn () => Shift::whereKey($shift->id)->forceDelete(), fn () => User::whereKey($ana->id)->increment('sector_id')] as $write) {
            try {
                $write();
                $this->fail('Bulk bypass must be explicit and refused.');
            } catch (LogicException) {
            }
        }
        $this->assertSame($count, Event::count());
        $this->assertNotNull($shift->fresh());
        $this->assertSame('07:00', substr($shift->fresh()->start_time, 0, 5));
    }

    public function test_author_deletion_does_not_rewrite_history(): void
    {
        [$manager, $ana] = $this->team();
        $shift = $this->shift($ana);
        $event = Event::where('entity_type', 'shift')->where('entity_id', $shift->id)->first();
        $original = $event->toArray();
        $manager->delete();
        $this->assertSame($original, $event->fresh()->toArray());
        $this->assertNull($event->fresh()->author);
    }

    public function test_migration_rollback_cannot_erase_existing_events(): void
    {
        $this->team();
        $count = Event::count();
        $migration = require database_path('migrations/2026_10_10_220000_create_operational_audit_events_table.php');
        try {
            $migration->down();
            $this->fail('Rollback must preserve populated history.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('histórico operacional', $exception->getMessage());
        }
        $this->assertSame($count, Event::count());
    }

    public function test_cadastre_changes_record_only_actual_fields_and_contextual_return_preserves_filters(): void
    {
        [$manager, $ana, , $sector, $category] = $this->team();
        $sector->update(['name' => 'Emergência revisada']);
        $category->update(['name' => 'Medicina revisada']);
        $ana->update(['role' => UserRole::Manager]);
        foreach ([['sector', $sector, 'name', 'Emergência', 'Emergência revisada'], ['category', $category, 'name', 'Medicina', 'Medicina revisada'], ['user', $ana, 'role', 'professional', 'manager']] as [$type, $model, $field, $before, $after]) {
            $event = Event::where('entity_type', $type)->where('entity_id', $model->id)->where('action', 'updated')->sole();
            $this->assertSame([$field => ['before' => $before, 'after' => $after]], $event->changes);
            $this->assertSame($manager->id, $event->author_id);
        }
        $url = OperationalNavigation::url('audit', ['entity' => 'user', 'record' => $ana->id], 'users', ['search' => 'Ana', 'roleFilter' => 'manager', 'page' => 2]);
        $this->get($url)->assertOk()->assertSee('Voltar aos usuários')->assertSee('roleFilter=manager', false);
    }

    public function test_history_policy_denies_professionals_and_all_user_mutations(): void
    {
        [$manager, $ana] = $this->team();
        $event = Event::first();
        foreach (['create', 'update', 'delete'] as $ability) {
            $this->assertFalse(Gate::forUser($manager)->allows($ability, $ability === 'create' ? Event::class : $event));
        }
        $this->assertFalse(Gate::forUser($ana)->allows('view', $event));
        $this->actingAs($ana)->get(route('manager.audit', ['entity' => 'user', 'record' => $manager->id]))->assertForbidden();
        Livewire::actingAs($ana)->test(AuditHistory::class)->assertForbidden();
        DB::table('users')->where('id', $manager->id)->update(['role' => 'professional']);
        $this->assertFalse(Gate::forUser($manager)->allows('viewAny', Event::class), 'A stale manager instance must not keep access.');
    }

    public function test_history_filters_pagination_invalid_inputs_and_deleted_record_lookup(): void
    {
        [$manager, $ana] = $this->team();
        $shift = $this->shift($ana);
        for ($i = 0; $i < 12; $i++) {
            $shift->update(['end_time' => $i % 2 ? '19:00' : '20:00']);
        }
        Livewire::test(AuditHistory::class, ['entity' => 'shift', 'record' => (string) $shift->id])
            ->assertViewHas('events', fn ($rows) => $rows->total() === 13 && $rows->count() === 10)
            ->call('setPage', 2)->assertViewHas('events', fn ($rows) => $rows->count() === 3)
            ->set('action', 'created')->assertSet('paginators.page', 1)
            ->set('author', 'Gerente Auditor')->set('dateFrom', today()->format('Y-m-d'))->set('dateTo', today()->format('Y-m-d'))
            ->assertViewHas('events', fn ($rows) => $rows->total() === 1)
            ->set('record', '-1')->assertHasErrors('record')->assertViewHas('events', fn ($rows) => $rows->total() === 0)
            ->call('clearFilters')->assertHasNoErrors();
        foreach ([['entity' => 'invalid'], ['record' => ['1']], ['record' => '1 OR 1=1'], ['author' => ['manager']], ['dateFrom' => '2026-02-31']] as $parameters) {
            $this->get(route('manager.audit', $parameters))->assertNotFound();
        }
        $shift->delete();
        $this->get(route('manager.audit', ['entity' => 'shift', 'record' => $shift->id, 'action' => 'deleted']))->assertOk()->assertSee('Exclusão · Plantão #'.$shift->id);
    }
}
