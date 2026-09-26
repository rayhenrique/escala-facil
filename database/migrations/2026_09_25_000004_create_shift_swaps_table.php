<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_swaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('substitute_id')->constrained('users');
            $table->enum('status', [
                'pending_professional',
                'pending_manager',
                'approved',
                'rejected',
            ]);
            $table->foreignId('manager_id')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_swaps');
    }
};
