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
        Schema::create('productos_importados', function (Blueprint $table) {
            $table->id();
            $table->string('archivo_origen');
            $table->unsignedInteger('fila_origen');
            $table->string('it')->nullable();
            $table->string('ubicacion')->nullable();
            $table->string('stock_origen')->nullable();
            $table->text('detalle')->nullable();
            $table->string('codigo_origen')->nullable();
            $table->string('unidad')->nullable();
            $table->string('precio_iva_origen')->nullable();
            $table->string('categoria_origen')->nullable();
            $table->string('precio_neto_origen')->nullable();
            $table->json('datos_originales')->nullable();
            $table->string('estado')->default('pendiente');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['archivo_origen', 'fila_origen']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos_importados');
    }
};
