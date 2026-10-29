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
        Schema::create('ordenes_compra', function (Blueprint $table): void {
            $table->id('id_orden_compra');
            $table->foreignId('version_id')->constrained('cotizacion_versiones', 'id_version')->cascadeOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas', 'id_venta')->nullOnDelete();
            $table->string('modalidad', 30);
            $table->string('numero')->nullable();
            $table->string('archivo_path')->nullable();
            $table->char('archivo_sha256', 64)->nullable();
            $table->foreignId('autorizada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('autorizada_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordenes_compra');
    }
};
