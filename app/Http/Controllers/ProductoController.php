<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Categoria;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\ReservaInventario;
use App\Services\RegistroActividad;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $categorias = Categoria::orderBy('nombre_categoria')->get();

        $productos = $this->consultaProductos($request)
            ->paginate(20)
            ->withQueryString();

        $productosInactivosIds = $productos->getCollection()
            ->filter(fn (Producto $producto): bool => ! $producto->activo)
            ->pluck('id_producto');

        $motivosDesactivacion = Auditoria::query()
            ->where('modelo', Producto::class)
            ->where('accion', 'producto_desactivado')
            ->whereIn('modelo_id', $productosInactivosIds)
            ->orderByDesc('id_auditoria')
            ->get(['modelo_id', 'datos_nuevos'])
            ->unique('modelo_id')
            ->mapWithKeys(fn (Auditoria $registro): array => [
                $registro->modelo_id => $registro->datos_nuevos['motivo'] ?? null,
            ]);

        return view('productos.index', compact('productos', 'categorias', 'motivosDesactivacion'));
    }

    public function exportarExcel(Request $request)
    {
        $productos = $this->consultaProductos($request)->get();
        $filtros = $this->filtrosInventario($request);
        $fechaGeneracion = now();

        app(RegistroActividad::class)->evento('exportacion', 'exportado', 'Exportó el inventario (Excel)', 'Producto', nuevos: ['cantidad' => $productos->count()]);

        return response()->streamDownload(function () use ($productos, $filtros, $fechaGeneracion): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['REPORTE DE INVENTARIO'], ';');
            fputcsv($handle, ['Fecha de generación', $fechaGeneracion->format('d/m/Y H:i')], ';');

            foreach ($filtros as $nombre => $valor) {
                fputcsv($handle, [$nombre, $valor], ';');
            }

            fputcsv($handle, ['Total de productos', $productos->count()], ';');
            fputcsv($handle, [], ';');

            fputcsv($handle, [
                'Código de barra',
                'Código origen',
                'Nombre',
                'Categoría',
                'Precio',
                'Stock actual',
                'Stock crítico',
                'Estado del stock',
                'Ubicación',
                'Unidad',
                'Estado catálogo',
            ], ';');

            foreach ($productos as $producto) {
                fputcsv($handle, [
                    $producto->codigo_barra,
                    $producto->codigo_origen,
                    $producto->nombre,
                    $producto->categoria?->nombre_categoria,
                    $producto->precio,
                    $producto->stock,
                    $producto->stock_critico,
                    $this->estadoStock($producto),
                    $producto->ubicacion,
                    $producto->unidad,
                    $producto->activo ? 'Activo' : 'Inactivo',
                ], ';');
            }

            fclose($handle);
        }, 'inventario-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportarPdf(Request $request)
    {
        $productos = $this->consultaProductos($request, false)->get([
            'id_producto',
            'id_categoria',
            'codigo_barra',
            'nombre',
            'unidad',
            'activo',
            'precio',
            'stock',
            'stock_critico',
        ]);
        $filtros = $this->filtrosInventario($request);
        $fechaGeneracion = now();

        app(RegistroActividad::class)->evento('exportacion', 'exportado', 'Exportó el inventario (PDF)', 'Producto', nuevos: ['cantidad' => $productos->count()]);

        return Pdf::loadView('productos.export-pdf', compact(
            'productos',
            'filtros',
            'fechaGeneracion'
        ))
            ->setPaper('a4', 'landscape')
            ->download('inventario-'.now()->format('Y-m-d').'.pdf');
    }

    private function consultaProductos(Request $request, bool $incluirStockReservado = true)
    {
        $query = Producto::with('categoria');

        if ($incluirStockReservado) {
            $query->withSum('reservasActivas as stock_reservado', 'cantidad');
        }

        return $query
            ->when($request->filled('nombre'), function ($query) use ($request): void {
                $query->buscarCoincidencias($request->string('nombre')->trim()->toString());
            })
            ->when($request->filled('categoria'), function ($query) use ($request): void {
                $query->where('id_categoria', $request->integer('categoria'));
            })
            ->when(in_array($request->input('activo'), ['1', '0'], true), function ($query) use ($request): void {
                $query->where('activo', $request->input('activo') === '1');
            })
            ->when($request->filled('stock'), function ($query) use ($request): void {
                $tipoStock = $request->string('stock')->toString();

                if ($tipoStock === 'agotado') {
                    $query->where('stock', '<=', 0);
                } elseif ($tipoStock === 'critico') {
                    $query->whereColumn('stock', '<=', 'stock_critico');
                } elseif ($tipoStock === 'disponible') {
                    $query->whereColumn('stock', '>', 'stock_critico');
                }
            })
            ->orderBy('nombre');
    }

    private function filtrosInventario(Request $request): array
    {
        $categoria = $request->filled('categoria')
            ? Categoria::find($request->integer('categoria'))
            : null;

        return [
            'Filtro de nombre o código' => $request->filled('nombre')
                ? $request->string('nombre')->toString()
                : 'Todos',
            'Categoría' => $categoria?->nombre_categoria ?? 'Todas',
            'Estado del stock' => match ($request->string('stock')->toString()) {
                'agotado' => 'Sin stock',
                'critico' => 'Stock crítico',
                'disponible' => 'Stock disponible',
                default => 'Todos',
            },
            'Estado del catálogo' => match ($request->input('activo')) {
                '1' => 'Activo',
                '0' => 'Inactivo',
                default => 'Todos',
            },
        ];
    }

    private function estadoStock(Producto $producto): string
    {
        if ((int) $producto->stock <= 0) {
            return 'Agotado';
        }

        if ((int) $producto->stock <= (int) $producto->stock_critico) {
            return 'Crítico';
        }

        return 'Disponible';
    }

    public function buscarParaPos(Request $request)
    {
        $datos = $request->validate(['buscar' => ['required', 'string', 'max:100']]);
        $buscar = trim($datos['buscar']);

        if ($buscar === '') {
            return response()->json(['message' => 'Escribe un nombre o código de producto.'], 422);
        }

        $productos = Producto::query()
            ->where('activo', true)
            ->withSum('reservasActivas as stock_reservado', 'cantidad')
            ->buscarCoincidencias($buscar)
            ->orderBy('nombre')
            ->limit(8)
            ->get();

        return response()->json($productos->map(fn (Producto $producto): array => [
            'id_producto' => $producto->id_producto,
            'nombre' => $producto->nombre,
            'codigo' => $producto->codigo_origen ?: $producto->codigo_barra,
            'precio' => (float) $producto->precio,
            'stock' => $producto->stock_disponible,
        ]));
    }

    public function buscarPorCodigo(Request $request)
    {
        $codigo = $request->string('codigo')->trim()->toString();

        if ($codigo === '') {
            return response()->json([
                'message' => 'El código es obligatorio.',
            ], 422);
        }

        $producto = Producto::query()
            ->withSum('reservasActivas as stock_reservado', 'cantidad')
            ->where('activo', true)
            ->where(function ($query) use ($codigo): void {
                $query->where('codigo_barra', $codigo)
                    ->orWhere('codigo_origen', $codigo);
            })
            ->first();

        if (! $producto) {
            return response()->json([
                'message' => 'No se encontró un producto con ese código.',
            ], 404);
        }

        return response()->json([
            'id_producto' => $producto->id_producto,
            'nombre' => $producto->nombre,
            'precio' => (float) $producto->precio,
            'stock' => $producto->stock_disponible,
        ]);
    }

    public function etiqueta(int $id)
    {
        $producto = Producto::with('categoria')->findOrFail($id);

        return view('productos.etiqueta', compact('producto'));
    }

    public function create()
    {
        $categorias = Categoria::orderBy('nombre_categoria')->get();
        $nombresExistentes = Producto::pluck('nombre');

        return view('productos.create', compact('categorias', 'nombresExistentes'));
    }

    public function store(Request $request)
    {
        if (is_string($request->input('nombre'))) {
            $request->merge(['nombre' => trim($request->input('nombre'))]);
        }
        $validator = Validator::make($request->all(), [
            'codigo_barra' => ['prohibited'],
            'codigo_origen' => ['prohibited'],
            'nombre' => [
                'required',
                'string',
                'max:255',
                'not_regex:/^\d+$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && strlen(Producto::prefijoCodigo($value)) < 3) {
                        $fail('El nombre del repuesto debe contener al menos tres letras.');
                    }
                    if (is_string($value) && Producto::nombreExiste($value)) {
                        $fail('Ya existe un repuesto con ese nombre.');
                    }
                },
            ],
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric|min:0|max:99999999.99',
            'stock' => 'required|integer|min:0|max:2147483647',
            'stock_critico' => 'required|integer|min:0|max:2147483647',
            'unidad' => 'required|in:unidad,metro',
            'activo' => 'required|boolean',
            'id_categoria' => 'required|exists:categorias,id_categoria',
            'imagen' => 'nullable|image|max:4096',
        ], [
            'nombre.not_regex' => 'El nombre del repuesto no puede contener únicamente números; debe incluir al menos una letra.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withInput()
                ->withErrors($validator)
                ->with('validation_error', $validator->errors()->first());
        }

        $datos = $validator->validated();
        unset($datos['imagen']);

        $imagenNueva = $request->hasFile('imagen')
            ? $request->file('imagen')->store('productos', 'public')
            : null;
        if ($imagenNueva !== null) {
            $datos['imagen'] = $imagenNueva;
        }

        try {
            $producto = Producto::crearConCodigo($datos);
        } catch (UniqueConstraintViolationException $exception) {
            if ($imagenNueva !== null) {
                Storage::disk('public')->delete($imagenNueva);
            }
            if (Producto::nombreExiste($datos['nombre'])) {
                return redirect()->back()->withInput()->withErrors(['nombre' => 'Ya existe un repuesto con ese nombre.']);
            }
            throw $exception;
        } catch (ValidationException $exception) {
            if ($imagenNueva !== null) {
                Storage::disk('public')->delete($imagenNueva);
            }
            throw $exception;
        }

        InventarioMovimiento::create([
            'id_producto' => $producto->id_producto,
            'user_id' => $request->user()->id,
            'tipo' => 'ingreso',
            'cantidad' => $producto->stock,
            'stock_anterior' => 0,
            'stock_nuevo' => $producto->stock,
            'motivo' => 'Producto creado',
        ]);

        return redirect()
            ->route('productos.index')
            ->with('success', 'Producto creado con éxito.');
    }

    public function edit($id)
    {
        $producto = Producto::findOrFail($id);
        $categorias = Categoria::orderBy('nombre_categoria')->get();
        $nombresExistentes = Producto::whereKeyNot($producto->id_producto)->pluck('nombre');

        return view('productos.edit', compact('producto', 'categorias', 'nombresExistentes'));
    }

    public function update(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);
        $seDesactiva = $producto->activo && ! $request->boolean('activo');
        if (is_string($request->input('nombre'))) {
            $request->merge(['nombre' => trim($request->input('nombre'))]);
        }

        $validator = Validator::make($request->all(), [
            'codigo_origen' => ['prohibited'],
            'codigo_barra' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9._\/-]+$/',
                'unique:productos,codigo_barra,'.$id.',id_producto',
                'in:'.$producto->codigo_barra,
            ],
            'nombre' => [
                'required',
                'string',
                'max:255',
                'not_regex:/^\d+$/',
                function (string $attribute, mixed $value, \Closure $fail) use ($producto): void {
                    if (is_string($value) && Producto::nombreExiste($value, $producto->id_producto)) {
                        $fail('Ya existe un repuesto con ese nombre.');
                    }
                },
            ],
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric|min:0|max:99999999.99',
            'stock' => 'required|integer|min:0|max:2147483647',
            'stock_critico' => 'required|integer|min:0|max:2147483647',
            'unidad' => 'required|in:unidad,metro',
            'activo' => 'required|boolean',
            'id_categoria' => 'required|exists:categorias,id_categoria',
            'imagen' => 'nullable|image|max:4096',
            'motivo_desactivacion' => [
                $seDesactiva ? 'required' : 'nullable',
                'string',
                'max:1000',
            ],
        ], [
            'nombre.not_regex' => 'El nombre del repuesto no puede contener únicamente números; debe incluir al menos una letra.',
            'codigo_barra.unique' => 'Ya existe un repuesto con ese código.',
            'motivo_desactivacion.required' => 'Indica por qué se desactiva el repuesto.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withInput()
                ->withErrors($validator)
                ->with('validation_error', $validator->errors()->first());
        }

        $datos = $validator->validated();
        $motivoDesactivacion = trim((string) ($datos['motivo_desactivacion'] ?? ''));
        unset($datos['imagen'], $datos['motivo_desactivacion']);

        $imagenNueva = $request->hasFile('imagen')
            ? $request->file('imagen')->store('productos', 'public')
            : null;
        if ($imagenNueva !== null) {
            $datos['imagen'] = $imagenNueva;
        }

        $stockNuevo = (int) $datos['stock'];
        $productoDesactivado = false;
        $stockAnteriorAlDesactivar = 0;
        $stockConservado = 0;

        try {
            DB::transaction(function () use ($datos, $id, $request, $stockNuevo, $motivoDesactivacion, &$productoDesactivado, &$stockAnteriorAlDesactivar, &$stockConservado): void {
                $producto = Producto::query()->whereKey($id)->lockForUpdate()->firstOrFail();
                $stockAnterior = (int) $producto->stock;
                $stockReservado = ReservaInventario::query()
                    ->where('producto_id', $producto->id_producto)
                    ->where('estado', 'activa')
                    ->where('expira_at', '>', now())
                    ->sum('cantidad');

                if ($stockNuevo < $stockReservado) {
                    throw new \RuntimeException('El stock no puede quedar por debajo de las reservas de cotizaciones vigentes.');
                }

                $productoDesactivado = $producto->activo && ! (bool) $datos['activo'];
                if ($productoDesactivado && $motivoDesactivacion === '') {
                    throw ValidationException::withMessages([
                        'motivo_desactivacion' => 'Indica por qué se desactiva el repuesto.',
                    ]);
                }

                $producto->update($datos);
                if ($productoDesactivado) {
                    $stockAnteriorAlDesactivar = $stockAnterior;
                    $stockConservado = (int) $producto->stock;
                }

                if ($stockAnterior !== $stockNuevo) {
                    InventarioMovimiento::create([
                        'id_producto' => $producto->id_producto,
                        'user_id' => $request->user()->id,
                        'tipo' => 'ajuste',
                        'cantidad' => $stockNuevo - $stockAnterior,
                        'stock_anterior' => $stockAnterior,
                        'stock_nuevo' => $stockNuevo,
                        'motivo' => 'Stock editado desde producto',
                    ]);
                }
            });
        } catch (UniqueConstraintViolationException $exception) {
            if ($imagenNueva !== null) {
                Storage::disk('public')->delete($imagenNueva);
            }
            if (Producto::nombreExiste($datos['nombre'], $producto->id_producto)) {
                return redirect()->back()->withInput()->withErrors(['nombre' => 'Ya existe un repuesto con ese nombre.']);
            }
            throw $exception;
        } catch (\RuntimeException $exception) {
            if ($imagenNueva !== null) {
                Storage::disk('public')->delete($imagenNueva);
            }

            return redirect()->back()->withInput()->withErrors(['stock' => $exception->getMessage()]);
        }

        if ($productoDesactivado) {
            app(RegistroActividad::class)->evento(
                categoria: 'datos',
                accion: 'producto_desactivado',
                descripcion: 'Desactivó el repuesto '.$producto->nombre.': '.$motivoDesactivacion,
                modelo: Producto::class,
                modeloId: (int) $producto->id_producto,
                anteriores: ['activo' => true, 'stock' => $stockAnteriorAlDesactivar],
                nuevos: [
                    'activo' => false,
                    'motivo' => $motivoDesactivacion,
                    'stock_conservado' => $stockConservado,
                ],
            );

            if ($stockConservado > 0) {
                return redirect()
                    ->route('productos.index')
                    ->with('warning', "El producto fue desactivado por: {$motivoDesactivacion}. Se conservaron {$stockConservado} unidades en inventario y no estará disponible en el catálogo.");
            }
        }

        return redirect()
            ->route('productos.index')
            ->with('success', 'Producto actualizado con éxito.');
    }

    public function destroy($id)
    {
        $producto = Producto::findOrFail($id);
        $producto->delete();

        return redirect()
            ->route('productos.index')
            ->with('success', 'Producto eliminado con éxito.');
    }
}
