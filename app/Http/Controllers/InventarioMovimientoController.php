<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\ReservaInventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventarioMovimientoController extends Controller
{
    private const MOTIVOS_FILTRO = [
        'grupo:compra' => 'Compra / Adquisición',
        'grupo:merma' => 'Merma / Daño',
        'grupo:devolucion' => 'Devolución al proveedor',
        'grupo:ajuste' => 'Ajuste de inventario',
        'grupo:uso_interno' => 'Uso interno',
        'grupo:venta' => 'Venta',
        'grupo:stock_inicial' => 'Stock inicial',
        'grupo:otros' => 'Otros',
    ];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'producto' => ['nullable', 'integer', 'exists:productos,id_producto'],
            'tipo' => ['nullable', 'string', 'in:adquisicion,merma,ajuste,ingreso,egreso'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_desde'],
        ]);

        $movimientos = InventarioMovimiento::with(['producto.categoria', 'usuario', 'venta.cliente'])
            ->when(isset($filters['producto']), function ($query) use ($filters): void {
                $query->where('id_producto', $filters['producto']);
            })
            ->when(isset($filters['tipo']), function ($query) use ($filters): void {
                $query->where('tipo', $filters['tipo']);
            })
            ->when(isset($filters['motivo']), function ($query) use ($filters): void {
                if (array_key_exists($filters['motivo'], self::MOTIVOS_FILTRO)) {
                    $query->whereRaw(
                        '(case
                            when tipo = ? then ?
                            when tipo = ? or motivo = ? then ?
                            when tipo = ? or motivo = ? then ?
                            when motivo = ? then ?
                            when motivo = ? then ?
                            when tipo = ? then ?
                            when motivo in (?, ?) then ?
                            else ?
                        end) = ?',
                        [
                            'venta', 'grupo:venta',
                            'adquisicion', 'Compra / Adquisición', 'grupo:compra',
                            'merma', 'Merma / Daño', 'grupo:merma',
                            'Devolución', 'grupo:devolucion',
                            'Uso interno', 'grupo:uso_interno',
                            'ajuste', 'grupo:ajuste',
                            'Producto creado', 'Stock inicial reconstruido para el historial de ventas', 'grupo:stock_inicial',
                            'grupo:otros', $filters['motivo'],
                        ],
                    );
                } else {
                    $query->where('motivo', $filters['motivo']);
                }
            })
            ->when(isset($filters['fecha_desde']), function ($query) use ($filters): void {
                $query->whereDate('fecha_evento', '>=', $filters['fecha_desde']);
            })
            ->when(isset($filters['fecha_hasta']), function ($query) use ($filters): void {
                $query->whereDate('fecha_evento', '<=', $filters['fecha_hasta']);
            })
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('inventario.movimientos', [
            'movimientos' => $movimientos,
            'catalogoProductos' => $this->catalogoProductos(),
            'categorias' => Categoria::orderBy('nombre_categoria')->get(),
            'proveedores' => Proveedor::orderBy('nombre')->get(),
            'motivos' => self::MOTIVOS_FILTRO,
            'productoFiltrado' => isset($filters['producto'])
                ? Producto::with('categoria')->find($filters['producto'])
                : null,
        ]);
    }

    /**
     * @return array<int, array{id: int, categoria: ?int, stock: int, busqueda: string, etiqueta: string, ultima_adquisicion: ?array{fecha: ?string, cantidad: ?int, costo_total: ?float, proveedor: ?string}}>
     */
    private function catalogoProductos(): array
    {
        $ultimasAdquisiciones = InventarioMovimiento::query()
            ->whereIn('id_movimiento', InventarioMovimiento::query()
                ->selectRaw('MAX(id_movimiento)')
                ->where('cantidad', '>', 0)
                ->where(function ($query): void {
                    $query->where('tipo', 'adquisicion')
                        ->orWhere('motivo', 'Compra / Adquisición')
                        ->orWhere(function ($query): void {
                            $query->where('tipo', 'ingreso')->whereNotNull('precio_entrada');
                        });
                })
                ->groupBy('id_producto'))
            ->get(['id_movimiento', 'id_producto', 'fecha_evento', 'created_at', 'cantidad_adquirida', 'precio_entrada', 'proveedor'])
            ->keyBy('id_producto');

        return Producto::query()
            ->leftJoin('categorias', 'categorias.id_categoria', '=', 'productos.id_categoria')
            ->orderBy('productos.nombre')
            ->get([
                'productos.id_producto', 'productos.nombre', 'productos.codigo_barra', 'productos.codigo_origen',
                'productos.stock', 'productos.id_categoria', 'categorias.nombre_categoria',
            ])
            ->map(function (Producto $producto) use ($ultimasAdquisiciones): array {
                $categoria = $producto->nombre_categoria ?? 'Sin Categoría';
                $adquisicion = $ultimasAdquisiciones->get($producto->id_producto);

                return [
                    'id' => $producto->id_producto,
                    'categoria' => $producto->id_categoria,
                    'stock' => (int) $producto->stock,
                    'busqueda' => trim($producto->nombre.' '.$categoria.' '.$producto->codigo_barra.' '.($producto->codigo_origen ?? '')),
                    'etiqueta' => $producto->nombre.' | Cat: '.$categoria.' | Stock: '.$producto->stock,
                    'ultima_adquisicion' => $adquisicion ? [
                        'fecha' => ($adquisicion->fecha_evento ?? $adquisicion->created_at)?->format('d/m/Y'),
                        'cantidad' => $adquisicion->cantidad_adquirida !== null ? (int) $adquisicion->cantidad_adquirida : null,
                        'costo_total' => $adquisicion->precio_entrada !== null ? (float) $adquisicion->precio_entrada : null,
                        'proveedor' => $adquisicion->proveedor,
                    ] : null,
                ];
            })
            ->all();
    }

    public function show(InventarioMovimiento $movimiento)
    {
        $movimiento->load(['producto.categoria', 'usuario', 'venta.cliente', 'venta.detalles.producto']);

        return response()->json([
            'id_movimiento' => $movimiento->id_movimiento,
            'fecha' => $movimiento->created_at?->format('d/m/Y H:i'),
            'fecha_evento' => $movimiento->fecha_evento?->format('d/m/Y'),
            'tipo' => $movimiento->tipo,
            'motivo' => $movimiento->motivo,
            'cantidad' => $movimiento->cantidad,
            'stock_anterior' => $movimiento->stock_anterior,
            'stock_nuevo' => $movimiento->stock_nuevo,
            'producto' => $movimiento->producto?->nombre ?? 'Producto eliminado',
            'usuario' => $movimiento->usuario?->name ?? 'Sistema',
            'proveedor' => $movimiento->proveedor,
            'contacto_proveedor' => $movimiento->contacto_proveedor,
            'documento_proveedor' => $movimiento->documento_proveedor,
            'precio_entrada' => $movimiento->precio_entrada !== null ? (float) $movimiento->precio_entrada : null,
            'cantidad_adquirida' => $movimiento->cantidad_adquirida,
            'precio_unitario_adquisicion' => $movimiento->precio_entrada !== null && $movimiento->cantidad_adquirida > 0
                ? round((float) $movimiento->precio_entrada / $movimiento->cantidad_adquirida, 2)
                : null,
            'precio_venta_recomendado' => $movimiento->precio_entrada !== null && $movimiento->cantidad_adquirida > 0
                ? round(((float) $movimiento->precio_entrada / $movimiento->cantidad_adquirida) * 1.19, 2)
                : null,
            'observaciones' => $movimiento->observaciones,
            'venta' => $movimiento->venta ? [
                'id_venta' => $movimiento->venta->id_venta,
                'tipo_documento' => $movimiento->venta->tipo_documento,
                'cliente' => data_get($movimiento->venta->snapshotDatosCliente(), 'nombre') ?: ($movimiento->venta->cliente?->nombre ?? 'Sin cliente'),
                'total' => (float) $movimiento->venta->total,
                'detalles' => $movimiento->venta->detalles->map(fn ($detalle): array => [
                    'producto' => $detalle->producto?->nombre ?? ($detalle->descripcion ?: 'Producto eliminado'),
                    'cantidad' => $detalle->cantidad,
                    'subtotal' => (float) $detalle->subtotal,
                ])->values(),
            ] : null,
        ]);
    }

    public function store(Request $request)
    {
        $multiple = $request->has('productos');
        $requiereProveedor = $request->input('tipo') === 'adquisicion'
            || in_array($request->input('motivo'), ['Compra / Adquisición', 'Devolución'], true);

        $rules = [
            'tipo' => ['nullable', 'string', 'in:adquisicion,merma,ajuste,ingreso,egreso'],
            'motivo' => ['required', 'string', 'max:255'],
            'fecha_evento' => ['required', 'date_format:Y-m-d'],
            'proveedor_id' => [Rule::requiredIf($requiereProveedor), 'nullable', 'integer', 'exists:proveedores,id'],
            'documento_proveedor' => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ];
        $esCompra = $request->input('tipo') === 'adquisicion' || $request->input('motivo') === 'Compra / Adquisición';
        if ($multiple) {
            $rules += [
                'productos' => ['required', 'array', 'min:1', 'max:100'],
                'productos.*.id_producto' => ['required', 'integer', 'distinct', 'exists:productos,id_producto'],
                'productos.*.cantidad' => ['required', 'integer', 'min:'.($request->input('tipo') === 'ajuste' ? 0 : 1), 'max:2147483647'],
                'productos.*.precio_entrada' => [Rule::requiredIf($esCompra), 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
            ];
        } else {
            $rules += [
                'id_producto' => ['required', 'exists:productos,id_producto'],
                'stock_nuevo' => [Rule::requiredIf(! $request->filled('tipo') || $request->input('tipo') === 'ajuste'), 'nullable', 'integer', 'min:0', 'max:2147483647'],
                'cantidad' => ['nullable', 'required_if:tipo,adquisicion,merma,ingreso,egreso', 'integer', 'min:1', 'max:2147483647'],
                'precio_entrada' => ['nullable', 'required_if:tipo,adquisicion', 'numeric', 'min:0', 'max:99999999.99'],
            ];
        }
        $validated = $request->validate($rules);

        if ($multiple) {
            $motivosPermitidos = [
                'ingreso' => ['Compra / Adquisición'],
                'egreso' => ['Merma / Daño', 'Devolución'],
                'ajuste' => ['Ajuste de inventario', 'Uso interno'],
            ];
            if (! in_array($validated['motivo'], $motivosPermitidos[$validated['tipo'] ?? ''] ?? [], true)) {
                throw ValidationException::withMessages(['motivo' => 'El motivo no corresponde al tipo de movimiento.']);
            }
        }

        if ($validated['motivo'] === 'Devolución' && ($validated['tipo'] ?? 'ajuste') !== 'egreso') {
            throw ValidationException::withMessages(['tipo' => 'Una devolución al proveedor debe ser un egreso.']);
        }
        if ($validated['motivo'] === 'Compra / Adquisición' && ! in_array($validated['tipo'] ?? 'ajuste', ['ingreso', 'adquisicion'], true)) {
            throw ValidationException::withMessages(['tipo' => 'Una adquisición debe ser un ingreso.']);
        }
        if (! $multiple && $esCompra && ! isset($validated['precio_entrada'])) {
            throw ValidationException::withMessages(['precio_entrada' => 'El costo neto de la adquisición es obligatorio.']);
        }

        try {
            DB::transaction(function () use ($validated, $request, $requiereProveedor, $multiple, $esCompra): void {
                $tipo = $validated['tipo'] ?? 'ajuste';
                $proveedor = $requiereProveedor
                    ? Proveedor::query()->whereKey($validated['proveedor_id'])->sharedLock()->first()
                    : null;
                if ($requiereProveedor && ! $proveedor) {
                    throw ValidationException::withMessages(['proveedor_id' => 'El proveedor seleccionado ya no existe.']);
                }

                $lineas = $multiple ? $validated['productos'] : [[
                    'id_producto' => $validated['id_producto'],
                    'cantidad' => $tipo === 'ajuste' ? ($validated['stock_nuevo'] ?? 0) : ($validated['cantidad'] ?? 0),
                    'precio_entrada' => $validated['precio_entrada'] ?? null,
                ]];
                usort($lineas, fn (array $primera, array $segunda): int => (int) $primera['id_producto'] <=> (int) $segunda['id_producto']);
                foreach ($lineas as $linea) {
                    $producto = Producto::query()->whereKey($linea['id_producto'])->lockForUpdate()->firstOrFail();
                    $stockAnterior = (int) $producto->stock;
                    $cantidad = match ($tipo) {
                        'adquisicion', 'ingreso' => (int) $linea['cantidad'],
                        'merma', 'egreso' => -((int) $linea['cantidad']),
                        default => (int) $linea['cantidad'] - $stockAnterior,
                    };

                    $stockNuevo = $stockAnterior + $cantidad;

                    if ($stockNuevo < 0) {
                        throw new \RuntimeException('La merma o egreso no puede superar el stock disponible.');
                    }
                    if ($stockNuevo > 2147483647) {
                        throw new \RuntimeException('El stock resultante supera el máximo permitido.');
                    }
                    $stockReservado = ReservaInventario::query()
                        ->where('producto_id', $producto->id_producto)
                        ->where('estado', 'activa')
                        ->where('expira_at', '>', now())
                        ->sum('cantidad');
                    if ($stockNuevo < $stockReservado) {
                        throw new \RuntimeException('El ajuste dejaría menos stock físico que las unidades reservadas para cotizaciones vigentes.');
                    }

                    $datosProducto = ['stock' => $stockNuevo];
                    if ($esCompra && isset($linea['precio_entrada']) && $cantidad > 0) {
                        $precioConIva = round(((float) $linea['precio_entrada'] / $cantidad) * 1.19, 2);
                        if ($precioConIva > 99999999.99) {
                            throw new \RuntimeException('El precio resultante supera el máximo permitido.');
                        }
                        $datosProducto['precio'] = $precioConIva;
                    }
                    $producto->update($datosProducto);

                    InventarioMovimiento::create([
                        'id_producto' => $producto->id_producto,
                        'user_id' => $request->user()->id,
                        'tipo' => $tipo,
                        'cantidad' => $cantidad,
                        'stock_anterior' => $stockAnterior,
                        'stock_nuevo' => $stockNuevo,
                        'motivo' => $validated['motivo'],
                        'fecha_evento' => $validated['fecha_evento'],
                        'proveedor_id' => $proveedor?->id,
                        'proveedor' => $proveedor?->nombre,
                        'contacto_proveedor' => $proveedor?->contacto,
                        'documento_proveedor' => $validated['documento_proveedor'] ?? null,
                        'precio_entrada' => $esCompra ? ($linea['precio_entrada'] ?? null) : null,
                        'cantidad_adquirida' => $esCompra ? $cantidad : null,
                        'observaciones' => $validated['observaciones'] ?? null,
                    ]);
                }
            });
        } catch (\RuntimeException $exception) {
            return redirect()->back()->withInput()->withErrors(['cantidad' => $exception->getMessage()]);
        }

        return redirect()->route('inventario.movimientos')->with('success', 'Stock ajustado y movimiento registrado.');
    }
}
