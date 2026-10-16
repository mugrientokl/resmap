<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table): void {
            $table->id('id_auditoria');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('modelo');
            $table->unsignedBigInteger('modelo_id')->nullable();
            $table->string('accion', 30);
            $table->json('datos_anteriores')->nullable();
            $table->json('datos_nuevos')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->timestamps();
            $table->index(['modelo', 'modelo_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
