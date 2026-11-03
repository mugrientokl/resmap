<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\CotizacionVersion;
use App\Models\OrdenCompra;
use App\Models\SolicitudWeb;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SeguimientoCotizacion
{
    /**
     * @param  array{titulo: string, detalle: string, estado: string}  $estadoComercial
     * @return array{
     *     etapas: list<array{titulo: string, estado: string}>,
     *     eventos: list<array{fecha: ?Carbon, titulo: string, detalle: ?string, tipo: string}>,
     *     espera: array{responsable: string, titulo: string, detalle: string},
     *     cerrada: bool
     * }
     */
    public function construir(SolicitudWeb $solicitud, array $estadoComercial): array
    {
        $cotizacion = $solicitud->cotizacion;
        $versiones = $cotizacion?->versiones->sortBy('numero_version')->values() ?? collect();
        $ultima = $versiones->last();
        $ventas = $versiones->map(fn (CotizacionVersion $version): ?Venta => $version->venta)->filter()->values();
        if ($ventas->isEmpty() && $solicitud->venta) {
            $ventas = collect([$solicitud->venta]);
        }
        $venta = $ventas->last();
        $ordenes = $versiones->flatMap(fn (CotizacionVersion $version) => $version->ordenesCompra)->values();
        $cerrada = $cotizacion && in_array($cotizacion->estado, ['rechazada', 'cancelada'], true);

        $usuarios = User::query()
            ->whereIn('id', $this->idsDeUsuarios($versiones, $ordenes, $ventas, $cotizacion))
            ->pluck('name', 'id');

        return [
            'etapas' => $this->etapas($cotizacion, $ultima, $ordenes, $venta, $cerrada),
            'eventos' => $this->eventos($solicitud, $cotizacion, $versiones, $ordenes, $ventas, $usuarios),
            'espera' => $this->espera($estadoComercial),
            'cerrada' => $cerrada,
        ];
    }

    /**
     * @return list<array{titulo: string, estado: string}>
     */
    private function etapas(?Cotizacion $cotizacion, ?CotizacionVersion $ultima, Collection $ordenes, ?Venta $venta, bool $cerrada): array
    {
        $completadas = [
            'Solicitud recibida' => true,
            'Cotización enviada' => $ultima?->enviada_at !== null && ! in_array($cotizacion?->estado, ['cambios_solicitados', 'vencida'], true),
            'Aceptada por el cliente' => $cotizacion?->estado === 'aceptada' || $ordenes->isNotEmpty() || $venta !== null,
            'Orden de compra' => $ordenes->isNotEmpty(),
            'Pago o crédito confirmado' => $venta?->estado_venta === 'confirmada',
            'Facturación' => $venta?->estado_facturacion === 'simulada_exitosa',
            'Entrega' => $venta?->estado_entrega === 'entregada',
        ];

        $etapas = [];
        $actualAsignada = false;
        foreach ($completadas as $titulo => $completa) {
            if ($completa) {
                $estado = 'completada';
            } elseif ($cerrada) {
                $estado = $titulo === 'Aceptada por el cliente' && $cotizacion?->estado === 'rechazada' ? 'rechazada' : 'omitida';
            } elseif (! $actualAsignada) {
                $estado = 'actual';
                $actualAsignada = true;
            } else {
                $estado = 'pendiente';
            }
            $etapas[] = ['titulo' => $titulo, 'estado' => $estado];
        }

        return $etapas;
    }

    /**
     * @param  Collection<int, CotizacionVersion>  $versiones
     * @param  Collection<int, OrdenCompra>  $ordenes
     * @param  Collection<int, Venta>  $ventas
     * @param  Collection<int|string, string>  $usuarios
     * @return list<array{fecha: ?Carbon, titulo: string, detalle: ?string, tipo: string}>
     */
    private function eventos(SolicitudWeb $solicitud, ?Cotizacion $cotizacion, Collection $versiones, Collection $ordenes, Collection $ventas, Collection $usuarios): array
    {
        $eventos = [];
        $agregar = function (?Carbon $fecha, string $titulo, ?string $detalle = null, string $tipo = 'info') use (&$eventos): void {
            $eventos[] = compact('fecha', 'titulo', 'detalle', 'tipo');
        };
        $pesos = fn ($monto): string => '$ '.number_format((float) $monto, 0, ',', '.');
        $nombre = fn (?int $id): ?string => $id ? ($usuarios[$id] ?? null) : null;

        $unidades = collect($solicitud->detalles_productos ?? [])->sum(fn (array $detalle): int => (int) ($detalle['cantidad'] ?? 0));
        $agregar(
            $solicitud->fecha,
            'Solicitud recibida',
            $solicitud->cliente->nombre.' solicitó '.$unidades.' unidad(es)'
                .($solicitud->metodo_entrega ? ' con '.($solicitud->metodo_entrega === 'despacho' ? 'despacho' : 'retiro en tienda') : '').'.'
        );

        $primeraReserva = $cotizacion?->reservas->sortBy('created_at')->first();
        if ($primeraReserva) {
            $agregar($primeraReserva->created_at, 'Stock reservado', 'Las unidades quedaron apartadas para esta cotización y no están disponibles para otras ventas.');
        }

        foreach ($versiones as $version) {
            $numero = $version->numero_version;
            $responsable = $nombre($version->revisada_por);
            $agregar(
                $version->created_at,
                "Versión {$numero} creada",
                'Total '.$pesos($version->total).($responsable ? ' · preparada por '.$responsable : '').' · válida hasta '.$version->vigente_hasta->format('d/m/Y H:i').'.'
            );

            if ($version->enviada_at) {
                $canales = collect([
                    $version->email_estado ? 'correo: '.str_replace('_', ' ', $version->email_estado) : null,
                    $version->whatsapp_estado ? 'WhatsApp: '.str_replace('_', ' ', $version->whatsapp_estado) : null,
                ])->filter()->implode(' · ');
                $agregar($version->enviada_at, "Versión {$numero} enviada al cliente", $canales ?: null);
            }
            if ($version->aceptada_at) {
                $agregar($version->aceptada_at, "Cliente aceptó la versión {$numero}", 'Respuesta registrada desde el enlace seguro de la cotización.', 'exito');
            }
            if ($version->rechazada_at) {
                $agregar($version->rechazada_at, "Cliente rechazó la versión {$numero}", $cotizacion?->comentarios_cliente, 'error');
            }
            if ($version->cambios_solicitados_at) {
                $agregar(
                    $version->cambios_solicitados_at,
                    "Cliente solicitó cambios en la versión {$numero}",
                    $version->is($versiones->last()) ? $cotizacion?->comentarios_cliente : null,
                    'alerta'
                );
            }
            if ($version->estado === 'vencida') {
                $agregar($version->vigente_hasta, "Versión {$numero} venció sin respuesta", 'Se liberó el stock reservado. Se puede crear una nueva versión.', 'alerta');
            }
        }

        foreach ($ordenes as $orden) {
            $agregar(
                $orden->autorizada_at ?? $orden->created_at,
                'Orden de compra recibida',
                $orden->modalidad === 'cliente'
                    ? 'Enviada por el cliente'.($orden->numero ? ' (N° '.$orden->numero.')' : '').'.'
                    : 'El cliente autorizó generar la orden de compra con esta cotización.',
                'exito'
            );
        }

        foreach ($ventas as $venta) {
            $agregar($venta->created_at, 'Venta generada', 'Total '.$pesos($venta->total).'; pendiente de confirmación del equipo.');

            if ($venta->estado_venta === 'confirmada') {
                $esCredito = $venta->credito_autorizado_at !== null;
                $detalle = $esCredito
                    ? 'Crédito autorizado por '.($venta->creditoAutorizadoPor?->name ?? 'un administrador')
                    .'. Pagado '.$pesos($venta->monto_pagado ?? 0).' de '.$pesos($venta->total)
                    .'; saldo '.$pesos((float) $venta->total - (float) ($venta->monto_pagado ?? 0)).'.'
                    : 'Medio de pago: '.$venta->medio_pago.' · '.$pesos($venta->monto_pagado ?? $venta->total).'.';
                $agregar($venta->confirmada_at ?? $venta->credito_autorizado_at, $esCredito ? 'Crédito autorizado' : 'Pago confirmado', $detalle, 'exito');
            }

            foreach ($venta->eventosFacturacion->sortBy('created_at') as $evento) {
                $exitoso = $evento->resultado === 'exitoso';
                $agregar(
                    $evento->created_at,
                    $exitoso ? 'Facturación de prueba exitosa' : 'Facturación de prueba fallida',
                    $evento->respuesta['mensaje'] ?? null,
                    $exitoso ? 'exito' : 'error'
                );
            }

            if ($venta->stock_descontado_at) {
                $agregar($venta->stock_descontado_at, 'Stock descontado del inventario', 'Las unidades reservadas se consumieron al facturar.', 'exito');
            }
            if ($venta->estado_entrega === 'entregada') {
                $agregar($venta->entregada_at, 'Entrega registrada', $venta->metodo_entrega === 'despacho' ? 'Despacho entregado al cliente.' : 'Retiro realizado por el cliente.', 'exito');
            }
        }

        usort($eventos, function (array $a, array $b): int {
            if ($a['fecha'] === null || $b['fecha'] === null) {
                return $a['fecha'] === null ? ($b['fecha'] === null ? 0 : 1) : -1;
            }

            return $a['fecha']->getTimestamp() <=> $b['fecha']->getTimestamp();
        });

        return $eventos;
    }

    /**
     * @param  array{titulo: string, detalle: string, estado: string}  $estadoComercial
     * @return array{responsable: string, titulo: string, detalle: string}
     */
    private function espera(array $estadoComercial): array
    {
        $responsable = match ($estadoComercial['estado']) {
            'esperando_cliente' => 'Cliente',
            'completada', 'cerrada' => 'Nadie; proceso finalizado',
            default => 'Equipo RESMAP',
        };

        return [
            'responsable' => $responsable,
            'titulo' => $estadoComercial['titulo'],
            'detalle' => $estadoComercial['detalle'],
        ];
    }

    /**
     * @return list<int>
     */
    private function idsDeUsuarios(Collection $versiones, Collection $ordenes, Collection $ventas, ?Cotizacion $cotizacion): array
    {
        return collect([$cotizacion?->creada_por])
            ->merge($versiones->pluck('revisada_por'))
            ->merge($ordenes->pluck('autorizada_por'))
            ->merge($ventas->pluck('credito_autorizado_por'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
