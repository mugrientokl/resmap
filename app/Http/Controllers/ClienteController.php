<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Rules\RutChileno;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $clientes = Cliente::query()
            ->when($request->filled('buscar'), function ($query) use ($request): void {
                $buscar = $request->string('buscar')->trim()->toString();

                $query->where(function ($clienteQuery) use ($buscar): void {
                    $clienteQuery->where('rut', 'like', "%{$buscar}%")
                        ->orWhere('nombre', 'like', "%{$buscar}%")
                        ->orWhere('correo', 'like', "%{$buscar}%");
                });
            })
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('clientes.index', compact('clientes'));
    }

    public function buscarPorRut(Request $request)
    {
        $datos = $request->validate([
            'rut' => [
                'required',
                'string',
                'max:20',
                new RutChileno,
            ],
        ]);

        $rutBuscado = strtoupper(
            preg_replace('/[^0-9kK]/', '', $datos['rut'])
        );

        $cliente = Cliente::query()
            ->whereRaw(
                "UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '')) = ?",
                [$rutBuscado]
            )
            ->first();

        if (! $cliente) {
            return response()->json([
                'encontrado' => false,
            ]);
        }

        return response()->json([
            'encontrado' => true,
            'cliente' => [
                'nombre' => $cliente->nombre,
                'giro' => $cliente->giro,
                'correo' => $cliente->correo,
                'telefono' => $cliente->telefono,
                'direccion' => $cliente->direccion,
                'region' => $cliente->region,
                'comuna' => $cliente->comuna,
            ],
        ]);
    }

    public function historial(int $id)
    {
        $cliente = Cliente::findOrFail($id);

        $ventas = $cliente->ventas()
            ->with(['detalles.producto', 'user'])
            ->orderByDesc('fecha')
            ->orderByDesc('id_venta')
            ->paginate(10);

        return view(
            'clientes.historial',
            compact('cliente', 'ventas')
        );
    }

    public function resumenVenta(int $id, int $ventaId)
    {
        $cliente = Cliente::findOrFail($id);

        $venta = $cliente->ventas()
            ->with([
                'cliente',
                'user',
                'detalles.producto',
                'cotizacionVersion.cotizacion',
                'ordenesCompra',
                'eventosFacturacion',
            ])
            ->findOrFail($ventaId);

        return Pdf::loadView('reportes.venta-pdf', compact('venta'))
            ->download(
                'resumen-cliente-'.$cliente->id_cliente
                .'-venta-'.$venta->id_venta.'.pdf'
            );
    }

    public function edit(int $id)
    {
        $cliente = Cliente::findOrFail($id);

        return view('clientes.edit', compact('cliente'));
    }

    public function update(Request $request, int $id)
    {
        $cliente = Cliente::findOrFail($id);

        $datos = $request->validate([
            'rut' => [
                'required',
                'string',
                'max:20',
                new RutChileno,
                Rule::unique('clientes', 'rut')
                    ->ignore($cliente->id_cliente, 'id_cliente'),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'direccion' => ['nullable', 'string', 'max:255'],
        ]);

        $cliente->update($datos);

        return redirect()
            ->route('clientes.index')
            ->with('success', 'Cliente actualizado con éxito.');
    }

    public function destroy(int $id)
    {
        $cliente = Cliente::findOrFail($id);
        $cliente->delete();

        return redirect()
            ->route('clientes.index')
            ->with('success', 'Cliente eliminado con éxito.');
    }
}