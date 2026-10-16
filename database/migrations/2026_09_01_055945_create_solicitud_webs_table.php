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
        Schema::create('solicitud_webs', function (Blueprint $table) {
            $table->id('id_solicitud');
            $table->dateTime('fecha');
            $table->string('estado')->default('Pendiente');
            $table->unsignedBigInteger('id_cliente');
            $table->json('detalles_productos');
            
            $table->foreign('id_cliente')->references('id_cliente')->on('clientes')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitud_webs');
    }
};