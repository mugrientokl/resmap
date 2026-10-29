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
        Schema::create('reservas_inventario', function (Blueprint $table): void {
            $table->id('id_reserva');
            $table->foreignId('cotizacion_id')->constrained('cotizaciones', 'id_cotizacion')->cascadeOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('cotizacion_versiones', 'id_version')->nullOnDelete();
            $table->foreignId('producto_id')->constrained('productos', 'id_producto')->cascadeOnDelete();
            $table->unsignedInteger('cantidad');
            $table->string('estado', 20)->default('activa')->index();
            $table->timestamp('expira_at')->index();
            $table->timestamp('consumida_at')->nullable();
            $table->timestamp('liberada_at')->nullable();
            $table->timestamps();

            $table->index(['producto_id', 'estado', 'expira_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservas_inventario');
    }
};
