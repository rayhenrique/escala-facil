<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentAccountActivationTest extends TestCase
{
    public function test_two_activations_can_consume_token_only_once(): void
    {
        $this->race(false);
    }

    public function test_revocation_and_activation_cannot_both_succeed(): void
    {
        $this->race(true);
    }

    private function race(bool $revoke): void
    {
        $database = storage_path('framework/testing/invitation-'.Str::uuid().'.sqlite');
        $barrier = $database.'.ready';
        touch($database);
        $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array'];
        $boot = 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();';
        try {
            (new Process([PHP_BINARY, 'artisan', 'migrate', '--force'], base_path(), $environment))->mustRun();
            $seed = new Process([PHP_BINARY, '-r', $boot.'
                Illuminate\\Support\\Facades\\Notification::fake(); config(["invitations.mailer"=>null]);
                $manager=App\\Models\\User::factory()->create(["role"=>"manager"]);
                $invitation=app(App\\Actions\\Accounts\\InviteProfessionalAction::class)->create($manager,["name"=>"Profissional","email"=>"race@example.test","role"=>"professional","sector_id"=>App\\Models\\Sector::factory()->create()->id,"category_id"=>App\\Models\\Category::factory()->create()->id]);
                echo json_encode([$manager->id,$invitation->user_id,$invitation->token_hash]);'], base_path(), $environment);
            $seed->mustRun();
            [$manager, $user, $hash] = json_decode($seed->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $worker = $boot.'
                file_put_contents($argv[3].".".$argv[4],"ready"); while(!file_exists($argv[3])) { usleep(1000); }
                try {
                    if($argv[5]==="revoke") app(App\\Actions\\Accounts\\InviteProfessionalAction::class)->revoke(App\\Models\\User::findOrFail((int)$argv[1]),(int)$argv[2]);
                    else app(App\\Actions\\Accounts\\ActivateAccountAction::class)->execute($argv[6],["password"=>"Own-password-2026","password_confirmation"=>"Own-password-2026"]);
                    echo "saved";
                } catch(Illuminate\\Validation\\ValidationException|Illuminate\\Auth\\Access\\AuthorizationException $e) { echo "refused"; }';
            $first = new Process([PHP_BINARY, '-r', $worker, (string) $manager, (string) $user, $barrier, 'one', 'activate', $hash], base_path(), $environment);
            $second = new Process([PHP_BINARY, '-r', $worker, (string) $manager, (string) $user, $barrier, 'two', $revoke ? 'revoke' : 'activate', $hash], base_path(), $environment);
            $first->start();
            $second->start();
            $deadline = microtime(true) + 10;
            while ((! file_exists($barrier.'.one') || ! file_exists($barrier.'.two')) && microtime(true) < $deadline) {
                usleep(1000);
            }
            $this->assertFileExists($barrier.'.one');
            $this->assertFileExists($barrier.'.two');
            file_put_contents($barrier, 'go');
            $first->wait();
            $second->wait();
            $this->assertTrue($first->isSuccessful(), $first->getOutput().$first->getErrorOutput());
            $this->assertTrue($second->isSuccessful(), $second->getOutput().$second->getErrorOutput());
            $outcomes = [$first->getOutput(), $second->getOutput()];
            sort($outcomes);
            $this->assertSame(['refused', 'saved'], $outcomes);
            $pdo = new \PDO('sqlite:'.$database);
            $record = $pdo->query('SELECT accepted_at, revoked_at, token_hash FROM account_invitations')->fetch(\PDO::FETCH_ASSOC);
            $this->assertNull($record['token_hash']);
            $this->assertSame(1, (int) ($record['accepted_at'] !== null) + (int) ($record['revoked_at'] !== null));
            $this->assertSame((int) ($record['accepted_at'] === null), (int) $pdo->query("SELECT requires_activation FROM users WHERE email='race@example.test'")->fetchColumn());
            $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM operational_audit_events WHERE entity_type='invitation' AND action IN ('activated','revoked')")->fetchColumn());
            $pdo = null;
        } finally {
            foreach ([$first ?? null, $second ?? null] as $process) {
                if ($process?->isRunning()) {
                    $process->stop();
                }
            }
            foreach ([$database, $database.'-journal', $database.'-wal', $database.'-shm', $barrier, $barrier.'.one', $barrier.'.two'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
