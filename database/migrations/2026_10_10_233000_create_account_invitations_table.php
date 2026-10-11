<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('requires_activation')->default(false));
        Schema::create('account_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('invited_by');
            $table->string('token_hash', 64)->nullable()->unique();
            $table->string('email_hash', 64);
            $table->unsignedInteger('generation')->default(1);
            $table->timestamp('expires_at');
            $table->timestamp('last_sent_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('account_invitations')->exists() || DB::table('users')->where('requires_activation', true)->exists()) {
            throw new LogicException('Preserve os convites e o estado de ativação ao reverter o código. A migração não pode liberar contas pendentes.');
        }
        Schema::dropIfExists('account_invitations');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('requires_activation'));
    }
};
