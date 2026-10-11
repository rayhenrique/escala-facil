<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['sqlite', 'mysql'], true)) {
            throw new LogicException('Configure proteção append-only para este banco antes de habilitar reversões.');
        }
        Schema::create('shift_swap_reversals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shift_swap_id')->unique();
            $table->unsignedBigInteger('author_id');
            $table->unsignedBigInteger('approval_event_id');
            $table->timestamp('occurred_at', 6);
            $table->uuid('operation_id')->index();
            $table->text('reason');
            $table->json('before');
            $table->json('after');
        });
        foreach (['update', 'delete'] as $operation) {
            if (DB::getDriverName() === 'sqlite') {
                DB::unprepared("CREATE TRIGGER swap_reversal_deny_{$operation} BEFORE {$operation} ON shift_swap_reversals BEGIN SELECT RAISE(ABORT, 'Swap reversals are append-only'); END");
            } else {
                DB::unprepared("CREATE TRIGGER swap_reversal_deny_{$operation} BEFORE {$operation} ON shift_swap_reversals FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Swap reversals are append-only'");
            }
        }
    }

    public function down(): void
    {
        if (DB::table('shift_swap_reversals')->exists()) {
            throw new LogicException('Preserve os registros de reversão ao reverter o código.');
        }
        foreach (['update', 'delete'] as $operation) {
            DB::unprepared('DROP TRIGGER IF EXISTS swap_reversal_deny_'.$operation);
        }
        Schema::dropIfExists('shift_swap_reversals');
    }
};
