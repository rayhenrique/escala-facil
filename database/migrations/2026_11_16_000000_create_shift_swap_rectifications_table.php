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
            throw new LogicException('Configure proteção append-only antes de habilitar retificações.');
        }
        Schema::create('shift_swap_rectifications', function (Blueprint $table): void {
            $table->id();
            // No cascading foreign keys: the evidence must survive record/account deletion.
            $table->unsignedBigInteger('shift_swap_id')->unique();
            $table->unsignedBigInteger('author_id');
            $table->timestamp('occurred_at', 6);
            $table->uuid('operation_id')->index();
            $table->string('kind', 30);
            $table->text('reason');
            $table->text('evidence_notes');
            $table->json('evidence');
            $table->json('receipt');
        });
        foreach (['update', 'delete'] as $operation) {
            $sql = DB::getDriverName() === 'sqlite'
                ? "CREATE TRIGGER swap_rectification_deny_{$operation} BEFORE {$operation} ON shift_swap_rectifications BEGIN SELECT RAISE(ABORT, 'Administrative records are append-only'); END"
                : "CREATE TRIGGER swap_rectification_deny_{$operation} BEFORE {$operation} ON shift_swap_rectifications FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Administrative records are append-only'";
            DB::unprepared($sql);
        }
    }

    public function down(): void
    {
        if (DB::table('shift_swap_rectifications')->exists()) {
            throw new LogicException('Preserve os registros administrativos ao reverter o código.');
        }
        foreach (['update', 'delete'] as $operation) {
            DB::unprepared('DROP TRIGGER IF EXISTS swap_rectification_deny_'.$operation);
        }
        Schema::dropIfExists('shift_swap_rectifications');
    }
};
