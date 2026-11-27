<?php

namespace App\Services;

use App\Models\EventoReserva;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\ReservaInventario;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentaCotizacionService
{
    public function confirmarPagoTotal(Venta $venta, string $medioPago): Venta
    {
        return DB::transaction(function () use ($venta, $medioPago): Venta {
            $venta = Venta::query()->whereKey($venta->id_venta)->lockForUpdate()->firstOrFail();
            if ($venta->cotizacion_version_id === null || $venta->estado_venta !== 'confirmada' || $venta->stock_descontado_at === null) {
                throw ValidationException::withMessages(['venta' => 'Solo se puede confirmar el pago de una venta de cotización ya registrada.']);
            }
            if ($venta->estado_pago === 'pagado') {
                return $venta;
            }

            $venta->update([
                'estado_pago' => 'pagado',
                'monto_pagado' => $venta->total,
                'medio_pago' => $medioPago,
                'pago_confirmado_at' => now(),
            ]);

            return $venta;
        }, attempts: 3);
    }

    public function completar(Venta $venta, int $usuarioId): Venta
    {
        return DB::transaction(function () use ($venta, $usuarioId): Venta {
            $venta = Venta::query()->whereKey($venta->id_venta)->lockForUpdate()->firstOrFail();
            if ($venta->cotizacion_version_id === null) {
                throw ValidationException::withMessages(['venta' => 'La venta no proviene de una cotización.']);
            }

            if ($venta->stock_descontado_at !== null) {
                return $venta;
            }
            $venta->loadMissing(['cotizacionVersion.cotizacion', 'detalles']);
            $cotizacion = $venta->cotizacionVersion->cotizacion;
            if ($venta->detalles->isEmpty() || $venta->detalles->contains(fn ($detalle): bool => $detalle->id_producto === null || (int) $detalle->cantidad <= 0)) {
                throw ValidationException::withMessages(['venta' => 'La venta no tiene productos y cantidades válidas.']);
            }
            $cantidades = $venta->detalles->groupBy('id_producto')
                ->map(fn ($detalles): int => (int) $detalles->sum('cantidad'))->sortKeys();
            $movimientos = InventarioMovimiento::query()->where('id_venta', $venta->id_venta)
                ->where('tipo', 'venta')->lockForUpdate()->get();
            if ($movimientos->isNotEmpty()) {
                $cantidadesMovidas = $movimientos->groupBy('id_producto')
                    ->map(fn ($items): int => -(int) $items->sum('cantidad'))->sortKeys();
                $movimientosValidos = $movimientos->every(fn (InventarioMovimiento $movimiento): bool => (int) $movimiento->cantidad < 0
                    && (int) $movimiento->stock_anterior + (int) $movimiento->cantidad === (int) $movimiento->stock_nuevo);
                if (! $movimientosValidos || $cantidades->all() !== $cantidadesMovidas->all()) {
                    throw ValidationException::withMessages(['venta' => 'Los movimientos históricos no coinciden con la venta y requieren revisión manual.']);
                }
                app(InventarioReservaService::class)->liberar($cotizacion, 'venta_ya_descontada', $usuarioId);
                $venta->update([
                    'estado_venta' => 'confirmada',
                    'confirmada_at' => $venta->confirmada_at ?? now(),
                    'stock_descontado_at' => $movimientos->max('created_at') ?? now(),
                ]);

                return $venta;
            }

            foreach ($cantidades as $productoId => $cantidad) {
                $producto = Producto::query()->whereKey($productoId)->lockForUpdate()->first();
                if (! $producto) {
                    throw ValidationException::withMessages(['stock' => "El producto #{$productoId} ya no existe."]);
                }
                $otrasReservas = ReservaInventario::query()
                    ->where('producto_id', $productoId)
                    ->where('cotizacion_id', '!=', $cotizacion->id_cotizacion)
                    ->where('estado', 'activa')
                    ->where('expira_at', '>', now())
                    ->sum('cantidad');
                if ((int) $producto->stock - (int) $otrasReservas < $cantidad) {
                    throw ValidationException::withMessages(['stock' => "No hay stock disponible suficiente para {$producto->nombre}."]);
                }

                $stockAnterior = (int) $producto->stock;
                $producto->decrement('stock', $cantidad);
                InventarioMovimiento::create([
                    'id_producto' => $producto->id_producto,
                    'user_id' => $usuarioId,
                    'tipo' => 'venta',
                    'cantidad' => -$cantidad,
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $stockAnterior - $cantidad,
                    'motivo' => 'Salida por venta de cotización',
                    'fecha_evento' => now(),
                    'id_venta' => $venta->id_venta,
                ]);

                $pendiente = $cantidad;
                $reservas = ReservaInventario::query()
                    ->where('cotizacion_id', $cotizacion->id_cotizacion)
                    ->where('producto_id', $productoId)
                    ->where('estado', 'activa')
                    ->where('expira_at', '>', now())
                    ->orderBy('id_reserva')
                    ->lockForUpdate()
                    ->get();
                foreach ($reservas as $reserva) {
                    if ($pendiente === 0) {
                        break;
                    }
                    $consumida = min($pendiente, (int) $reserva->cantidad);
                    if ($consumida === (int) $reserva->cantidad) {
                        $reserva->update(['estado' => 'consumida', 'consumida_at' => now()]);
                        $registro = $reserva;
                    } else {
                        $reserva->decrement('cantidad', $consumida);
                        $registro = ReservaInventario::create([
                            'cotizacion_id' => $reserva->cotizacion_id,
                            'version_id' => $reserva->version_id,
                            'producto_id' => $reserva->producto_id,
                            'cantidad' => $consumida,
                            'estado' => 'consumida',
                            'expira_at' => $reserva->expira_at,
                            'consumida_at' => now(),
                        ]);
                    }
                    EventoReserva::create([
                        'reserva_id' => $registro->id_reserva,
                        'user_id' => $usuarioId,
                        'evento' => 'consumida',
                        'expiracion_anterior' => $reserva->expira_at,
                    ]);
                    $pendiente -= $consumida;
                }
            }

            app(InventarioReservaService::class)->liberar($cotizacion, 'venta_completada', $usuarioId);
            $venta->update([
                'estado_venta' => 'confirmada',
                'confirmada_at' => $venta->confirmada_at ?? now(),
                'stock_descontado_at' => now(),
            ]);

            return $venta;
        }, attempts: 3);
    }
}
