<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Notifications\ProductoStockCritico;
use App\Rules\RutChileno;
use App\Services\Facturacion\LibreDteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VentaController extends Controller
{
    public function store(Request $request, LibreDteService $libreDteService): JsonResponse
    {
        $request->validate([
            'tipo_documento' => 'required|string|in:Boleta Electrónica,Factura Electrónica',
            'medio_pago' => 'required|string|in:Efectivo,Transferencia,Tarjeta',
            'rut' => ['required', 'string', 'max:20', new RutChileno],
            'nombre_cliente' => 'required|string|max:255',
            'razon_social_cliente' => 'nullable|string|max:255',
            'giro_cliente' => 'nullable|string|max:255',
            'correo_cliente' => 'nullable|email|max:255',
            'telefono_cliente' => 'nullable|string|max:50',
            'direccion_cliente' => 'nullable|string|max:255',
            'region_cliente' => 'nullable|string|max:100',
            'comuna_cliente' => 'nullable|string|max:100',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_producto' => 'required|exists:productos,id_producto',
            'detalles.*.cantidad' => 'required|integer|min:1|max:2147483647',
        ]);

        try {
            $resultado = DB::transaction(function () use ($request) {
                $cliente = Cliente::firstOrCreate(
                    ['rut' => $request->rut],
                    [
                        'nombre' => $request->nombre_cliente,
                        'razon_social' => $request->razon_social_cliente,
                        'giro' => $request->giro_cliente,
                        'correo' => $request->correo_cliente,
                        'telefono' => $request->telefono_cliente,
                        'direccion' => $request->direccion_cliente,
                        'region' => $request->region_cliente,
                        'comuna' => $request->comuna_cliente,
                        'ciudad' => $request->comuna_cliente,
                    ]
                );

                $cliente->update([
                    'nombre' => $request->nombre_cliente,
                    'razon_social' => $request->razon_social_cliente ?? $cliente->razon_social,
                    'giro' => $request->giro_cliente ?? $cliente->giro,
                    'correo' => $request->correo_cliente ?? $cliente->correo,
                    'telefono' => $request->telefono_cliente ?? $cliente->telefono,
                    'direccion' => $request->direccion_cliente ?? $cliente->direccion,
                    'region' => $request->region_cliente ?? $cliente->region,
                    'comuna' => $request->comuna_cliente ?? $cliente->comuna,
                    'ciudad' => $request->comuna_cliente ?? $cliente->ciudad,
                ]);

                $neto = 0;
                $detallesCalculados = [];

                $cantidades = collect($request->detalles)->groupBy('id_producto')->map(fn ($items): int => $items->sum('cantidad'));
                foreach ($cantidades as $idProducto => $cantidad) {
                    $producto = Producto::query()->whereKey($idProducto)->lockForUpdate()->firstOrFail();

                    if ($producto->stock < $cantidad) {
                        throw new \Exception("Stock insuficiente para el producto: {$producto->nombre}");
                    }

                    $subtotal = $producto->precio * $cantidad;
                    $neto += $subtotal;

                    $detallesCalculados[] = [
                        'producto' => $producto,
                        'cantidad' => $cantidad,
                        'precio_unitario' => $producto->precio,
                        'subtotal' => $subtotal,
                    ];
                }

                $totalBruto = $neto;
                $montoNeto = round($totalBruto / 1.19, 2);
                $montoIva = round($totalBruto - $montoNeto, 2);

                $venta = Venta::create([
                    'fecha' => now(),
                    'tipo_documento' => $request->tipo_documento,
                    'folio_sii' => null,
                    'neto' => $montoNeto,
                    'iva' => $montoIva,
                    'total' => $totalBruto,
                    'medio_pago' => $request->medio_pago,
                    'estado_sii' => 'Pendiente',
                    'estado_dte' => 'pendiente',
                    'user_id' => $request->user()->id,
                    'id_cliente' => $cliente->id_cliente,
                ]);

                foreach ($detallesCalculados as $det) {
                    DetalleVenta::create([
                        'id_venta' => $venta->id_venta,
                        'id_producto' => $det['producto']->id_producto,
                        'cantidad' => $det['cantidad'],
                        'precio_unitario' => $det['precio_unitario'],
                        'subtotal' => $det['subtotal'],
                    ]);

                    $stockAnterior = $det['producto']->stock;
                    $det['producto']->decrement('stock', $det['cantidad']);
                    InventarioMovimiento::create([
                        'id_producto' => $det['producto']->id_producto,
                        'user_id' => $request->user()->id,
                        'tipo' => 'venta',
                        'cantidad' => -$det['cantidad'],
                        'stock_anterior' => $stockAnterior,
                        'stock_nuevo' => $stockAnterior - $det['cantidad'],
                        'motivo' => 'Venta registrada',
                        'id_venta' => $venta->id_venta,
                    ]);
                }

                return [
                    'venta' => $venta,
                    'cliente' => $cliente,
                ];
            });

            foreach ($resultado['venta']->detalles as $detalle) {
                if ($detalle->producto->stock <= $detalle->producto->stock_critico) {
                    User::whereIn('rol', ['Administrador', 'Vendedor'])->get()
                        ->each(fn (User $user) => $user->notify(new ProductoStockCritico($detalle->producto)));
                }
            }

            $resultado['dte'] = $libreDteService->emitir($resultado['venta']->load(['cliente', 'detalles.producto']));

            return response()->json([
                'message' => $resultado['dte']['message'] ?? 'Venta registrada con éxito, stock actualizado y cliente sincronizado.',
                'resultado' => $resultado,
            ], 201);

        } catch (\Exception $e) {
            Log::error('No se pudo registrar la venta.', ['exception' => $e]);

            return response()->json([
                'error' => 'No se pudo registrar la venta. Verifica los datos e inténtalo nuevamente.',
            ], 400);
        }
    }
}
