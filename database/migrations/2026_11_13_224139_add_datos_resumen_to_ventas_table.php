<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->json('datos_resumen')->nullable();
        });
        Schema::table('cotizacion_versiones', function (Blueprint $table): void {
            $table->foreignId('respuesta_registrada_por')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->dropColumn('datos_resumen');
        });
        Schema::table('cotizacion_versiones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('respuesta_registrada_por');
        });
    }
};
