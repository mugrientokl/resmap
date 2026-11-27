<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Producto;
use App\Models\SolicitudWeb;
use App\Models\User;
use App\Notifications\NuevaCotizacionPendiente;
use App\Notifications\SolicitudWebRecibida;
use App\Rules\CorreoConDominio;
use App\Rules\RutChileno;
use App\Services\ActualizarDatosCliente;
use App\Services\InventarioReservaService;
use App\Support\Rut;
use App\Support\TextoPersona;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogoController extends Controller
{
    public function home()
    {
        return view('welcome', [
            'productosOferta' => Producto::with('categoria')
                ->withSum('reservasActivas as stock_reservado', 'cantidad')
                ->enOferta()
                ->conStockDisponible()
                ->orderByDesc('porcentaje_descuento')
                ->limit(12)
                ->get(),
            'productosStock' => Producto::query()
                ->where('activo', true)
                ->select('id_producto', 'stock', 'precio', 'precio_oferta', 'porcentaje_descuento', 'oferta_inicio_at', 'oferta_fin_at', 'unidad')
                ->withSum('reservasActivas as stock_reservado', 'cantidad')
                ->get(),
        ]);
    }

    public function index(Request $request)
    {
        $categorias = Categoria::withCount([
            'productos' => fn ($query) => $query->where('activo', true),
        ])
            ->orderBy('nombre_categoria')
            ->get();

        $productosBuscador = Producto::query()
            ->where('activo', true)
            ->select('id_producto', 'nombre', 'codigo_barra', 'codigo_origen', 'id_categoria', 'stock', 'precio', 'precio_oferta', 'porcentaje_descuento', 'oferta_inicio_at', 'oferta_fin_at', 'unidad')
            ->withSum('reservasActivas as stock_reservado', 'cantidad')
            ->orderBy('nombre')
            ->get();

        $productos = Producto::with('categoria')
            ->withSum('reservasActivas as stock_reservado', 'cantidad')
            ->where('activo', true)
            ->when($request->filled('nombre'), function ($query) use ($request): void {
                $query->buscarCoincidencias($request->string('nombre')->trim()->toString());
            })
            ->when($request->filled('categoria'), function ($query) use ($request): void {
                $query->where(
                    'id_categoria',
                    $request->integer('categoria')
                );
            })
            ->orderBy('nombre')
            ->paginate(24)
            ->withQueryString();

        return view(
            'catalogo.index',
            [
                'categorias' => $categorias,
                'productos' => $productos,
                'productosBuscador' => $productosBuscador,
                'productosStock' => $productosBuscador,
            ]
        );
    }

    public function storeRequest(
        Request $request,
        InventarioReservaService $reservas,
        ActualizarDatosCliente $actualizarCliente,
    ) {
        $request->merge([
            'rut' => Rut::normalizar((string) $request->input('rut')),
            'nombre' => TextoPersona::normalizar($request->input('nombre')),
            'contacto' => TextoPersona::normalizar($request->input('contacto')),
        ]);

        $data = $request->validate([
            'tipo_cliente' => ['nullable', 'in:Persona,Empresa'],
            'rut' => [
                'required',
                'string',
                'max:20',
                new RutChileno,
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'giro' => [
                Rule::requiredIf($request->input('tipo_cliente') === 'Empresa'),
                'nullable',
                'string',
                'max:255',
            ],
            'contacto' => ['required', 'string', 'max:255'],
            'cargo' => ['nullable', 'string', 'max:255'],
            'correo' => ['required', 'email', new CorreoConDominio, 'max:255'],
            'prefijo_telefono' => ['required', 'in:+56,+569'],
            'telefono' => ['required', 'regex:/^[0-9]{8,9}$/'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:100'],
            'comuna' => ['required', 'string', 'max:100'],
            'detalles_productos' => ['required', 'array', 'min:1'],
            'detalles_productos.*.id_producto' => [
                'required',
                'exists:productos,id_producto',
            ],
            'detalles_productos.*.cantidad' => [
                'required',
                'integer',
                'min:1',
                'max:99',
            ],
            'metodo_entrega' => ['nullable', 'in:retiro,despacho'],
            'direccion_calle' => [
                'required_if:metodo_entrega,despacho',
                'nullable',
                'string',
                'max:255',
            ],
            'direccion_numero' => [
                'required_if:metodo_entrega,despacho',
                'nullable',
                'string',
                'max:50',
            ],
            'direccion_comuna' => [
                'required_if:metodo_entrega,despacho',
                'nullable',
                'string',
                'max:100',
            ],
            'direccion_ciudad' => [
                'required_if:metodo_entrega,despacho',
                'nullable',
                'string',
                'max:100',
            ],
            'direccion_region' => [
                'required_if:metodo_entrega,despacho',
                'nullable',
                'string',
                'max:100',
            ],
            'direccion_referencias' => ['nullable', 'string', 'max:1000'],
        ], [
            'rut.required' => 'Escribe tu RUT o RUN, por ejemplo: 12.345.678-5.',
            'nombre.required' => 'Escribe tu nombre o razón social.',
            'correo.email' => 'Escribe un correo electrónico válido.',
            'telefono.required' => 'Escribe tu teléfono chileno.',
            'telefono.regex' => 'El teléfono debe contener los dígitos nacionales.',
            'correo.required' => 'Escribe el correo donde recibirás tu cotización.',
            'detalles_productos.required' => 'Selecciona al menos un repuesto para cotizar.',
            'detalles_productos.min' => 'Selecciona al menos un repuesto para cotizar.',
        ]);

        $telefono = $data['prefijo_telefono'].$data['telefono'];
        validator(['telefono' => $telefono], [
            'telefono' => ['regex:/^\+56[2-9][0-9]{8}$/'],
        ], ['telefono.regex' => 'Ingresa un teléfono chileno válido.'])->validate();

        $solicitud = DB::transaction(function () use (
            $data,
            $telefono,
            $reservas,
            $actualizarCliente,
        ): SolicitudWeb {
            $cantidadesSolicitadas = collect($data['detalles_productos'])
                ->groupBy(
                    fn (array $detalle): int => (int) $detalle['id_producto']
                )
                ->map(
                    fn ($detalles): int => $detalles->sum(
                        fn (array $detalle): int => (int) $detalle['cantidad']
                    )
                );

            $productos = Producto::query()
                ->whereIn('id_producto', $cantidadesSolicitadas->keys())
                ->orderBy('id_producto')
                ->lockForUpdate()
                ->get()
                ->keyBy('id_producto');

            $inactivos = $cantidadesSolicitadas->keys()->filter(
                fn (int|string $idProducto): bool => ! $productos->get($idProducto)?->activo
            );

            if ($inactivos->isNotEmpty()) {
                $nombres = $inactivos->map(
                    fn (int|string $idProducto): string => $productos->get($idProducto)?->nombre
                        ?? 'Producto #'.$idProducto
                );

                throw ValidationException::withMessages([
                    'detalles_productos' => 'No se pueden solicitar productos inactivos: '
                        .$nombres->implode(', ').'.',
                ]);
            }

            $faltantes = $cantidadesSolicitadas->filter(
                fn (int $cantidad, int|string $idProducto): bool => $cantidad > (int) (
                    $productos->get($idProducto)?->stock ?? 0
                )
            );

            if ($faltantes->isNotEmpty()) {
                $mensajes = $faltantes->map(function (
                    int $cantidad,
                    int|string $idProducto
                ) use ($productos): string {
                    $producto = $productos->get($idProducto);
                    $nombre = $producto?->nombre ?? 'Producto #'.$idProducto;
                    $stock = (int) ($producto?->stock ?? 0);

                    return "Stock insuficiente para {$nombre}: solicitaste {$cantidad} y quedan {$stock}.";
                });

                throw ValidationException::withMessages([
                    'detalles_productos' => $mensajes->implode(' '),
                ]);
            }

            $datosCliente = [
                'tipo_cliente' => $data['tipo_cliente'] ?? 'Persona',
                'rut' => $data['rut'],
                'nombre' => $data['nombre'],
                'contacto' => $data['contacto'],
                'giro' => $data['giro'] ?? null,
                'correo' => $data['correo'] ?? null,
                'telefono' => $telefono,
                'telefono_whatsapp' => $telefono,
                'direccion' => $data['direccion'] ?? null,
                'region' => $data['region'],
                'comuna' => $data['comuna'],
                'ciudad' => $data['comuna'],
            ];

            $clienteExistente = Cliente::query()->where('rut', $data['rut'])->lockForUpdate()->first();
            if ($clienteExistente === null) {
                $datosCliente['consentimiento_whatsapp_at'] = now();
                $datosCliente['consentimiento_whatsapp_origen'] = 'catalogo';
            }

            $cliente = $actualizarCliente->actualizarDesdeSolicitud(
                $datosCliente,
                'la solicitud de cotización web'
            );

            $solicitud = SolicitudWeb::create([
                'fecha' => now(),
                'estado' => 'Pendiente',
                'id_cliente' => $cliente->id_cliente,
                'datos_cliente' => $datosCliente,
                'contacto' => $data['contacto'],
                'cargo' => $data['cargo'] ?? null,
                'detalles_productos' => $data['detalles_productos'],
                'metodo_entrega' => $data['metodo_entrega'] ?? null,
                'direccion_calle' => $data['direccion_calle'] ?? null,
                'direccion_numero' => $data['direccion_numero'] ?? null,
                'direccion_comuna' => $data['direccion_comuna'] ?? null,
                'direccion_ciudad' => $data['direccion_ciudad'] ?? null,
                'direccion_region' => $data['direccion_region'] ?? null,
                'direccion_referencias' => $data['direccion_referencias'] ?? null,
            ]);

            $cotizacion = Cotizacion::create([
                'solicitud_id' => $solicitud->id_solicitud,
                'estado' => 'pendiente_revision',
            ]);

            $reservas->reservar(
                $cotizacion,
                $cantidadesSolicitadas->all()
            );

            return $solicitud;
        });

        $solicitud->load(['cliente', 'cotizacion.reservas']);

        $notificacion = $solicitud->tipo_solicitud === 'pedido'
            ? new NuevaCotizacionPendiente($solicitud->cotizacion)
            : new SolicitudWebRecibida($solicitud);

        User::whereIn('rol', ['Administrador', 'Vendedor'])
            ->get()
            ->filter(fn (User $user): bool => $user->tienePermiso('cotizaciones.ver'))
            ->each(function (User $user) use ($notificacion, $solicitud): void {
                try {
                    $user->notify($notificacion->afterCommit());
                } catch (\Throwable $exception) {
                    Log::warning('La solicitud quedó registrada, pero falló el correo interno.', [
                        'solicitud_id' => $solicitud->id_solicitud,
                        'usuario_id' => $user->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            });

        return redirect()
            ->route('catalogo.index')
            ->with(
                'success',
                'Recibimos tu solicitud. Te enviaremos una cotización que indicará su fecha límite de validez.'
            );
    }
}
