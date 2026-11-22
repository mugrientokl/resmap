<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('costo_promedio_neto', 12, 2)->nullable()->after('precio');
            $table->decimal('margen_objetivo', 5, 2)->default(30)->after('costo_promedio_neto');
            $table->string('modo_precio', 20)->default('manual')->after('margen_objetivo');
        });

        Schema::table('inventario_movimientos', function (Blueprint $table) {
            $table->decimal('costo_unitario_neto', 12, 2)->nullable()->after('precio_entrada');
            $table->decimal('costo_total_neto', 12, 2)->nullable()->after('costo_unitario_neto');
        });

        $adquisicionesHistoricas = DB::table('inventario_movimientos')
            ->whereIn('tipo', ['ingreso', 'adquisicion'])
            ->whereNotNull('precio_entrada')
            ->whereNotNull('cantidad_adquirida')
            ->where('cantidad_adquirida', '>', 0)
            ->orderBy('fecha_evento')
            ->orderBy('created_at')
            ->orderBy('id_movimiento')
            ->get(['id_movimiento', 'id_producto', 'precio_entrada', 'cantidad_adquirida']);

        foreach ($adquisicionesHistoricas as $adquisicion) {
            $unitario = round((float) $adquisicion->precio_entrada / (int) $adquisicion->cantidad_adquirida, 2);
            DB::table('inventario_movimientos')
                ->where('id_movimiento', $adquisicion->id_movimiento)
                ->update([
                    'costo_unitario_neto' => $unitario,
                    'costo_total_neto' => round($unitario * (int) $adquisicion->cantidad_adquirida, 2),
                ]);

            DB::table('productos')
                ->where('id_producto', $adquisicion->id_producto)
                ->update([
                    'costo_promedio_neto' => $unitario,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table) {
            $table->dropColumn(['costo_unitario_neto', 'costo_total_neto']);
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['costo_promedio_neto', 'margen_objetivo', 'modo_precio']);
        });
    }
};
