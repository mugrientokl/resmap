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
        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            $table->string('proveedor')->nullable()->after('motivo');
            $table->string('documento_proveedor')->nullable()->after('proveedor');
            $table->decimal('precio_entrada', 12, 2)->nullable()->after('documento_proveedor');
            $table->unsignedInteger('cantidad_adquirida')->nullable()->after('precio_entrada');
            $table->text('observaciones')->nullable()->after('cantidad_adquirida');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            $table->dropColumn([
                'proveedor',
                'documento_proveedor',
                'precio_entrada',
                'cantidad_adquirida',
                'observaciones',
            ]);
        });
    }
};
