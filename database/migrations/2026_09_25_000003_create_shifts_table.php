<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('sector_id')->constrained();
            $table->foreignId('category_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->enum('status', ['scheduled', 'absence_reported', 'uncovered'])
                ->default('scheduled');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
