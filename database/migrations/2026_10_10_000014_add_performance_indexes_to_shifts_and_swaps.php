<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices justificados pelos caminhos quentes de leitura:
     * - painel gerencial filtra plantões por status com recorte de data;
     * - calendário consulta o intervalo do mês exibido;
     * - fila de trocas ordena pendências por antiguidade.
     */
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table): void {
            $table->index(['status', 'date']);
            $table->index(['date']);
        });

        Schema::table('shift_swaps', function (Blueprint $table): void {
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('shift_swaps', function (Blueprint $table): void {
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('shifts', function (Blueprint $table): void {
            $table->dropIndex(['date']);
            $table->dropIndex(['status', 'date']);
        });
    }
};
