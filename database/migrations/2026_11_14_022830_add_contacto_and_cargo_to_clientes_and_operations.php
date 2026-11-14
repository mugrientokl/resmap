<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            $table->string('contacto')->nullable();
        });

        Schema::create('cliente_contactos_historial', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_cliente')->constrained('clientes', 'id_cliente')->cascadeOnDelete();
            $table->string('contacto');
            $table->timestamp('cambiado_at');
        });

        foreach (['solicitud_webs', 'ventas'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table): void {
                $table->string('contacto')->nullable();
                $table->string('cargo')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['solicitud_webs', 'ventas'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table): void {
                $table->dropColumn(['contacto', 'cargo']);
            });
        }

        Schema::dropIfExists('cliente_contactos_historial');
        Schema::table('clientes', function (Blueprint $table): void {
            $table->dropColumn('contacto');
        });
    }
};
