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
        Schema::create('eventos_reserva', function (Blueprint $table): void {
            $table->id('id_evento_reserva');
            $table->foreignId('reserva_id')->constrained('reservas_inventario', 'id_reserva')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('evento', 30);
            $table->timestamp('expiracion_anterior')->nullable();
            $table->timestamp('expiracion_nueva')->nullable();
            $table->json('datos')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eventos_reserva');
    }
};
