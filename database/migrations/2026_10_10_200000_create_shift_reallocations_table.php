<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_reallocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('professional_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('manager_name');
            $table->timestamp('performed_at');
            $table->json('before');
            $table->json('after');
            $table->timestamps();
            $table->index(['shift_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_reallocations');
    }
};
