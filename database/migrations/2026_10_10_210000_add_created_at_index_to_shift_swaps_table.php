<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // All-status monthly queries cannot seek by the leading status of the existing composite index.
        Schema::table('shift_swaps', function (Blueprint $table): void {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('shift_swaps', function (Blueprint $table): void {
            $table->dropIndex(['created_at']);
        });
    }
};
