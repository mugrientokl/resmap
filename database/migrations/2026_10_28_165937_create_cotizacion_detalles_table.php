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
        Schema::create('cotizacion_detalles', function (Blueprint $table): void {
            $table->id('id_detalle');
            $table->foreignId('version_id')->constrained('cotizacion_versiones', 'id_version')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos', 'id_producto')->nullOnDelete();
            $table->string('nombre_producto');
            $table->string('codigo_producto')->nullable();
            $table->unsignedInteger('cantidad');
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizacion_detalles');
    }
};
