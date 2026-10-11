<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'sqlite'], true)) {
            throw new LogicException('Operational audit immutability requires MySQL or SQLite.');
        }
        Schema::create('operational_audit_events', function (Blueprint $table): void {
            $table->id();
            // No cascading FK: deleting a record or an account must not rewrite its history.
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('entity_type', 30);
            $table->unsignedBigInteger('entity_id');
            $table->string('action', 30);
            $table->timestamp('occurred_at', 6);
            $table->uuid('operation_id');
            $table->json('changes');
            $table->index(['entity_type', 'entity_id', 'occurred_at'], 'audit_record_time_index');
            $table->index('occurred_at');
            $table->index(['author_id', 'occurred_at']);
            $table->index('operation_id');
        });

        foreach (['update', 'delete'] as $operation) {
            $name = 'operational_audit_deny_'.$operation;
            if (DB::getDriverName() === 'sqlite') {
                DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON operational_audit_events BEGIN SELECT RAISE(ABORT, 'Operational audit events are append-only'); END");
            } elseif (DB::getDriverName() === 'mysql') {
                DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON operational_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Operational audit events are append-only'");
            } else {
                throw new LogicException('Operational audit immutability requires MySQL or SQLite.');
            }
        }
    }

    public function down(): void
    {
        if (DB::table('operational_audit_events')->exists()) {
            throw new LogicException('A reversão não pode apagar histórico operacional existente. Preserve a tabela ao reverter o código.');
        }
        foreach (['update', 'delete'] as $operation) {
            DB::unprepared('DROP TRIGGER IF EXISTS operational_audit_deny_'.$operation);
        }
        Schema::dropIfExists('operational_audit_events');
    }
};
