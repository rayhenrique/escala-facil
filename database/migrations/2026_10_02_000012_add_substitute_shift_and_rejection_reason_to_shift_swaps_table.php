<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_swaps', function (Blueprint $table): void {
            $table->foreignId('substitute_shift_id')
                ->nullable()
                ->after('shift_id')
                ->constrained('shifts')
                ->nullOnDelete();
            $table->text('rejection_reason')
                ->nullable()
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('shift_swaps', function (Blueprint $table): void {
            $table->dropForeign(['substitute_shift_id']);
            $table->dropColumn(['substitute_shift_id', 'rejection_reason']);
        });
    }
};
