<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_swaps', function (Blueprint $table): void {
            $table->dateTime('approved_at')->nullable();
            $table->index(['requester_id', 'status', 'approved_at'], 'swaps_requester_approved_at');
            $table->index(['substitute_id', 'status', 'approved_at'], 'swaps_substitute_approved_at');
        });

        DB::table('shift_swaps')->where('status', 'approved')
            ->update(['approved_at' => DB::raw('COALESCE(updated_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('shift_swaps', function (Blueprint $table): void {
            $table->dropIndex('swaps_requester_approved_at');
            $table->dropIndex('swaps_substitute_approved_at');
            $table->dropColumn('approved_at');
        });
    }
};
