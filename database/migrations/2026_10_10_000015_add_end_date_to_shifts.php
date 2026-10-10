<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NULL mantém a interpretação histórica; nenhum registro é reescrito.
        Schema::table('shifts', function (Blueprint $table): void {
            $table->date('end_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table): void {
            $table->dropColumn('end_date');
        });
    }
};
