<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\EventoReserva;
use App\Models\Producto;
use App\Models\ReservaInventario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioReservaService
{
    /**
     * @param  array<int|string, int>  $cantidades
     * @return Collection<int, ReservaInventario>
     */
    public function reservar(Cotizacion $cotizacion, array $cantidades, ?int $versionId = null): Collection
    {
        return DB::transaction(function () use ($cotizacion, $cantidades, $versionId): Collection {
            $reservas = new Collection;
            $expiraAt = now()->addHours(48);
            $cantidadesAgrupadas = collect($cantidades)
                ->mapWithKeys(fn (int $cantidad, int|string $productoId): array => [(int) $productoId => $cantidad])
                ->sortKeys();

            foreach ($cantidadesAgrupadas as $productoId => $cantidad) {
                $producto = Producto::query()->whereKey($productoId)->lockForUpdate()->firstOrFail();
                $reservado = ReservaInventario::query()
                    ->where('producto_id', $productoId)
                    ->where('estado', 'activa')
                    ->where('expira_at', '>', now())
                    ->sum('cantidad');
                $disponible = max(0, (int) $producto->stock - (int) $reservado);

                if ($cantidad > $disponible) {
                    throw ValidationException::withMessages([
                        'detalles_productos' => "Stock insuficiente para {$producto->nombre}: solicitaste {$cantidad} y quedan {$disponible} disponibles.",
                    ]);
                }

                $reserva = ReservaInventario::create([
                    'cotizacion_id' => $cotizacion->id_cotizacion,
                    'version_id' => $versionId,
                    'producto_id' => $productoId,
                    'cantidad' => $cantidad,
                    'estado' => 'activa',
                    'expira_at' => $expiraAt,
                ]);
                EventoReserva::create([
                    'reserva_id' => $reserva->id_reserva,
                    'evento' => 'creada',
                    'expiracion_nueva' => $expiraAt,
                ]);
                $reservas->push($reserva);
            }

            return $reservas;
        });
    }

    public function disponible(Producto $producto): int
    {
        $reservado = ReservaInventario::query()
            ->where('producto_id', $producto->id_producto)
            ->where('estado', 'activa')
            ->where('expira_at', '>', now())
            ->sum('cantidad');

        return max(0, (int) $producto->stock - (int) $reservado);
    }

    public function renovar(ReservaInventario $reserva, ?int $userId = null): ReservaInventario
    {
        return DB::transaction(function () use ($reserva, $userId): ReservaInventario {
            $reserva = ReservaInventario::query()->whereKey($reserva->id_reserva)->lockForUpdate()->firstOrFail();

            if ($reserva->estado !== 'activa') {
                throw ValidationException::withMessages(['reserva' => 'Solo se pueden renovar reservas activas.']);
            }

            $expiracionAnterior = $reserva->expira_at;
            $expiracionNueva = now()->addHours(48);
            $reserva->update(['expira_at' => $expiracionNueva]);
            EventoReserva::create([
                'reserva_id' => $reserva->id_reserva,
                'user_id' => $userId,
                'evento' => 'renovada',
                'expiracion_anterior' => $expiracionAnterior,
                'expiracion_nueva' => $expiracionNueva,
            ]);

            return $reserva->refresh();
        });
    }

    public function liberar(Cotizacion $cotizacion, string $motivo, ?int $userId = null): int
    {
        return DB::transaction(function () use ($cotizacion, $motivo, $userId): int {
            $reservas = ReservaInventario::query()
                ->where('cotizacion_id', $cotizacion->id_cotizacion)
                ->where('estado', 'activa')
                ->orderBy('id_reserva')
                ->lockForUpdate()
                ->get();

            foreach ($reservas as $reserva) {
                $expiracionAnterior = $reserva->expira_at;
                $reserva->update([
                    'estado' => 'liberada',
                    'liberada_at' => now(),
                ]);
                EventoReserva::create([
                    'reserva_id' => $reserva->id_reserva,
                    'user_id' => $userId,
                    'evento' => 'liberada',
                    'expiracion_anterior' => $expiracionAnterior,
                    'datos' => ['motivo' => $motivo],
                ]);
            }

            return $reservas->count();
        });
    }

    public function liberarVencidas(): int
    {
        $liberadas = 0;
        ReservaInventario::query()
            ->where('estado', 'activa')
            ->where('expira_at', '<=', now())
            ->orderBy('id_reserva')
            ->chunkById(100, function (Collection $reservas) use (&$liberadas): void {
                foreach ($reservas as $reservaSinBloqueo) {
                    DB::transaction(function () use ($reservaSinBloqueo, &$liberadas): void {
                        $reserva = ReservaInventario::query()
                            ->whereKey($reservaSinBloqueo->id_reserva)
                            ->lockForUpdate()
                            ->first();

                        if (! $reserva || $reserva->estado !== 'activa' || $reserva->expira_at->isFuture()) {
                            return;
                        }

                        $reserva->update([
                            'estado' => 'vencida',
                            'liberada_at' => now(),
                        ]);
                        EventoReserva::create([
                            'reserva_id' => $reserva->id_reserva,
                            'evento' => 'vencida',
                            'expiracion_anterior' => $reserva->expira_at,
                        ]);
                        $liberadas++;
                    });
                }
            }, 'id_reserva');

        return $liberadas;
    }
}
