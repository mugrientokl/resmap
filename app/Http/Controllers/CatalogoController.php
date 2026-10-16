<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\SolicitudWeb;
use App\Models\User;
use App\Notifications\SolicitudWebRecibida;
use App\Rules\RutChileno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CatalogoController extends Controller
{
    public function home()
    {
        $kitsSetsCategory = Categoria::where('nombre_categoria', 'KITS Y SETS')->first();

        return view('welcome', [
            'categorias' => Categoria::withCount('productos')->orderByDesc('productos_count')->limit(6)->get(),
            'kitsSetsCategory' => $kitsSetsCategory,
            'kitsYSets' => $kitsSetsCategory
                ? Producto::with('categoria')->where('id_categoria', $kitsSetsCategory->id_categoria)->orderByDesc('precio')->limit(8)->get()
                : collect(),
        ]);
    }

    public function index(Request $request)
    {
        $categorias = Categoria::withCount('productos')->orderBy('nombre_categoria')->get();
        $productos = Producto::with('categoria')
            ->when($request->filled('nombre'), fn ($query) => $query->where('nombre', 'like', '%'.$request->string('nombre')->toString().'%'))
            ->when($request->filled('categoria'), fn ($query) => $query->where('id_categoria', $request->integer('categoria')))
            ->orderBy('nombre')->paginate(24)->withQueryString();

        return view('catalogo.index', compact('categorias', 'productos'));
    }

    public function storeRequest(Request $request)
    {
        $data = $request->validate([
            'rut' => ['required', 'string', 'max:20', 'regex:/^[0-9]{7,8}-[0-9Kk]$/', new RutChileno],
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['nullable', 'email', 'max:255'],
            'prefijo_telefono' => ['required', 'regex:/^\+[1-9][0-9]{0,4}$/'],
            'telefono' => ['required', 'regex:/^[0-9]{8}$/'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:100'],
            'comuna' => ['required', 'string', 'max:100'],
            'detalles_productos' => ['required', 'array', 'min:1'],
            'detalles_productos.*.id_producto' => ['required', 'exists:productos,id_producto'],
            'detalles_productos.*.cantidad' => ['required', 'integer', 'min:1', 'max:99'],
        ], [
            'rut.required' => 'Escribe tu RUT sin puntos y con guion, por ejemplo: 12345678-5.',
            'rut.regex' => 'El RUT debe escribirse sin puntos y con guion, por ejemplo: 12345678-5.',
            'nombre.required' => 'Escribe tu nombre o razón social.',
            'correo.email' => 'Escribe un correo electrónico válido.',
            'telefono.required' => 'Escribe los 8 dígitos de tu teléfono.',
            'telefono.regex' => 'El teléfono debe contener exactamente 8 dígitos.',
            'detalles_productos.required' => 'Agrega al menos un repuesto al carrito.',
            'detalles_productos.min' => 'Agrega al menos un repuesto al carrito.',
        ]);

        $telefono = $data['prefijo_telefono'].$data['telefono'];

        $solicitud = DB::transaction(function () use ($data, $telefono): SolicitudWeb {
            $cliente = Cliente::updateOrCreate(['rut' => $data['rut']], [
                'nombre' => $data['nombre'], 'correo' => $data['correo'] ?? null,
                'telefono' => $telefono, 'direccion' => $data['direccion'] ?? null,
                'region' => $data['region'], 'comuna' => $data['comuna'], 'ciudad' => $data['comuna'],
            ]);

            return SolicitudWeb::create([
                'fecha' => now(), 'estado' => 'Pendiente', 'id_cliente' => $cliente->id_cliente,
                'detalles_productos' => $data['detalles_productos'],
            ]);
        });

        $cantidadesSolicitadas = collect($data['detalles_productos'])
            ->groupBy('id_producto')
            ->map(fn ($detalles): int => $detalles->sum(fn (array $detalle): int => (int) $detalle['cantidad']));
        $productos = Producto::whereIn('id_producto', $cantidadesSolicitadas->keys())->get()->keyBy('id_producto');
        $faltantes = $cantidadesSolicitadas->filter(fn (int $cantidad, $idProducto): bool => $cantidad > (int) ($productos->get($idProducto)?->stock ?? 0));

        User::whereIn('rol', ['Administrador', 'Vendedor'])->get()->each(function (User $user) use ($solicitud): void {
            try {
                $user->notify(new SolicitudWebRecibida($solicitud));
            } catch (\Throwable $exception) {
                Log::warning('No fue posible enviar la notificación de solicitud web.', [
                    'solicitud_id' => $solicitud->id_solicitud,
                    'usuario_id' => $user->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        });

        $mensaje = 'Solicitud enviada. El equipo de RESMAP se pondrá en contacto contigo.';
        if ($faltantes->isNotEmpty()) {
            $mensaje .= ' Algunos productos no tienen stock suficiente; la solicitud no reserva unidades y el plazo puede ser mayor.';
        }

        return redirect()->route('catalogo.index')->with('success', $mensaje);
    }
}
