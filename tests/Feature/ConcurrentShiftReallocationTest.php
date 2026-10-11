<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentShiftReallocationTest extends TestCase
{
    public static function targets(): array
    {
        return ['same shift' => [false], 'overlapping shifts for the same professional' => [true]];
    }

    #[DataProvider('targets')]
    public function test_separate_processes_cannot_reallocate_simultaneously(bool $differentTargets): void
    {
        $database = tempnam(sys_get_temp_dir(), 'allocation-concurrency-');
        $barrier = $database.'.ready';
        $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database,
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array'];
        $boot = 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();';
        try {
            $migrate = new Process([PHP_BINARY, 'artisan', 'migrate', '--force'], base_path(), $environment);
            $migrate->mustRun();
            $seed = new Process([PHP_BINARY, '-r', $boot.'
                $manager=App\\Models\\User::factory()->create(["role"=>"manager"]);
                $otherManager=App\\Models\\User::factory()->create(["role"=>"manager"]);
                $professional=App\\Models\\User::factory()->create(["sector_id"=>App\\Models\\Sector::factory()->create()->id,"category_id"=>App\\Models\\Category::factory()->create()->id]);
                $shift=App\\Models\\Shift::factory()->create(["status"=>"uncovered","user_id"=>null,
                    "sector_id"=>$professional->sector_id,"category_id"=>$professional->category_id,
                    "date"=>today()->addDay(),"end_date"=>today()->addDay(),"start_time"=>"07:00","end_time"=>"19:00"]);
                $second=$shift->replicate(); $second->save();
                $otherProfessional=App\\Models\\User::factory()->create(["sector_id"=>$professional->sector_id,"category_id"=>$professional->category_id]);
                echo json_encode([$manager->id,$otherManager->id,$professional->id,$otherProfessional->id,$shift->id,$second->id]);'], base_path(), $environment);
            $seed->mustRun();
            [$managerId, $otherManagerId, $professionalId, $otherProfessionalId, $shiftId, $secondShiftId] = json_decode($seed->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $worker = $boot.'
                $manager=App\\Models\\User::findOrFail((int)$argv[1]);
                $professional=App\\Models\\User::findOrFail((int)$argv[2]);
                $shift=App\\Models\\Shift::findOrFail((int)$argv[3]);
                $preview=app(App\\Support\\ShiftReallocation::class)->preview($shift,$professional);
                file_put_contents($argv[4].".".$argv[5], "ready");
                while (!file_exists($argv[4])) { usleep(1000); }
                try {
                    app(App\\Actions\\Shifts\\ReallocateShiftAction::class)->execute($manager,$shift->id,$professional->id,$preview["fingerprint"]);
                    echo "decided";
                } catch (Illuminate\\Auth\\Access\\AuthorizationException|Illuminate\\Validation\\ValidationException $e) { echo "refused"; }
            ';
            $first = new Process([PHP_BINARY, '-r', $worker, (string) $managerId, (string) $professionalId, (string) $shiftId, $barrier, 'one'], base_path(), $environment);
            $second = new Process([PHP_BINARY, '-r', $worker, (string) $otherManagerId, (string) ($differentTargets ? $professionalId : $otherProfessionalId), (string) ($differentTargets ? $secondShiftId : $shiftId), $barrier, 'two'], base_path(), $environment);
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
            $this->assertSame(['decided', 'refused'], $outcomes);
            $pdo = new \PDO('sqlite:'.$database);
            $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM shift_reallocations')->fetchColumn());
            $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM shifts WHERE status = 'scheduled'")->fetchColumn());
            $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn());
            $events = $pdo->query("SELECT author_id, changes FROM operational_audit_events WHERE entity_type = 'shift' AND action = 'reallocated'")->fetchAll(\PDO::FETCH_ASSOC);
            $this->assertCount(1, $events);
            $this->assertContains((int) $events[0]['author_id'], [$managerId, $otherManagerId]);
            $changes = json_decode($events[0]['changes'], true, flags: JSON_THROW_ON_ERROR);
            $this->assertNull($changes['user_id']['before']);
            $this->assertContains($changes['user_id']['after'], [$professionalId, $otherProfessionalId]);
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
