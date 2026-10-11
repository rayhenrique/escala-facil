<?php

namespace Tests\Feature;

use App\Enums\ShiftSwapStatus;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentSwapReversionTest extends TestCase
{
    public function test_separate_processes_cannot_both_revert_the_same_mutual_swap(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'reversion-concurrency-');
        $barrier = $database.'.ready';
        $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database,
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array'];
        $boot = 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();';
        try {
            $migrate = new Process([PHP_BINARY, 'artisan', 'migrate', '--force'], base_path(), $environment);
            $migrate->mustRun();
            $seed = new Process([PHP_BINARY, '-r', $boot.'
                $manager=App\\Models\\User::factory()->create(["role"=>"manager"]);
                $shift=App\\Models\\Shift::factory()->create(["status"=>"scheduled"]);
                $sub=App\\Models\\User::factory()->create(["sector_id"=>$shift->sector_id,"category_id"=>$shift->category_id]);
                App\\Models\\User::findOrFail($shift->user_id)->update(["sector_id"=>$shift->sector_id,"category_id"=>$shift->category_id]);
                $counterpart=$shift->replicate();
                $counterpart->fill(["user_id"=>$sub->id,"date"=>$shift->date->copy()->addDay()])->save();
                $swap=App\\Models\\ShiftSwap::factory()->create(["shift_id"=>$shift->id,"substitute_shift_id"=>$counterpart->id,"requester_id"=>$shift->user_id,"substitute_id"=>$sub->id,"status"=>"pending_manager"]);
                app(App\\Actions\\ShiftSwaps\\DecideShiftSwapAction::class)->approve($manager,$swap->id);
                $snapshot=app(App\\Support\\ShiftSwapReversion::class)->inspect($swap->fresh())["fingerprint"];
                echo json_encode([$manager->id,$swap->id,$counterpart->id,$snapshot]);'], base_path(), $environment);
            $seed->mustRun();
            [$managerId, $swapId, $counterpartId, $snapshot] = json_decode($seed->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $worker = $boot.'
                $manager=App\\Models\\User::findOrFail((int)$argv[1]);
                file_put_contents($argv[3].".".$argv[4], "ready");
                while (!file_exists($argv[3])) { usleep(1000); }
                try {
                    app(App\\Actions\\ShiftSwaps\\RevertShiftSwapAction::class)->execute($manager,(int)$argv[2],"Correção administrativa confirmada.",$argv[5]);
                    echo "decided";
                } catch (Illuminate\\Auth\\Access\\AuthorizationException|Illuminate\\Validation\\ValidationException $e) { echo "refused"; }
            ';
            $first = new Process([PHP_BINARY, '-r', $worker, (string) $managerId, (string) $swapId, $barrier, 'approve', $snapshot], base_path(), $environment);
            $second = new Process([PHP_BINARY, '-r', $worker, (string) $managerId, (string) $swapId, $barrier, 'reject', $snapshot], base_path(), $environment);
            $first->start();
            $second->start();
            $deadline = microtime(true) + 10;
            while ((! file_exists($barrier.'.approve') || ! file_exists($barrier.'.reject')) && microtime(true) < $deadline) {
                usleep(1000);
            }
            $this->assertFileExists($barrier.'.approve');
            $this->assertFileExists($barrier.'.reject');
            file_put_contents($barrier, 'go');
            $first->wait();
            $second->wait();
            $this->assertTrue($first->isSuccessful(), $first->getOutput().$first->getErrorOutput());
            $this->assertTrue($second->isSuccessful(), $second->getOutput().$second->getErrorOutput());
            $outcomes = [$first->getOutput(), $second->getOutput()];
            sort($outcomes);
            $this->assertSame(['decided', 'refused'], $outcomes);
            $pdo = new \PDO('sqlite:'.$database);
            $this->assertSame(4, (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn());
            $statement = $pdo->prepare('SELECT swaps.status, shifts.user_id, swaps.requester_id, swaps.substitute_id FROM shift_swaps swaps JOIN shifts ON shifts.id = swaps.shift_id WHERE swaps.id = ?');
            $statement->execute([$swapId]);
            $record = $statement->fetch(\PDO::FETCH_ASSOC);
            $status = $record['status'];
            $this->assertSame(ShiftSwapStatus::Reverted->value, $status);
            $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM shift_swap_reversals')->fetchColumn());
            $this->assertSame((int) $record['requester_id'], (int) $record['user_id']);
            $counterpartOwner = $pdo->query('SELECT user_id FROM shifts WHERE id = '.(int) $counterpartId)->fetchColumn();
            $this->assertSame((int) $record['substitute_id'], (int) $counterpartOwner);
            $audit = $pdo->prepare("SELECT author_id, action FROM operational_audit_events WHERE entity_type = 'swap' AND entity_id = ? AND action = 'reverted'");
            $audit->execute([$swapId]);
            $decisions = $audit->fetchAll(\PDO::FETCH_ASSOC);
            $this->assertCount(1, $decisions);
            $this->assertSame($managerId, (int) $decisions[0]['author_id']);
            $this->assertSame($status, $decisions[0]['action']);
            $audit = null;
            $statement = null;
            $pdo = null;
        } finally {
            foreach ([$first ?? null, $second ?? null] as $process) {
                if ($process?->isRunning()) {
                    $process->stop();
                }
            }
            foreach ([$database, $database.'-journal', $database.'-wal', $database.'-shm', $barrier, $barrier.'.approve', $barrier.'.reject'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
