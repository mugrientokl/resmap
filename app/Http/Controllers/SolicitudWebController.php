<?php

namespace App\Http\Controllers;

use App\Models\DetalleVenta;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\ReservaInventario;
use App\Models\SolicitudWeb;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SolicitudWebController extends Controller
{
    public function index(Request $request)
    {
        $solicitudes = SolicitudWeb::with([
            'cliente',
            'cotizacion.versiones.ordenesCompra',
            'cotizacion.versiones.venta.creditoAutorizadoPor',
        ])
            ->when($request->filled('estado'), function ($query) use ($request): void {
                match ($request->string('estado')->toString()) {
                    'pendiente_comercial' => $query->where(function ($query): void {
                        $query->where('tipo_solicitud', '!=', 'servicio')
                            ->where(function ($query): void {
                                $query->whereDoesntHave('cotizacion')
                                    ->orWhereHas('cotizacion', fn ($query) => $query->whereIn('estado', ['pendiente_revision', 'borrador']));
                            });
                    }),
                    'esperando_cliente' => $query->where('tipo_solicitud', '!=', 'servicio')
                        ->whereHas('cotizacion', fn ($query) => $query->where('estado', 'enviada')
                            ->orWhere(fn ($query) => $query->where('estado', 'aceptada')->whereDoesntHave('versiones.ordenesCompra'))),
                    'cambios_solicitados' => $query->where('tipo_solicitud', '!=', 'servicio')
                        ->whereHas('cotizacion', fn ($query) => $query->where('estado', 'cambios_solicitados')),
                    'aceptada' => $query->where('tipo_solicitud', '!=', 'servicio')
                        ->whereHas('cotizacion', fn ($query) => $query->where('estado', 'aceptada')),
                    'pendiente_equipo' => $query->where('tipo_solicitud', '!=', 'servicio')
                        ->whereHas('cotizacion.versiones.venta', fn ($query) => $query->where('estado_venta', 'pendiente_confirmacion')),
                    'pendiente_facturacion' => $query->where('tipo_solicitud', '!=', 'servicio')
                        ->whereHas('cotizacion.versiones.venta', fn ($query) => $query->where('estado_venta', 'confirmada')
                            ->where('estado_facturacion', '!=', 'simulada_exitosa')),
                    'pendiente_entrega' => $query->where('tipo_solicitud', '!=', 'servicio')
                        ->whereHas('cotizacion.versiones.venta', fn ($query) => $query->where('estado_facturacion', 'simulada_exitosa')
                            ->where('estado_entrega', '!=', 'entregada')),
                    'completada' => $query->where('tipo_solicitud', '!=', 'servicio')
                        ->whereHas('cotizacion.versiones.venta', fn ($query) => $query->where('estado_entrega', 'entregada')),
                    'cerrada' => $query->where(function ($query): void {
                        $query->where('tipo_solicitud', '!=', 'servicio')
                            ->where(function ($query): void {
                                $query->where('estado', 'Entregado y completado')
                                    ->orWhereHas('cotizacion', fn ($query) => $query->whereIn('estado', ['rechazada', 'cancelada']));
                            });
                    }),
                    default => $query->where('estado', $request->string('estado')->toString()),
                };
            })
            ->when($request->filled('desde'), fn ($query) => $query->whereDate('fecha', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($query) => $query->whereDate('fecha', '<=', $request->date('hasta')))
            ->orderByDesc('id_solicitud')
            ->paginate(20)
            ->withQueryString();

        $solicitudes->getCollection()->each(function (SolicitudWeb $solicitud): void {
            $solicitud->setAttribute('estado_comercial', $this->estadoComercial($solicitud));
        });

        return request()->expectsJson()
            ? response()->json($solicitudes)
            : view('solicitudes.index', compact('solicitudes'));
    }

    public function show($id)
    {
        $solicitud = SolicitudWeb::with([
            'cliente',
            'cotizacion.versiones.detalles',
            'cotizacion.versiones.ordenesCompra',
            'cotizacion.versiones.venta.creditoAutorizadoPor',
            'cotizacion.reservas',
            'venta.creditoAutorizadoPor',
        ])->findOrFail($id);
        $productos = $this->productosDeSolicitud($solicitud);
        $totales = $this->totalesDeSolicitud($productos);
        $solicitud->setAttribute('estado_comercial', $this->estadoComercial($solicitud));

        return view('solicitudes.show', compact('solicitud', 'productos', 'totales'));
    }

    public function descargarSolicitudPdf($id)
    {
        $solicitud = SolicitudWeb::with('cliente')->findOrFail($id);
        $productos = $this->productosDeSolicitud($solicitud);
        $totales = $this->totalesDeSolicitud($productos);

        return Pdf::loadView('solicitudes.factura-pdf', compact('solicitud', 'productos', 'totales'))
            ->setPaper('a4')
            ->download('resumen-solicitud-'.$solicitud->id_solicitud.'.pdf');
    }

    private function productosDeSolicitud(SolicitudWeb $solicitud)
    {
        return collect($solicitud->detalles_productos)->map(function (array $detalle) {
            $producto = Producto::find($detalle['id_producto']);
            $cantidad = (int) $detalle['cantidad'];
            $precioIva = (float) ($producto?->precio ?? 0);
            $precioNeto = round($precioIva / 1.19);

            return [
                'producto' => $producto,
                'cantidad' => $cantidad,
                'precio_neto' => $precioNeto,
                'precio_iva' => $precioIva,
                'total_neto' => $precioNeto * $cantidad,
                'total_iva' => $precioIva * $cantidad,
            ];
        });
    }

    private function totalesDeSolicitud($productos): array
    {
        $neto = (float) $productos->sum('total_neto');
        $total = (float) $productos->sum('total_iva');
        $iva = $total - $neto;

        return [
            'neto' => $neto,
            'iva' => $iva,
            'total' => $total,
        ];
    }

    /**
     * @return array{titulo: string, detalle: string, estado: string}
     */
    private function estadoComercial(SolicitudWeb $solicitud): array
    {
        if ($solicitud->tipo_solicitud === 'servicio') {
            return [
                'titulo' => 'Servicio: '.$solicitud->estado,
                'detalle' => 'Solicitud de atención técnica; se gestiona desde el equipo de servicio.',
                'estado' => 'servicio',
            ];
        }

        $cotizacion = $solicitud->cotizacion;
        if (! $cotizacion) {
            return $solicitud->estado === 'Rechazado'
                ? [
                    'titulo' => 'Cotización cerrada',
                    'detalle' => 'Esta solicitud comercial fue rechazada antes de generar una cotización.',
                    'estado' => 'cerrada',
                ]
                : [
                    'titulo' => 'Pendiente de revisión comercial',
                    'detalle' => 'Esperando que un vendedor revise disponibilidad, prepare y envíe la cotización al cliente.',
                    'estado' => 'pendiente_comercial',
                ];
        }

        $version = $cotizacion->versiones->sortByDesc('numero_version')->first();
        $venta = $version?->venta ?? $solicitud->venta;
        $tieneOrdenCompra = $version?->ordenesCompra->isNotEmpty() ?? false;

        if ($cotizacion->estado === 'pendiente_revision') {
            return [
                'titulo' => 'Pendiente de revisión comercial',
                'detalle' => 'Esperando que un vendedor prepare la cotización para enviarla al cliente.',
                'estado' => 'pendiente_comercial',
            ];
        }

        if ($cotizacion->estado === 'borrador' || $version?->estado === 'borrador') {
            return [
                'titulo' => 'Cotización lista para enviar',
                'detalle' => 'El equipo comercial debe enviar esta versión al cliente.',
                'estado' => 'pendiente_comercial',
            ];
        }

        if ($cotizacion->estado === 'enviada') {
            return [
                'titulo' => 'Esperando respuesta del cliente',
                'detalle' => 'Pendiente de aceptación o solicitud de cambios y de la orden de compra del cliente.',
                'estado' => 'esperando_cliente',
            ];
        }

        if ($cotizacion->estado === 'cambios_solicitados') {
            return [
                'titulo' => 'Cambios solicitados por el cliente',
                'detalle' => 'El equipo comercial debe revisar los comentarios y preparar una nueva versión.',
                'estado' => 'cambios_solicitados',
            ];
        }

        if (in_array($cotizacion->estado, ['rechazada', 'cancelada'], true)) {
            return [
                'titulo' => $cotizacion->estado === 'rechazada' ? 'Cotización rechazada' : 'Cotización cancelada',
                'detalle' => 'Este proceso comercial está cerrado.',
                'estado' => 'cerrada',
            ];
        }

        if ($cotizacion->estado === 'aceptada' && ! $tieneOrdenCompra) {
            return [
                'titulo' => 'Aceptada; falta la orden de compra',
                'detalle' => 'Esperando que el cliente adjunte o autorice una orden de compra antes de continuar con la venta.',
                'estado' => 'esperando_cliente',
            ];
        }

        if ($venta?->estado_venta === 'pendiente_confirmacion') {
            return [
                'titulo' => 'Orden de compra recibida; pendiente del equipo comercial',
                'detalle' => 'El equipo debe confirmar el pago total o autorizar crédito para continuar.',
                'estado' => 'pendiente_equipo',
            ];
        }

        if ($venta?->estado_venta === 'confirmada' && $venta->estado_facturacion !== 'simulada_exitosa') {
            $detalle = $venta->estado_pago === 'credito_autorizado'
                ? 'Crédito autorizado por '.$venta->creditoAutorizadoPor?->name.'; pendiente de facturación de prueba.'
                : ($venta->estado_pago === 'credito_parcial'
                    ? 'Abono de crédito registrado; el saldo sigue pendiente mientras se prepara la facturación de prueba.'
                    : 'La venta está confirmada. El inventario se descontará al completar la facturación.');

            return [
                'titulo' => $venta->estado_pago === 'credito_autorizado'
                    ? 'Crédito autorizado; pendiente de facturación'
                    : ($venta->estado_pago === 'credito_parcial'
                        ? 'Crédito con abonos; pendiente de facturación'
                        : 'Pago confirmado; pendiente de facturación'),
                'detalle' => $detalle.' El inventario se descontará al completar la facturación.',
                'estado' => 'pendiente_facturacion',
            ];
        }

        if ($venta?->estado_facturacion === 'simulada_exitosa' && $venta->estado_entrega !== 'entregada') {
            return [
                'titulo' => 'Facturación simulada; pendiente de entrega',
                'detalle' => 'El stock ya fue descontado. Coordina el despacho o retiro con el cliente.',
                'estado' => 'pendiente_entrega',
            ];
        }

        if ($venta?->estado_entrega === 'entregada') {
            return [
                'titulo' => 'Venta completada',
                'detalle' => 'La venta fue facturada en modo de prueba y marcada como entregada.',
                'estado' => 'completada',
            ];
        }

        return [
            'titulo' => 'Cotización aceptada; venta en preparación',
            'detalle' => 'Revisa la orden de compra del cliente para continuar con la venta.',
            'estado' => 'pendiente_equipo',
        ];
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_cliente' => 'required|exists:clientes,id_cliente',
            'detalles_productos' => 'required|array|min:1',
        ]);

        $solicitud = SolicitudWeb::create([
            'fecha' => now(),
            'estado' => 'Pendiente',
            'id_cliente' => $request->id_cliente,
            'detalles_productos' => $request->detalles_productos,
        ]);

        return response()->json([
            'message' => 'Cotización web registrada con éxito.',
            'solicitud' => $solicitud,
        ], 201);
    }

    public function actualizarEstado(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|string|in:Pendiente,Pagado - pendiente de entrega,Entregado - pago pendiente,Entregado y completado,Rechazado',
            'observaciones' => 'nullable|string|max:2000',
        ]);

        $solicitud = SolicitudWeb::with('cliente')->findOrFail($id);
        if ($solicitud->tipo_solicitud !== 'servicio' && $solicitud->cotizacion()->exists()) {
            $mensaje = 'Las cotizaciones de repuestos se gestionan desde el flujo comercial de cotizaciones.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $mensaje], 422);
            }

            return redirect()->route('solicitudes.show', $solicitud)->with('error', $mensaje);
        }
        $transiciones = [
            'Pendiente' => ['Pagado - pendiente de entrega', 'Entregado - pago pendiente', 'Entregado y completado', 'Rechazado'],
            'Pagado - pendiente de entrega' => ['Entregado y completado'],
            'Entregado - pago pendiente' => ['Entregado y completado'],
            'Entregado y completado' => [],
            'Rechazado' => [],
        ];
        $estadoNuevo = $request->string('estado')->toString();

        if (! in_array($estadoNuevo, $transiciones[$solicitud->estado] ?? [], true)) {
            $mensaje = $solicitud->estado === 'Rechazado' || $solicitud->estado === 'Entregado'
                ? 'La solicitud ya está cerrada y no admite más cambios.'
                : "No se puede cambiar de {$solicitud->estado} a {$estadoNuevo}.";

            if ($request->expectsJson()) {
                return response()->json(['message' => $mensaje], 422);
            }

            return redirect()->route('solicitudes.show', $solicitud)->withInput()->with('error', $mensaje);
        }

        try {
            DB::transaction(function () use ($request, $solicitud, $estadoNuevo): void {
                $solicitud->update([
                    'estado' => $estadoNuevo,
                    'observaciones' => $request->observaciones,
                    'atendida_at' => in_array($estadoNuevo, ['Entregado - pago pendiente', 'Entregado y completado'], true)
                        ? now()
                        : $solicitud->atendida_at,
                ]);

                if ($estadoNuevo !== 'Entregado y completado' || $solicitud->venta_id) {
                    return;
                }

                $items = collect($solicitud->detalles_productos ?? [])
                    ->groupBy('id_producto')
                    ->map(fn ($detalles): int => $detalles->sum(fn (array $detalle): int => (int) $detalle['cantidad']));
                $total = 0;
                $detallesVenta = [];

                foreach ($items as $idProducto => $cantidad) {
                    $producto = Producto::query()->whereKey($idProducto)->lockForUpdate()->firstOrFail();
                    $reservado = ReservaInventario::query()
                        ->where('producto_id', $producto->id_producto)
                        ->where('estado', 'activa')
                        ->where('expira_at', '>', now())
                        ->sum('cantidad');
                    $disponible = max(0, (int) $producto->stock - (int) $reservado);
                    if ($disponible < $cantidad) {
                        throw new \RuntimeException("Stock disponible insuficiente para {$producto->nombre}; hay {$disponible} unidades libres.");
                    }

                    $subtotal = (float) $producto->precio * $cantidad;
                    $total += $subtotal;
                    $detallesVenta[] = compact('producto', 'cantidad', 'subtotal');
                }

                $neto = round($total / 1.19, 2);
                $venta = Venta::create([
                    'fecha' => now(),
                    'tipo_documento' => 'Boleta Electrónica',
                    'folio_sii' => null,
                    'neto' => $neto,
                    'iva' => round($total - $neto, 2),
                    'total' => $total,
                    'medio_pago' => 'Pendiente de emisión',
                    'estado_sii' => 'Pendiente',
                    'user_id' => $request->user()->id,
                    'id_cliente' => $solicitud->id_cliente,
                ]);

                foreach ($detallesVenta as $detalle) {
                    DetalleVenta::create([
                        'id_venta' => $venta->id_venta,
                        'id_producto' => $detalle['producto']->id_producto,
                        'cantidad' => $detalle['cantidad'],
                        'precio_unitario' => $detalle['producto']->precio,
                        'subtotal' => $detalle['subtotal'],
                    ]);

                    $stockAnterior = $detalle['producto']->stock;
                    $detalle['producto']->decrement('stock', $detalle['cantidad']);
                    InventarioMovimiento::create([
                        'id_producto' => $detalle['producto']->id_producto,
                        'user_id' => $request->user()->id,
                        'tipo' => 'venta',
                        'cantidad' => -$detalle['cantidad'],
                        'stock_anterior' => $stockAnterior,
                        'stock_nuevo' => $stockAnterior - $detalle['cantidad'],
                        'motivo' => 'Venta generada al completar solicitud',
                        'id_venta' => $venta->id_venta,
                    ]);
                }

                $solicitud->update(['venta_id' => $venta->id_venta]);
            });
        } catch (\RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return redirect()->route('solicitudes.show', $solicitud)->withInput()->with('error', $exception->getMessage());
        }

        if (! $request->expectsJson()) {
            return redirect()->route('solicitudes.show', $solicitud)->with('success', 'Estado de la solicitud actualizado.');
        }

        return response()->json([
            'message' => 'Estado de la solicitud actualizado.',
            'solicitud' => $solicitud,
        ]);
    }
}
