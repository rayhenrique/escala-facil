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
            $table->string('professional_response', 16)->nullable();
            $table->timestamp('professional_responded_at')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('shift_swaps')->whereNotNull('professional_response')->exists()) {
            throw new RuntimeException('Preserve o histórico de respostas profissionais; esta migração contém registros em uso.');
        }
        Schema::table('shift_swaps', fn (Blueprint $table) => $table->dropColumn(['professional_response', 'professional_responded_at']));
    }
};
