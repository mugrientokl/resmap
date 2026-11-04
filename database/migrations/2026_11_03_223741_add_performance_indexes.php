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
        Schema::table('ventas', function (Blueprint $table): void {
            $table->index('fecha');
        });
        Schema::table('solicitud_webs', function (Blueprint $table): void {
            $table->index('estado');
            $table->index('fecha');
        });
        Schema::table('productos', function (Blueprint $table): void {
            $table->index('stock');
            $table->index('nombre');
        });
        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            $table->index('fecha_evento');
            $table->index('tipo');
        });
        Schema::table('clientes', function (Blueprint $table): void {
            $table->index('nombre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->dropIndex(['fecha']);
        });
        Schema::table('solicitud_webs', function (Blueprint $table): void {
            $table->dropIndex(['estado']);
            $table->dropIndex(['fecha']);
        });
        Schema::table('productos', function (Blueprint $table): void {
            $table->dropIndex(['stock']);
            $table->dropIndex(['nombre']);
        });
        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            $table->dropIndex(['fecha_evento']);
            $table->dropIndex(['tipo']);
        });
        Schema::table('clientes', function (Blueprint $table): void {
            $table->dropIndex(['nombre']);
        });
    }
};
