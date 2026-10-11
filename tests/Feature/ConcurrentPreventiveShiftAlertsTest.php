<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentPreventiveShiftAlertsTest extends TestCase
{
    public function test_two_creations_cannot_commit_the_same_stale_availability(): void
    {
        $this->race(false);
    }

    public function test_two_edits_cannot_commit_the_same_stale_record(): void
    {
        $this->race(true);
    }

    private function race(bool $editing): void
    {
        $database = storage_path('framework/testing/preventive-'.Str::uuid().'.sqlite');
        $barrier = $database.'.ready';
        touch($database);
        $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_FOREIGN_KEYS' => 'true', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array'];
        $boot = 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();';
        try {
            (new Process([PHP_BINARY, 'artisan', 'migrate', '--force'], base_path(), $environment))->mustRun();
            $seed = new Process([PHP_BINARY, '-r', $boot.'
                $manager=App\\Models\\User::factory()->create(["role"=>"manager"]);
                $other=App\\Models\\User::factory()->create(["role"=>"manager"]);
                $professional=App\\Models\\User::factory()->create(["sector_id"=>App\\Models\\Sector::factory()->create()->id,"category_id"=>App\\Models\\Category::factory()->create()->id]);
                $shift=App\\Models\\Shift::factory()->create(["status"=>"uncovered","user_id"=>null,"sector_id"=>$professional->sector_id,"category_id"=>$professional->category_id,"date"=>today()->addDay(),"end_date"=>today()->addDays(2),"start_time"=>"07:00","end_time"=>"07:00"]);
                echo json_encode([$manager->id,$other->id,$professional->id,$shift->id]);'], base_path(), $environment);
            $seed->mustRun();
            [$manager, $other, $professional, $shift] = json_decode($seed->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $worker = $boot.'
                $manager=App\\Models\\User::findOrFail((int)$argv[1]);
                $professional=App\\Models\\User::findOrFail((int)$argv[2]);
                $shift=App\\Models\\Shift::findOrFail((int)$argv[3]);
                $payload=$shift->only(["date","end_date","start_time","end_time","sector_id","category_id"]);
                $payload["date"]=$shift->date->format("Y-m-d"); $payload["end_date"]=$shift->end_date->format("Y-m-d");
                $payload["user_id"]=$professional->id; $payload["status"]="scheduled";
                $candidate=new App\\Models\\Shift($payload); if($argv[6]==="edit") $candidate->id=$shift->id;
                $preview=app(App\\Support\\ShiftAssignmentAnalysis::class)->preview($candidate);
                $signature=app(App\\Support\\ShiftReallocation::class)->shiftSignature($shift);
                file_put_contents($argv[4].".".$argv[5],"ready"); while(!file_exists($argv[4])) { usleep(1000); }
                try {
                    if($argv[6]==="edit") app(App\\Actions\\Shifts\\UpdateShiftAction::class)->execute($manager,$shift->id,$payload,$signature,$preview["fingerprint"]);
                    else app(App\\Actions\\Shifts\\CreateShiftAction::class)->execute($manager,$payload,$preview["fingerprint"]);
                    echo "saved";
                } catch(Illuminate\\Validation\\ValidationException $e) { echo "refused"; }';
            $first = new Process([PHP_BINARY, '-r', $worker, (string) $manager, (string) $professional, (string) $shift, $barrier, 'one', $editing ? 'edit' : 'create'], base_path(), $environment);
            $second = new Process([PHP_BINARY, '-r', $worker, (string) $other, (string) $professional, (string) $shift, $barrier, 'two', $editing ? 'edit' : 'create'], base_path(), $environment);
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
            $results = [$first->getOutput(), $second->getOutput()];
            sort($results);
            $this->assertSame(['refused', 'saved'], $results);
            $pdo = new \PDO('sqlite:'.$database);
            $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM shifts WHERE status = 'scheduled'")->fetchColumn());
            $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM operational_audit_events WHERE author_id IS NOT NULL')->fetchColumn());
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
