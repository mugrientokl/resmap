<?php

namespace App\Http\Controllers;

use App\Models\DetalleVenta;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\SolicitudWeb;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SolicitudWebController extends Controller
{
    public function index(Request $request)
    {
        $solicitudes = SolicitudWeb::with('cliente')
            ->when($request->filled('estado'), fn ($query) => $query->where('estado', $request->string('estado')->toString()))
            ->when($request->filled('desde'), fn ($query) => $query->whereDate('fecha', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($query) => $query->whereDate('fecha', '<=', $request->date('hasta')))
            ->orderByDesc('id_solicitud')
            ->paginate(20)
            ->withQueryString();

        return request()->expectsJson()
            ? response()->json($solicitudes)
            : view('solicitudes.index', compact('solicitudes'));
    }

    public function show($id)
    {
        $solicitud = SolicitudWeb::with('cliente')->findOrFail($id);
        $productos = $this->productosDeSolicitud($solicitud);
        $totales = $this->totalesDeSolicitud($productos);

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
            'message' => 'Solicitud web registrada con éxito.',
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
                    if ($producto->stock < $cantidad) {
                        throw new \RuntimeException("Stock insuficiente para {$producto->nombre}.");
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
                    'estado_dte' => 'pendiente',
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
