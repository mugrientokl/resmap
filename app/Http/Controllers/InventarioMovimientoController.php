<?php

namespace App\Http\Controllers;

use App\Models\InventarioMovimiento;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarioMovimientoController extends Controller
{
    public function index(Request $request)
    {
        $movimientos = InventarioMovimiento::with(['producto', 'usuario', 'venta.cliente'])
            ->when($request->filled('producto'), function ($query) use ($request): void {
                $query->where('id_producto', $request->integer('producto'));
            })
            ->when($request->filled('tipo'), function ($query) use ($request): void {
                $query->where('tipo', $request->string('tipo')->toString());
            })
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('inventario.movimientos', [
            'movimientos' => $movimientos,
            'productos' => Producto::orderBy('nombre')->get(),
        ]);
    }

    public function show(InventarioMovimiento $movimiento)
    {
        $movimiento->load(['producto', 'usuario', 'venta.cliente', 'venta.detalles.producto']);

        return response()->json([
            'id_movimiento' => $movimiento->id_movimiento,
            'fecha' => $movimiento->created_at?->format('d/m/Y H:i'),
            'tipo' => $movimiento->tipo,
            'motivo' => $movimiento->motivo,
            'cantidad' => $movimiento->cantidad,
            'stock_anterior' => $movimiento->stock_anterior,
            'stock_nuevo' => $movimiento->stock_nuevo,
            'producto' => $movimiento->producto?->nombre ?? 'Producto eliminado',
            'usuario' => $movimiento->usuario?->name ?? 'Sistema',
            'proveedor' => $movimiento->proveedor,
            'documento_proveedor' => $movimiento->documento_proveedor,
            'precio_entrada' => $movimiento->precio_entrada ? (float) $movimiento->precio_entrada : null,
            'cantidad_adquirida' => $movimiento->cantidad_adquirida,
            'precio_unitario_adquisicion' => $movimiento->cantidad_adquirida > 0
                ? round((float) $movimiento->precio_entrada / $movimiento->cantidad_adquirida, 2)
                : null,
            'precio_venta_recomendado' => $movimiento->cantidad_adquirida > 0
                ? round(((float) $movimiento->precio_entrada / $movimiento->cantidad_adquirida) * 1.19, 2)
                : null,
            'observaciones' => $movimiento->observaciones,
            'venta' => $movimiento->venta ? [
                'id_venta' => $movimiento->venta->id_venta,
                'tipo_documento' => $movimiento->venta->tipo_documento,
                'cliente' => $movimiento->venta->cliente?->nombre ?? 'Sin cliente',
                'total' => (float) $movimiento->venta->total,
                'detalles' => $movimiento->venta->detalles->map(fn ($detalle): array => [
                    'producto' => $detalle->producto?->nombre ?? 'Producto eliminado',
                    'cantidad' => $detalle->cantidad,
                    'subtotal' => (float) $detalle->subtotal,
                ])->values(),
            ] : null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_producto' => ['required', 'exists:productos,id_producto'],
            'tipo' => ['nullable', 'string', 'in:adquisicion,merma,ajuste'],
            'stock_nuevo' => ['nullable', 'required_if:tipo,ajuste', 'integer', 'min:0', 'max:2147483647'],
            'cantidad' => ['nullable', 'required_if:tipo,adquisicion,merma', 'integer', 'min:1', 'max:2147483647'],
            'motivo' => ['required', 'string', 'max:255'],
            'proveedor' => ['nullable', 'required_if:tipo,adquisicion', 'string', 'max:255'],
            'documento_proveedor' => ['nullable', 'string', 'max:100'],
            'precio_entrada' => ['nullable', 'required_if:tipo,adquisicion', 'numeric', 'min:0', 'max:9999999999.99'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            DB::transaction(function () use ($validated, $request): void {
                $producto = Producto::query()
                    ->whereKey($validated['id_producto'])
                    ->lockForUpdate()
                    ->firstOrFail();
                $stockAnterior = $producto->stock;
                $tipo = $validated['tipo'] ?? 'ajuste';
                $cantidad = match ($tipo) {
                    'adquisicion' => (int) $validated['cantidad'],
                    'merma' => -((int) $validated['cantidad']),
                    default => (int) $validated['stock_nuevo'] - $stockAnterior,
                };
                $stockNuevo = $stockAnterior + $cantidad;

                if ($stockNuevo < 0) {
                    throw new \RuntimeException('La merma no puede superar el stock disponible.');
                }

                $producto->update(['stock' => $stockNuevo]);
                InventarioMovimiento::create([
                    'id_producto' => $producto->id_producto,
                    'user_id' => $request->user()->id,
                    'tipo' => $tipo,
                    'cantidad' => $cantidad,
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $stockNuevo,
                    'motivo' => $validated['motivo'],
                    'proveedor' => $validated['proveedor'] ?? null,
                    'documento_proveedor' => $validated['documento_proveedor'] ?? null,
                    'precio_entrada' => $validated['precio_entrada'] ?? null,
                    'cantidad_adquirida' => $tipo === 'adquisicion' ? $validated['cantidad'] : null,
                    'observaciones' => $validated['observaciones'] ?? null,
                ]);
            });
        } catch (\RuntimeException $exception) {
            return redirect()->back()->withInput()->withErrors(['cantidad' => $exception->getMessage()]);
        }

        return redirect()->route('inventario.movimientos')->with('success', 'Stock ajustado y movimiento registrado.');
    }
}
