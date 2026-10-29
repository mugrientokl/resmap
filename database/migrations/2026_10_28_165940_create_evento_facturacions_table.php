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
        Schema::create('eventos_facturacion', function (Blueprint $table): void {
            $table->id('id_evento');
            $table->foreignId('venta_id')->constrained('ventas', 'id_venta')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('proveedor', 30)->default('simulado');
            $table->string('resultado', 20);
            $table->string('clave_idempotencia')->unique();
            $table->json('respuesta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eventos_facturacion');
    }
};
