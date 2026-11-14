<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\ReservaInventario;
use App\Models\SolicitudWeb;
use App\Models\Venta;
use App\Services\ResumenInternoVentaPdf;
use App\Services\SeguimientoCotizacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
                    'servicios' => $query->where('tipo_solicitud', 'servicio'),
                    'pendiente_comercial' => $query->where(function ($query): void {
                        $query->where('tipo_solicitud', '!=', 'servicio')
                            ->where(function ($query): void {
                                $query->whereDoesntHave('cotizacion')
                                    ->orWhereHas('cotizacion', fn ($query) => $query->whereIn('estado', ['pendiente_revision', 'borrador', 'vencida']));
                            });
                    }),
                    'esperando_cliente' => $query->where('tipo_solicitud', '!=', 'servicio')
                        ->whereHas('cotizacion', fn ($query) => $query->where('estado', 'enviada')),
                    'cambios_solicitados' => $query->where('tipo_solicitud', '!=', 'servicio')
                        ->whereHas('cotizacion', fn ($query) => $query->where('estado', 'cambios_solicitados')),
                    'aceptada' => $query->where('tipo_solicitud', '!=', 'servicio')
                        ->whereHas('cotizacion', fn ($query) => $query->where('estado', 'aceptada')
                            ->whereDoesntHave('versiones.venta', fn ($query) => $query->where('estado_venta', 'confirmada')->whereNotNull('stock_descontado_at'))),
                    'pendiente_equipo' => $query->where('tipo_solicitud', '!=', 'servicio')
                        ->whereHas('cotizacion.versiones.venta', fn ($query) => $query->where('estado_venta', 'pendiente_confirmacion')),
                    'pendiente_facturacion' => $query->where('tipo_solicitud', '!=', 'servicio')
                        ->whereHas('cotizacion.versiones.venta', fn ($query) => $query->where('estado_venta', 'confirmada')
                            ->where('estado_facturacion', '!=', 'simulada_exitosa')),
                    'pendiente_entrega' => $query->where('tipo_solicitud', '!=', 'servicio')
                        ->whereHas('cotizacion.versiones.venta', fn ($query) => $query->where('estado_facturacion', 'simulada_exitosa')
                            ->where('estado_entrega', '!=', 'entregada')),
                    'completada' => $query->where('tipo_solicitud', '!=', 'servicio')
                        ->whereHas('cotizacion.versiones.venta', fn ($query) => $query->where('estado_venta', 'confirmada')->whereNotNull('stock_descontado_at')),
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

        if (request()->expectsJson()) {
            return response()->json($solicitudes);
        }

        $solicitudes->getCollection()->each(function (SolicitudWeb $solicitud): void {
            $solicitud->setAttribute(
                'cliente_snapshot',
                Cliente::desdeSnapshot($solicitud->snapshotDatosCliente())
            );
        });

        return view('solicitudes.index', compact('solicitudes'));
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
        $productosDisponibles = Producto::query()->where('activo', true)
            ->orderBy('nombre')->get(['id_producto', 'nombre', 'codigo_origen', 'codigo_barra', 'precio']);
        $solicitud->setAttribute('estado_comercial', $this->estadoComercial($solicitud));
        $cliente = Cliente::desdeSnapshot($solicitud->snapshotDatosCliente());

        return view('solicitudes.show', compact('solicitud', 'cliente', 'productos', 'totales', 'productosDisponibles'));
    }

    public function seguimiento($id, SeguimientoCotizacion $seguimiento)
    {
        $solicitud = SolicitudWeb::with([
            'cliente',
            'cotizacion.versiones.ordenesCompra',
            'cotizacion.versiones.venta.creditoAutorizadoPor',
            'cotizacion.versiones.venta.eventosFacturacion',
            'cotizacion.reservas',
            'venta.creditoAutorizadoPor',
            'venta.eventosFacturacion',
        ])->findOrFail($id);
        abort_if($solicitud->tipo_solicitud === 'servicio', 404);

        $estadoComercial = $this->estadoComercial($solicitud);
        $solicitud->setAttribute('estado_comercial', $estadoComercial);
        $datos = $seguimiento->construir($solicitud, $estadoComercial);
        $cliente = Cliente::desdeSnapshot($solicitud->snapshotDatosCliente());

        return view('solicitudes.seguimiento', compact('solicitud', 'datos', 'cliente'));
    }

    public function descargarSolicitudPdf($id, ResumenInternoVentaPdf $resumen)
    {
        $solicitud = SolicitudWeb::with(['cliente', 'venta', 'cotizacion.versiones.venta'])->findOrFail($id);
        $cliente = Cliente::desdeSnapshot($solicitud->snapshotDatosCliente());

        if ($solicitud->tipo_solicitud === 'servicio' && ! $solicitud->venta?->estado_venta) {
            abort_unless(
                $solicitud->estado === 'Entregado y completado',
                403,
                'El resumen interno estará disponible cuando el servicio esté completado.'
            );

            $logo = file_get_contents(public_path('images/resmap sin fondo.png'));

            return Pdf::loadView('solicitudes.factura-pdf', [
                'solicitud' => $solicitud,
                'cliente' => $cliente,
                'logoDataUri' => 'data:image/png;base64,'.base64_encode((string) $logo),
            ])->setPaper('a4')->download('resumen-servicio-'.$solicitud->id_solicitud.'.pdf');
        }

        $venta = $solicitud->cotizacion?->versiones->sortByDesc('numero_version')->first()?->venta
            ?? $solicitud->venta;

        abort_unless(
            $venta !== null && Venta::completadas()->whereKey($venta->id_venta)->exists(),
            403,
            'El resumen interno estará disponible cuando la venta esté completada.'
        );

        return $resumen->descargar($venta);
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

        if ($venta?->estado_venta === 'confirmada' && $venta->stock_descontado_at !== null) {
            return [
                'titulo' => 'Venta completada',
                'detalle' => 'La venta está registrada y el stock fue descontado. Puedes generar un resumen interno.',
                'estado' => 'completada',
            ];
        }

        if ($cotizacion->estado === 'aceptada') {
            return [
                'titulo' => 'Cotización aceptada',
                'detalle' => 'El equipo puede convertirla en venta sin adjuntar una orden de compra.',
                'estado' => 'aceptada',
            ];
        }

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
                'detalle' => 'El equipo debe registrar la respuesta recibida por WhatsApp u otro medio.',
                'estado' => 'esperando_cliente',
            ];
        }

        if ($cotizacion->estado === 'vencida') {
            return [
                'titulo' => 'Cotización vencida',
                'detalle' => 'La vigencia terminó sin respuesta del cliente y se liberó el stock reservado. Crea una nueva versión para reactivarla.',
                'estado' => 'pendiente_comercial',
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

        $cliente = Cliente::findOrFail($request->id_cliente);
        $solicitud = SolicitudWeb::create([
            'fecha' => now(),
            'estado' => 'Pendiente',
            'id_cliente' => $request->id_cliente,
            'datos_cliente' => $cliente->snapshotDatos(),
            'contacto' => $cliente->contacto,
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
            'estado' => 'required|string|in:Pendiente,En proceso,Pagado - pendiente de entrega,Entregado - pago pendiente,Entregado y completado,Rechazado',
            'observaciones' => 'nullable|string|max:2000',
        ]);

        $solicitud = SolicitudWeb::with('cliente')->findOrFail($id);
        if ($solicitud->tipo_solicitud === 'servicio' && $request->input('estado') === 'Entregado y completado') {
            throw ValidationException::withMessages([
                'estado' => 'Registra el servicio con su precio y repuestos para completarlo.',
            ]);
        }
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
        if ($solicitud->tipo_solicitud === 'servicio') {
            $transiciones = [
                'Pendiente' => ['En proceso', 'Rechazado'],
                'En proceso' => ['Pendiente', 'Rechazado'],
                'Pagado - pendiente de entrega' => ['En proceso', 'Rechazado'],
                'Entregado - pago pendiente' => ['En proceso', 'Rechazado'],
                'Entregado y completado' => [],
                'Rechazado' => [],
            ];
        }
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
            DB::transaction(function () use ($request, $solicitud, $estadoNuevo, $transiciones): void {
                if ($solicitud->tipo_solicitud === 'servicio') {
                    $solicitud = SolicitudWeb::query()->whereKey($solicitud->id_solicitud)->lockForUpdate()->firstOrFail();
                    if ($solicitud->venta_id || in_array($solicitud->estado, ['Entregado y completado', 'Rechazado'], true)) {
                        throw ValidationException::withMessages(['estado' => 'El servicio ya está cerrado.']);
                    }
                    if (! in_array($estadoNuevo, $transiciones[$solicitud->estado] ?? [], true)) {
                        throw ValidationException::withMessages(['estado' => 'El estado del servicio cambió. Actualiza la página e inténtalo nuevamente.']);
                    }
                }
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
                    'datos_cliente' => $solicitud->snapshotDatosCliente(),
                    'contacto' => $solicitud->contacto,
                    'cargo' => $solicitud->cargo,
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

    public function registrarServicio(Request $request, int $id): RedirectResponse
    {
        $datos = $request->validate([
            'detalle_servicio' => ['required', 'string', 'max:255'],
            'precio_servicio' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'medio_pago' => ['required', 'in:Efectivo,Transferencia,Tarjeta,Otro'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'repuestos' => ['nullable', 'array', 'max:100'],
            'repuestos.*.id_producto' => ['required', 'integer', 'distinct', 'exists:productos,id_producto'],
            'repuestos.*.cantidad' => ['required', 'integer', 'min:1', 'max:99999'],
        ]);

        DB::transaction(function () use ($request, $id, $datos): void {
            $solicitud = SolicitudWeb::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($solicitud->tipo_solicitud === 'servicio', 404);
            if ($solicitud->venta_id || in_array($solicitud->estado, ['Entregado y completado', 'Rechazado'], true)) {
                throw ValidationException::withMessages(['servicio' => 'El servicio ya está cerrado o registrado.']);
            }

            $repuestos = collect($datos['repuestos'] ?? [])->sortBy('id_producto');
            $productos = Producto::query()
                ->whereIn('id_producto', $repuestos->pluck('id_producto'))
                ->orderBy('id_producto')
                ->lockForUpdate()
                ->get()
                ->keyBy('id_producto');
            if ($productos->count() !== $repuestos->count()) {
                throw ValidationException::withMessages(['repuestos' => 'Uno de los repuestos ya no existe. Actualiza la página e inténtalo nuevamente.']);
            }
            $detalles = [];
            $totalCentavos = (int) round((float) $datos['precio_servicio'] * 100);
            foreach ($repuestos as $repuesto) {
                $producto = $productos->get($repuesto['id_producto']);
                if (! $producto->activo) {
                    throw ValidationException::withMessages(['repuestos' => "El repuesto {$producto->nombre} está inactivo."]);
                }
                $reservado = ReservaInventario::query()
                    ->where('producto_id', $producto->id_producto)
                    ->where('estado', 'activa')
                    ->where('expira_at', '>', now())
                    ->sum('cantidad');
                $disponible = max(0, (int) $producto->stock - (int) $reservado);
                $cantidad = (int) $repuesto['cantidad'];
                if ($cantidad > $disponible) {
                    throw ValidationException::withMessages(['repuestos' => "Stock insuficiente para {$producto->nombre}; hay {$disponible} unidades disponibles."]);
                }
                $subtotalCentavos = (int) round((float) $producto->precio * 100) * $cantidad;
                $totalCentavos += $subtotalCentavos;
                $detalles[] = compact('producto', 'cantidad', 'subtotalCentavos');
            }
            if ($totalCentavos > 9999999999) {
                throw ValidationException::withMessages(['precio_servicio' => 'El total supera el máximo permitido para la venta.']);
            }
            $total = $totalCentavos / 100;
            $neto = round($total / 1.19, 2);
            $venta = Venta::create([
                'fecha' => now(),
                'tipo_documento' => 'Resumen interno',
                'folio_sii' => null,
                'neto' => $neto,
                'iva' => round($total - $neto, 2),
                'total' => $total,
                'medio_pago' => $datos['medio_pago'],
                'estado_sii' => 'Pendiente',
                'estado_venta' => 'confirmada',
                'estado_pago' => 'pagado',
                'estado_facturacion' => 'pendiente',
                'estado_entrega' => 'entregada',
                'monto_pagado' => $total,
                'confirmada_at' => now(),
                'entregada_at' => now(),
                'stock_descontado_at' => now(),
                'user_id' => $request->user()->id,
                'id_cliente' => $solicitud->id_cliente,
                'datos_cliente' => $solicitud->snapshotDatosCliente(),
                'contacto' => $solicitud->contacto,
                'cargo' => $solicitud->cargo,
            ]);
            DetalleVenta::create([
                'id_venta' => $venta->id_venta,
                'id_producto' => null,
                'descripcion' => $datos['detalle_servicio'],
                'cantidad' => 1,
                'precio_unitario' => $datos['precio_servicio'],
                'subtotal' => $datos['precio_servicio'],
            ]);
            foreach ($detalles as $detalle) {
                $producto = $detalle['producto'];
                DetalleVenta::create([
                    'id_venta' => $venta->id_venta,
                    'id_producto' => $producto->id_producto,
                    'descripcion' => $producto->nombre,
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $producto->precio,
                    'subtotal' => $detalle['subtotalCentavos'] / 100,
                ]);
                $stockAnterior = (int) $producto->stock;
                $producto->decrement('stock', $detalle['cantidad']);
                InventarioMovimiento::create([
                    'id_producto' => $producto->id_producto,
                    'user_id' => $request->user()->id,
                    'tipo' => 'venta',
                    'cantidad' => -$detalle['cantidad'],
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $stockAnterior - $detalle['cantidad'],
                    'motivo' => 'Repuesto utilizado en servicio',
                    'fecha_evento' => now(),
                    'id_venta' => $venta->id_venta,
                ]);
            }
            $solicitud->update([
                'venta_id' => $venta->id_venta,
                'estado' => 'Entregado y completado',
                'observaciones' => $datos['observaciones'] ?? null,
                'atendida_at' => now(),
            ]);
        }, attempts: 3);

        return redirect()->route('solicitudes.show', $id)->with('success', 'Servicio y venta registrados correctamente.');
    }
}
