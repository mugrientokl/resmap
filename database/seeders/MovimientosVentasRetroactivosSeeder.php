<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reconstruye el kardex de las ventas que se cargaron sin movimiento de inventario.
 *
 * Por cada producto crea una entrada inicial anterior a su primera venta y una salida
 * por cada venta, de modo que el stock actual no cambia pero el historial cuadra.
 * Es idempotente: solo procesa ventas sin movimientos asociados.
 */
class MovimientosVentasRetroactivosSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Este seeder retroactivo no puede ejecutarse en producción.');
        }

        $lineas = DB::table('detalle_ventas as d')
            ->join('ventas as v', 'v.id_venta', '=', 'd.id_venta')
            ->whereNotNull('d.id_producto')
            ->where(function ($query): void {
                $query->whereNull('v.estado_venta')->orWhereNotIn('v.estado_venta', ['cancelada', 'anulada']);
            })
            ->whereNotExists(function ($query): void {
                $query->from('inventario_movimientos as m')
                    ->whereColumn('m.id_venta', 'v.id_venta')
                    ->whereColumn('m.id_producto', 'd.id_producto');
            })
            ->orderBy('v.fecha')
            ->orderBy('d.id_detalle')
            ->get(['d.id_producto', 'd.cantidad', 'v.id_venta', 'v.fecha', 'v.user_id'])
            ->groupBy('id_producto');

        $entradas = 0;
        $salidas = 0;

        DB::transaction(function () use ($lineas, &$entradas, &$salidas): void {
            foreach ($lineas as $idProducto => $ventas) {
                $stockActual = (int) DB::table('productos')->where('id_producto', $idProducto)->value('stock');
                $netoExistente = (int) DB::table('inventario_movimientos')->where('id_producto', $idProducto)->sum('cantidad');
                $base = max($stockActual - $netoExistente, 0);
                $vendido = (int) $ventas->sum('cantidad');
                $primera = Carbon::parse($ventas->first()->fecha)->subDay()->startOfDay();
                $userId = $ventas->first()->user_id;

                DB::table('inventario_movimientos')->insert([
                    'id_producto' => $idProducto,
                    'user_id' => $userId,
                    'tipo' => 'entrada',
                    'cantidad' => $vendido + $base,
                    'stock_anterior' => 0,
                    'stock_nuevo' => $vendido + $base,
                    'motivo' => 'Stock inicial reconstruido para el historial de ventas',
                    'fecha_evento' => $primera->toDateString(),
                    'created_at' => $primera,
                    'updated_at' => $primera,
                ]);
                $entradas++;

                $saldo = $vendido + $base;

                foreach ($ventas as $venta) {
                    $cantidad = (int) $venta->cantidad;
                    DB::table('inventario_movimientos')->insert([
                        'id_producto' => $idProducto,
                        'user_id' => $venta->user_id,
                        'tipo' => 'venta',
                        'cantidad' => -$cantidad,
                        'stock_anterior' => $saldo,
                        'stock_nuevo' => $saldo - $cantidad,
                        'motivo' => 'Venta registrada',
                        'fecha_evento' => substr((string) $venta->fecha, 0, 10),
                        'id_venta' => $venta->id_venta,
                        'created_at' => $venta->fecha,
                        'updated_at' => $venta->fecha,
                    ]);
                    $saldo -= $cantidad;
                    $salidas++;
                }
            }
        });

        $this->command?->info("Entradas iniciales creadas: {$entradas}");
        $this->command?->info("Salidas por venta creadas: {$salidas}");
    }
}
