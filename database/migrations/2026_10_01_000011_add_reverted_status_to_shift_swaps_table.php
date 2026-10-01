<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const STATUSES = [
        'pending_professional',
        'pending_manager',
        'approved',
        'rejected',
        'reverted',
    ];

    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            $values = implode("','", self::STATUSES);

            DB::statement("ALTER TABLE shift_swaps MODIFY COLUMN status ENUM('{$values}')");

            return;
        }

        if ($driver === 'sqlite') {
            Schema::create('shift_swaps_reverted', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
                $table->foreignId('requester_id')->constrained('users');
                $table->foreignId('substitute_id')->constrained('users');
                $table->enum('status', self::STATUSES);
                $table->foreignId('manager_id')->nullable()->constrained('users');
                $table->timestamps();
            });

            DB::table('shift_swaps_reverted')->insertUsing(
                ['id', 'shift_id', 'requester_id', 'substitute_id', 'status', 'manager_id', 'created_at', 'updated_at'],
                DB::table('shift_swaps')->select(['id', 'shift_id', 'requester_id', 'substitute_id', 'status', 'manager_id', 'created_at', 'updated_at']),
            );

            Schema::drop('shift_swaps');
            Schema::rename('shift_swaps_reverted', 'shift_swaps');
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE shift_swaps MODIFY COLUMN status ENUM('pending_professional','pending_manager','approved','rejected')");
        }
    }
};
