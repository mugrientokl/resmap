<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\ReservaInventario;
use App\Models\User;
use App\Models\Venta;
use App\Notifications\ProductoStockCritico;
use App\Rules\CorreoConDominio;
use App\Rules\RutChileno;
use App\Services\ResumenInternoVentaPdf;
use App\Support\Rut;
use App\Support\TelefonoChileno;
use App\Support\TextoPersona;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VentaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->merge(['rut' => Rut::normalizar((string) $request->input('rut'))]);
        if (is_string($request->input('telefono_cliente'))) {
            $request->merge(['telefono_cliente' => TelefonoChileno::normalizar($request->input('telefono_cliente'))]);
        }
        $request->merge([
            'nombre_cliente' => TextoPersona::normalizar($request->input('nombre_cliente')),
            'contacto' => TextoPersona::normalizar($request->input('contacto')),
            'razon_social_cliente' => TextoPersona::normalizar($request->input('razon_social_cliente')),
        ]);

        $datos = $request->validate([
            'tipo_documento' => 'required|string|in:Boleta Electrónica,Factura Electrónica',
            'medio_pago' => 'required|string|in:Efectivo,Transferencia,Tarjeta,Crédito',
            'forma_pago' => 'nullable|string|in:Contado,Crédito',
            'rut' => ['required', 'string', 'max:20', new RutChileno],
            'nombre_cliente' => 'required|string|max:255',
            'contacto' => 'required|string|max:255',
            'cargo' => 'nullable|string|max:255',
            'razon_social_cliente' => 'nullable|string|max:255',
            'giro_cliente' => [
                Rule::requiredIf($request->input('tipo_documento') === 'Factura Electrónica'),
                'nullable',
                'string',
                'max:255',
            ],
            'correo_cliente' => ['required', 'email', new CorreoConDominio, 'max:255'],
            'telefono_cliente' => ['nullable', 'string', 'regex:/^\+56[2-9][0-9]{8}$/'],
            'direccion_cliente' => [
                Rule::requiredIf($request->input('tipo_documento') === 'Factura Electrónica'),
                'nullable',
                'string',
                'max:255',
            ],
            'region_cliente' => [
                Rule::requiredIf($request->input('tipo_documento') === 'Factura Electrónica'),
                'nullable',
                'string',
                'max:100',
            ],
            'comuna_cliente' => [
                Rule::requiredIf($request->input('tipo_documento') === 'Factura Electrónica'),
                'nullable',
                'string',
                'max:100',
            ],
            'ciudad_cliente' => [
                Rule::requiredIf($request->input('tipo_documento') === 'Factura Electrónica'),
                'nullable',
                'string',
                'max:100',
            ],
            'acteco_emisor' => ['required', Rule::in(array_keys(config('services.cotizaciones.actividades_emisor')))],
            'tipo_venta' => ['required', Rule::in(['del_giro'])],
            'tipo_compra' => ['required', Rule::in(['del_giro'])],
            'numero_oc' => 'nullable|string|max:100',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_producto' => 'required|exists:productos,id_producto',
            'detalles.*.cantidad' => 'required|integer|min:1|max:2147483647',
        ]);
        $claveIdempotencia = $request->header('Idempotency-Key');
        if ($claveIdempotencia !== null && ! Str::isUuid($claveIdempotencia)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave de idempotencia no es válida.',
            ]);
        }
        if ($claveIdempotencia !== null) {
            $ventaExistente = Venta::query()
                ->where('clave_idempotencia', $claveIdempotencia)
                ->where('user_id', $request->user()->id)
                ->with('cliente')
                ->first();
            if ($ventaExistente?->cliente !== null) {
                return $this->respuestaVenta(
                    $request,
                    $ventaExistente,
                    $ventaExistente->cliente,
                    repetida: true,
                );
            }
        }

        try {
            $resultado = DB::transaction(function () use ($request, $datos, $claveIdempotencia) {
                $actividades = config('services.cotizaciones.actividades_emisor');
                $cliente = Cliente::query()->lockForUpdate()->firstOrCreate(
                    ['rut' => $datos['rut']],
                    [
                        'nombre' => $datos['nombre_cliente'],
                        'contacto' => $datos['contacto'],
                        'razon_social' => $datos['razon_social_cliente'] ?? null,
                        'giro' => $datos['giro_cliente'] ?? null,
                        'correo' => $datos['correo_cliente'],
                        'telefono' => $datos['telefono_cliente'] ?? null,
                        'direccion' => $datos['direccion_cliente'] ?? null,
                        'region' => $datos['region_cliente'] ?? null,
                        'comuna' => $datos['comuna_cliente'] ?? null,
                        'ciudad' => $datos['ciudad_cliente'] ?? null,
                    ]
                );

                $giroCliente = $datos['giro_cliente'] ?? $cliente->giro;

                $cliente->update([
                    'nombre' => $datos['nombre_cliente'],
                    'contacto' => $datos['contacto'],
                    'razon_social' => $datos['razon_social_cliente'] ?? $cliente->razon_social,
                    'giro' => $giroCliente,
                    'correo' => $datos['correo_cliente'],
                    'telefono' => $datos['telefono_cliente'] ?? $cliente->telefono,
                    'direccion' => $datos['direccion_cliente'],
                    'region' => $datos['region_cliente'],
                    'comuna' => $datos['comuna_cliente'],
                    'ciudad' => $datos['ciudad_cliente'],
                ]);

                $neto = 0;
                $detallesCalculados = [];

                $cantidades = collect($datos['detalles'])->groupBy('id_producto')->map(fn ($items): int => $items->sum('cantidad'));
                foreach ($cantidades as $idProducto => $cantidad) {
                    $producto = Producto::query()->whereKey($idProducto)->lockForUpdate()->firstOrFail();

                    $reservado = ReservaInventario::query()
                        ->where('producto_id', $producto->id_producto)
                        ->where('estado', 'activa')
                        ->where('expira_at', '>', now())
                        ->sum('cantidad');
                    $disponible = max(0, (int) $producto->stock - (int) $reservado);

                    if ($disponible < $cantidad) {
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
                    'clave_idempotencia' => $claveIdempotencia,
                    'fecha' => now(),
                    'tipo_documento' => $datos['tipo_documento'],
                    'folio_sii' => null,
                    'neto' => $montoNeto,
                    'iva' => $montoIva,
                    'total' => $totalBruto,
                    'medio_pago' => $datos['medio_pago'],
                    'estado_sii' => 'Pendiente',
                    'estado_venta' => 'confirmada',
                    'estado_pago' => 'registrado',
                    'estado_facturacion' => 'pendiente',
                    'estado_entrega' => 'entregada',
                    'stock_descontado_at' => now(),
                    'user_id' => $request->user()->id,
                    'id_cliente' => $cliente->id_cliente,
                    'datos_cliente' => $cliente->snapshotDatos(),
                    'contacto' => $datos['contacto'],
                    'cargo' => $datos['cargo'] ?? null,
                    'datos_resumen' => [
                        'acteco_emisor' => $datos['acteco_emisor'],
                        'tipo_venta' => $datos['tipo_venta'],
                        'nombre' => $datos['nombre_cliente'],
                        'rut' => $datos['rut'],
                        'giro' => $giroCliente,
                        'direccion' => $datos['direccion_cliente'],
                        'comuna' => $datos['comuna_cliente'],
                        'ciudad' => $datos['ciudad_cliente'],
                        'contacto' => $datos['contacto'],
                        'tipo_compra' => $datos['tipo_compra'],
                        'correo' => $datos['correo_cliente'],
                        'numero_oc' => $datos['numero_oc'] ?? null,
                        'forma_pago' => $datos['forma_pago'] ?? 'Contado',
                        'giro_emisor' => $actividades[$datos['acteco_emisor']],
                    ],
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

            $resultado['venta']->load('detalles.producto');
            foreach ($resultado['venta']->detalles as $detalle) {
                if ($detalle->producto->stock <= $detalle->producto->stock_critico) {
                    User::whereIn('rol', ['Administrador', 'Vendedor'])->get()
                        ->filter(fn (User $user): bool => $user->tienePermiso('productos.ver'))->each(function (User $user) use ($detalle): void {
                            try {
                                $user->notify(new ProductoStockCritico($detalle->producto));
                            } catch (\Throwable $exception) {
                                Log::warning('La venta se registró, pero no se pudo enviar un aviso de stock crítico.', [
                                    'venta_id' => $detalle->id_venta,
                                    'producto_id' => $detalle->id_producto,
                                    'usuario_id' => $user->id,
                                    'error' => $exception->getMessage(),
                                ]);
                            }
                        });
                }
            }

            return $this->respuestaVenta(
                $request,
                $resultado['venta'],
                $resultado['cliente'],
            );

        } catch (UniqueConstraintViolationException $exception) {
            if ($claveIdempotencia !== null) {
                $ventaExistente = Venta::query()
                    ->where('clave_idempotencia', $claveIdempotencia)
                    ->where('user_id', $request->user()->id)
                    ->with('cliente')
                    ->first();
                if ($ventaExistente?->cliente !== null) {
                    return $this->respuestaVenta(
                        $request,
                        $ventaExistente,
                        $ventaExistente->cliente,
                        repetida: true,
                    );
                }
            }

            Log::error('No se pudo registrar la venta.', ['exception' => $exception]);

            return response()->json([
                'error' => 'No se pudo registrar la venta. Verifica los datos e inténtalo nuevamente.',
            ], 400);
        } catch (\Exception $e) {
            Log::error('No se pudo registrar la venta.', ['exception' => $e]);

            return response()->json([
                'error' => 'No se pudo registrar la venta. Verifica los datos e inténtalo nuevamente.',
            ], 400);
        }
    }

    private function respuestaVenta(
        Request $request,
        Venta $venta,
        Cliente $cliente,
        bool $repetida = false,
    ): JsonResponse {
        $request->session()->flash('success', '¡Venta completada correctamente! El stock fue actualizado.');
        $request->session()->flash('resumen_venta_id', $venta->id_venta);

        return response()->json([
            'message' => $repetida
                ? 'La venta ya había sido registrada; no se descontó stock nuevamente.'
                : 'Venta registrada con éxito, stock actualizado y cliente sincronizado.',
            'resultado' => [
                'venta' => $venta,
                'cliente' => $cliente,
            ],
            'repetida' => $repetida,
        ], 201);
    }

    public function resumenInterno(int $venta, ResumenInternoVentaPdf $resumen)
    {
        $venta = Venta::completadas()->findOrFail($venta);
        abort_if($venta->cotizacion_version_id !== null, 404);

        return $resumen->descargar($venta);
    }
}
