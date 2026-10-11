<?php

namespace Tests\Feature;

use App\Actions\Accounts\InviteProfessionalAction;
use App\Models\Category;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class AccountInvitationQueueTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        // Real commits exercise afterCommit; the disposable DB is recreated without dropping immutable history through rollback.
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_queue_payload_is_encrypted_and_superseded_delivery_is_skipped(): void
    {
        config(['queue.default' => 'database', 'mail.default' => 'array', 'invitations.mailer' => null]);
        Event::fake([NotificationSent::class]);
        $manager = User::factory()->create(['role' => 'manager']);
        $invitation = app(InviteProfessionalAction::class)->create($manager, ['name' => 'Profissional', 'email' => 'queue@example.test', 'role' => 'professional', 'sector_id' => Sector::factory()->create()->id, 'category_id' => Category::factory()->create()->id]);
        $this->assertDatabaseCount('jobs', 1);
        $payload = json_decode(DB::table('jobs')->value('payload'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('queue@example.test', $payload['data']['command']);
        $this->assertStringNotContainsString('AccountInvitationNotification', $payload['data']['command']);
        // Laravel decrypts only while executing the worker; application tables contain only the digest.
        $decoded = decrypt($payload['data']['command']);
        $this->assertStringContainsString('AccountInvitationNotification', $decoded);
        app(InviteProfessionalAction::class)->resend($manager, $invitation->user_id);
        $this->assertDatabaseCount('jobs', 2);
        Artisan::call('queue:work', ['--once' => true, '--sleep' => 0]);
        Event::assertNotDispatched(NotificationSent::class);
        Artisan::call('queue:work', ['--once' => true, '--sleep' => 0]);
        Event::assertDispatchedTimes(NotificationSent::class, 1);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_outer_rollback_removes_account_audit_and_does_not_queue_email(): void
    {
        config(['queue.default' => 'database', 'mail.default' => 'array', 'invitations.mailer' => null]);
        $manager = User::factory()->create(['role' => 'manager']);
        $sector = Sector::factory()->create();
        $category = Category::factory()->create();
        $events = DB::table('operational_audit_events')->count();
        try {
            DB::transaction(function () use ($manager, $sector, $category): void {
                app(InviteProfessionalAction::class)->create($manager, ['name' => 'Profissional', 'email' => 'rollback@example.test', 'role' => 'professional', 'sector_id' => $sector->id, 'category_id' => $category->id]);
                throw new RuntimeException('Rollback test');
            });
        } catch (RuntimeException) {
        }
        $this->assertDatabaseMissing('users', ['email' => 'rollback@example.test']);
        $this->assertDatabaseCount('account_invitations', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame($events, DB::table('operational_audit_events')->count());
    }
}
