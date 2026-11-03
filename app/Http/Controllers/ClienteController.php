<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Rules\RutChileno;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $buscar = $request->string('buscar')->trim()->toString();
        $clientes = Cliente::query()
            ->when($buscar !== '', fn (Builder $query) => $this->filtrarClientes($query, $buscar))
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('clientes.index', compact('clientes'));
    }

    public function sugerencias(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'buscar' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $clientes = $this->filtrarClientes(
            Cliente::query()->select('id_cliente', 'nombre', 'rut', 'correo'),
            trim($datos['buscar'])
        )
            ->orderBy('nombre')
            ->limit(8)
            ->get();

        return response()->json($clientes);
    }

    private function filtrarClientes(Builder $query, string $buscar): Builder
    {
        $palabras = preg_split('/\s+/u', $buscar, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($palabras as $palabra) {
            $rutNormalizado = strtoupper(
                preg_replace('/[^0-9kK]/', '', $palabra) ?? ''
            );
            $buscarRutNormalizado = preg_match('/[0-9]/', $palabra) === 1
                && $rutNormalizado !== '';

            $query->where(function (Builder $coincidencias) use ($palabra, $rutNormalizado, $buscarRutNormalizado): void {
                $coincidencias->where('nombre', 'like', '%'.$palabra.'%')
                    ->orWhere('correo', 'like', '%'.$palabra.'%')
                    ->orWhere('rut', 'like', '%'.$palabra.'%');

                if ($buscarRutNormalizado) {
                    $coincidencias->orWhereRaw(
                        "UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '')) LIKE ?",
                        ['%'.$rutNormalizado.'%']
                    );
                }
            });
        }

        return $query;
    }

    public function buscarPorRut(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'rut' => [
                'required',
                'string',
                'max:20',
                new RutChileno,
            ],
        ]);

        $cliente = $this->buscarClientePorRut($datos['rut']);

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

    public function buscarParaCatalogo(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'rut' => [
                'required',
                'string',
                'max:20',
                new RutChileno,
            ],
        ]);

        $cliente = $this->buscarClientePorRut($datos['rut']);

        if (! $cliente) {
            return response()->json([
                'encontrado' => false,
            ]);
        }

        return response()->json([
            'encontrado' => true,
            'cliente' => [
                'nombre' => $cliente->nombre,
                'correo' => $cliente->correo,
                'telefono' => $cliente->telefono,
                'direccion' => $cliente->direccion,
                'region' => $cliente->region,
                'comuna' => $cliente->comuna,
            ],
        ]);
    }

    private function buscarClientePorRut(string $rut): ?Cliente
    {
        $rutBuscado = strtoupper(
            preg_replace('/[^0-9kK]/', '', $rut) ?? ''
        );

        return Cliente::query()
            ->whereRaw(
                "UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '')) = ?",
                [$rutBuscado]
            )
            ->first();
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

    public function ventasPdf(int $id)
    {
        $cliente = Cliente::findOrFail($id);
        $ventas = $cliente->ventas()
            ->with('detalles.producto')
            ->orderByDesc('fecha')
            ->orderByDesc('id_venta')
            ->get();

        return Pdf::loadView('clientes.ventas-pdf', compact('cliente', 'ventas'))
            ->download('ventas-cliente-'.$cliente->id_cliente.'.pdf');
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
