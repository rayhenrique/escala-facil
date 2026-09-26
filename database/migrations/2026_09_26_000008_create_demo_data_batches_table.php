<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_data_batches', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('manifest');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_data_batches');
    }
};
