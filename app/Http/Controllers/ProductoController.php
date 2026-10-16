<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $categorias = Categoria::orderBy('nombre_categoria')->get();

        $productos = Producto::with('categoria')
            ->when($request->filled('nombre'), function ($query) use ($request): void {
                $nombre = $request->string('nombre')->toString();

                $query->where(function ($productQuery) use ($nombre): void {
                    $productQuery->where('nombre', 'like', "%{$nombre}%")
                        ->orWhere('codigo_barra', 'like', "%{$nombre}%")
                        ->orWhere('codigo_origen', 'like', "%{$nombre}%");
                });
            })
            ->when($request->filled('categoria'), function ($query) use ($request): void {
                $query->where('id_categoria', $request->integer('categoria'));
            })
            ->when($request->filled('stock'), function ($query) use ($request): void {
                $tipoStock = $request->string('stock')->toString();

                if ($tipoStock === 'agotado') {
                    $query->where('stock', '<=', 0);
                } elseif ($tipoStock === 'critico') {
                    $query->where('stock', '>', 0)
                        ->whereColumn('stock', '<=', 'stock_critico');
                } elseif ($tipoStock === 'disponible') {
                    $query->whereColumn('stock', '>', 'stock_critico');
                }
            })
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('productos.index', compact('productos', 'categorias'));
    }

    public function exportarExcel()
    {
        $productos = Producto::with('categoria')->orderBy('nombre')->get();

        return response()->streamDownload(function () use ($productos): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv(
                $handle,
                [
                    'Código de barra',
                    'Código origen',
                    'Nombre',
                    'Categoría',
                    'Precio',
                    'Stock',
                    'Stock crítico',
                    'Ubicación',
                    'Unidad',
                ],
                ';'
            );

            foreach ($productos as $producto) {
                fputcsv(
                    $handle,
                    [
                        $producto->codigo_barra,
                        $producto->codigo_origen,
                        $producto->nombre,
                        $producto->categoria?->nombre_categoria,
                        $producto->precio,
                        $producto->stock,
                        $producto->stock_critico,
                        $producto->ubicacion,
                        $producto->unidad,
                    ],
                    ';'
                );
            }

            fclose($handle);
        }, 'inventario-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportarPdf()
    {
        $productos = Producto::with('categoria')
            ->orderBy('nombre')
            ->get();

        return Pdf::loadView(
            'productos.export-pdf',
            compact('productos')
        )->download(
            'inventario-'.now()->format('Y-m-d').'.pdf'
        );
    }

    public function buscarPorCodigo(Request $request)
    {
        $codigo = $request->string('codigo')->trim()->toString();

        if ($codigo === '') {
            return response()->json([
                'message' => 'El código es obligatorio.',
            ], 422);
        }

        $producto = Producto::where('codigo_barra', $codigo)
            ->orWhere('codigo_origen', $codigo)
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
            'stock' => $producto->stock,
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

        return view('productos.create', compact('categorias'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'codigo_barra' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9._\/-]+$/',
                'unique:productos,codigo_barra',
            ],
            'nombre' => [
                'required',
                'string',
                'max:255',
                'not_regex:/^\d+$/',
            ],
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric|min:0|max:99999999.99',
            'stock' => 'required|integer|min:0|max:2147483647',
            'stock_critico' => 'required|integer|min:0|max:2147483647',
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

        $datos = $request->except('imagen');

        if ($request->hasFile('imagen')) {
            $datos['imagen'] = $request->file('imagen')->store('productos', 'public');
        }

        $producto = Producto::create($datos);

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

        return view('productos.edit', compact('producto', 'categorias'));
    }

    public function update(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'codigo_barra' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9._\/-]+$/',
                'unique:productos,codigo_barra,'.$id.',id_producto',
            ],
            'nombre' => [
                'required',
                'string',
                'max:255',
                'not_regex:/^\d+$/',
            ],
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric|min:0|max:99999999.99',
            'stock' => 'required|integer|min:0|max:2147483647',
            'stock_critico' => 'required|integer|min:0|max:2147483647',
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

        $datos = $request->except('imagen');

        if ($request->hasFile('imagen')) {
            $datos['imagen'] = $request->file('imagen')->store('productos', 'public');
        }

        $stockAnterior = (int) $producto->stock;
        $stockNuevo = (int) $datos['stock'];

        $producto->update($datos);

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
