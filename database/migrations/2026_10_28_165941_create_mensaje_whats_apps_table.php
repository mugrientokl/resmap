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
        Schema::create('mensajes_whatsapp', function (Blueprint $table): void {
            $table->id('id_mensaje');
            $table->foreignId('cotizacion_id')->nullable()->constrained('cotizaciones', 'id_cotizacion')->nullOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('cotizacion_versiones', 'id_version')->nullOnDelete();
            $table->string('direccion', 10);
            $table->string('telefono', 25);
            $table->string('tipo', 30);
            $table->string('estado', 30);
            $table->string('id_proveedor')->nullable()->unique();
            $table->string('clave_idempotencia')->nullable()->unique();
            $table->text('contenido')->nullable();
            $table->json('datos')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mensajes_whatsapp');
    }
};
