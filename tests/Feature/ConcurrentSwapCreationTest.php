<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentSwapCreationTest extends TestCase
{
    public function test_simultaneous_confirmations_create_one_request_and_one_notification(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'swap-create-');
        $barrier = $database.'.ready';
        $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database,
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array'];
        $boot = 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();';
        try {
            (new Process([PHP_BINARY, 'artisan', 'migrate', '--force'], base_path(), $environment))->mustRun();
            $seed = new Process([PHP_BINARY, '-r', $boot.'
                $user=App\Models\User::factory()->create(["sector_id"=>App\Models\Sector::factory(),"category_id"=>App\Models\Category::factory()]);
                $sub=App\Models\User::factory()->create(["sector_id"=>$user->sector_id,"category_id"=>$user->category_id]);
                $shift=App\Models\Shift::factory()->create(["user_id"=>$user->id,"sector_id"=>$user->sector_id,"category_id"=>$user->category_id,"date"=>today()->addDays(3)]);
                echo json_encode([$user->id,$sub->id,$shift->id]);'], base_path(), $environment);
            $seed->mustRun();
            $ids = json_decode($seed->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $worker = $boot.'
                file_put_contents($argv[4].".".$argv[5],"ready");
                while (!file_exists($argv[4])) usleep(1000);
                try {
                    app(App\Actions\ShiftSwaps\CreateShiftSwapAction::class)->execute(App\Models\User::findOrFail($argv[1]),["substitute_id"=>$argv[2],"shift_id"=>$argv[3]]);
                    echo "created";
                } catch (Illuminate\Validation\ValidationException $e) { echo "refused"; }
            ';
            $first = new Process([PHP_BINARY, '-r', $worker, ...array_map('strval', $ids), $barrier, 'first'], base_path(), $environment);
            $second = new Process([PHP_BINARY, '-r', $worker, ...array_map('strval', $ids), $barrier, 'second'], base_path(), $environment);
            $first->start();
            $second->start();
            $deadline = microtime(true) + 10;
            while ((! file_exists($barrier.'.first') || ! file_exists($barrier.'.second')) && microtime(true) < $deadline) {
                usleep(1000);
            }
            $this->assertFileExists($barrier.'.first');
            $this->assertFileExists($barrier.'.second');
            file_put_contents($barrier, 'go');
            $first->wait();
            $second->wait();
            foreach ([$first, $second] as $process) {
                $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
            }
            $outcomes = [$first->getOutput(), $second->getOutput()];
            sort($outcomes);
            $this->assertSame(['created', 'refused'], $outcomes);
            $pdo = new \PDO('sqlite:'.$database);
            $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM shift_swaps')->fetchColumn());
            $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn());
            $pdo = null;
        } finally {
            foreach ([$first ?? null, $second ?? null] as $process) {
                if ($process?->isRunning()) {
                    $process->stop();
                }
            }
            foreach ([$database, $database.'-journal', $database.'-wal', $database.'-shm', $barrier, $barrier.'.first', $barrier.'.second'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
