<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentMonthlySwapQuotaTest extends TestCase
{
    public function test_two_approvals_sharing_a_professional_cannot_consume_the_fifth_slot(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'monthly-quota-');
        $barrier = $database.'.ready';
        $environment = ['APP_ENV' => 'testing', 'APP_TIMEZONE' => 'America/Maceio', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database,
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array'];
        $boot = 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
            Carbon\\CarbonImmutable::setTestNow(Carbon\\CarbonImmutable::parse("2026-10-31 23:59:59", "America/Maceio"));';
        try {
            (new Process([PHP_BINARY, 'artisan', 'migrate', '--force'], base_path(), $environment))->mustRun();
            $seed = new Process([PHP_BINARY, '-r', $boot.'
                $manager=App\\Models\\User::factory()->create(["role"=>"manager"]);
                $other=App\\Models\\User::factory()->create(["role"=>"manager"]);
                $requester=App\\Models\\User::factory()->create(["sector_id"=>App\\Models\\Sector::factory()->create()->id,"category_id"=>App\\Models\\Category::factory()->create()->id]);
                $profile=$requester->only(["sector_id","category_id"]);
                $sub=App\\Models\\User::factory()->create($profile);
                for($i=0;$i<3;$i++) App\\Models\\ShiftSwap::factory()->approved()->create(["requester_id"=>$requester->id,"substitute_id"=>$sub->id,"approved_at"=>"2026-10-10 10:00:00"]);
                $ids=[];
                foreach(["2026-11-10","2026-11-12"] as $date) {
                    $shift=App\\Models\\Shift::factory()->create($profile+["user_id"=>$requester->id,"date"=>$date,"start_time"=>"07:00","end_time"=>"19:00"]);
                    $ids[]=App\\Models\\ShiftSwap::factory()->pendingManager()->create(["shift_id"=>$shift->id,"requester_id"=>$requester->id,"substitute_id"=>$sub->id])->id;
                }
                echo json_encode([$manager->id,$other->id,$requester->id,$sub->id,...$ids]);',
            ], base_path(), $environment);
            $seed->mustRun();
            [$manager, $other, $requester, $sub, $one, $two] = json_decode($seed->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $worker = $boot.'
                $manager=App\\Models\\User::findOrFail((int)$argv[1]);
                file_put_contents($argv[3].".".$argv[4], "ready");
                while(!file_exists($argv[3])) { usleep(1000); }
                try {
                    app(App\\Actions\\ShiftSwaps\\DecideShiftSwapAction::class)->approve($manager,(int)$argv[2]);
                    echo "approved";
                } catch(Illuminate\\Auth\\Access\\AuthorizationException|Illuminate\\Validation\\ValidationException $e) { echo "refused"; }';
            $first = new Process([PHP_BINARY, '-r', $worker, (string) $manager, (string) $one, $barrier, 'one'], base_path(), $environment);
            $second = new Process([PHP_BINARY, '-r', $worker, (string) $other, (string) $two, $barrier, 'two'], base_path(), $environment);
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
            $this->assertSame(['approved', 'refused'], $outcomes);
            $pdo = new \PDO('sqlite:'.$database);
            $count = $pdo->prepare("SELECT COUNT(*) FROM shift_swaps WHERE status='approved' AND approved_at>='2026-10-01' AND approved_at<'2026-11-01' AND (requester_id=? OR substitute_id=?)");
            foreach ([$requester, $sub] as $participant) {
                $count->execute([$participant, $participant]);
                $this->assertSame(4, (int) $count->fetchColumn());
            }
            $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM operational_audit_events WHERE entity_type='swap' AND action='approved'")->fetchColumn());
            $count = null;
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
