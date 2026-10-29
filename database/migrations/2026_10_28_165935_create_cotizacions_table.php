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
        Schema::create('cotizaciones', function (Blueprint $table): void {
            $table->id('id_cotizacion');
            $table->foreignId('solicitud_id')->unique()->constrained('solicitud_webs', 'id_solicitud')->cascadeOnDelete();
            $table->foreignId('creada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 40)->default('pendiente_revision')->index();
            $table->unsignedSmallInteger('ultima_version')->default(0);
            $table->timestamp('revisada_at')->nullable();
            $table->text('comentarios_cliente')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizaciones');
    }
};
