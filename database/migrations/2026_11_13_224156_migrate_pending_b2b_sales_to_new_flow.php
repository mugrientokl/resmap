<?php

use App\Models\Venta;
use App\Services\VentaCotizacionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $ventas = Venta::query()
                ->whereNotNull('cotizacion_version_id')
                ->whereNull('stock_descontado_at')
                ->orderBy('id_venta')
                ->lockForUpdate()
                ->get();

            foreach ($ventas as $venta) {
                app(VentaCotizacionService::class)->completar($venta, (int) $venta->user_id);
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Los descuentos de inventario migrados no se revierten automáticamente.');
    }
};
