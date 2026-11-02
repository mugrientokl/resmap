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
use App\Rules\RutChileno;
use App\Services\InventarioReservaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class CatalogoController extends Controller
{
    public function home()
    {
        $kitsSetsCategory = Categoria::where(
            'nombre_categoria',
            'KITS Y SETS'
        )->first();

        return view('welcome', [
            'categorias' => Categoria::withCount([
                'productos' => fn ($query) => $query->where('activo', true),
            ])
                ->orderByDesc('productos_count')
                ->limit(6)
                ->get(),

            'kitsSetsCategory' => $kitsSetsCategory,

            'kitsYSets' => $kitsSetsCategory
                ? Producto::with('categoria')
                    ->withSum('reservasActivas as stock_reservado', 'cantidad')
                    ->where('activo', true)
                    ->where('id_categoria', $kitsSetsCategory->id_categoria)
                    ->orderByDesc('precio')
                    ->limit(8)
                    ->get()
                : collect(),
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
            ->select('id_producto', 'nombre', 'id_categoria')
            ->orderBy('nombre')
            ->get();

        $productos = Producto::with('categoria')
            ->withSum('reservasActivas as stock_reservado', 'cantidad')
            ->where('activo', true)
            ->when($request->filled('nombre'), function ($query) use ($request): void {
                $palabras = preg_split(
                    '/\s+/u',
                    trim($request->string('nombre')->toString()),
                    -1,
                    PREG_SPLIT_NO_EMPTY
                );

                foreach ($palabras as $palabra) {
                    $query->where('nombre', 'like', '%'.$palabra.'%');
                }
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
            compact('categorias', 'productos', 'productosBuscador')
        );
    }

    public function storeRequest(
        Request $request,
        InventarioReservaService $reservas
    ) {
        $data = $request->validate([
            'rut' => [
                'required',
                'string',
                'max:20',
                'regex:/^[0-9]{7,8}-[0-9Kk]$/',
                new RutChileno,
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'email', 'max:255'],
            'prefijo_telefono' => [
                'required',
                'regex:/^\+[1-9][0-9]{0,4}$/',
            ],
            'telefono' => ['required', 'regex:/^[0-9]{8}$/'],
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
            'preferencia_cotizacion' => ['required', 'in:correo_whatsapp,solo_correo'],
            'destino_whatsapp' => [
                'exclude_unless:preferencia_cotizacion,correo_whatsapp',
                'required_if:preferencia_cotizacion,correo_whatsapp',
                'in:mismo_numero,otro_numero',
            ],
            'telefono_whatsapp_otro' => [
                'exclude_unless:destino_whatsapp,otro_numero',
                'required_if:destino_whatsapp,otro_numero',
                'digits_between:4,14',
            ],
            'prefijo_telefono_whatsapp' => [
                'exclude_unless:destino_whatsapp,otro_numero',
                'required_if:destino_whatsapp,otro_numero',
                'regex:/^\\+[1-9][0-9]{0,3}$/',
            ],
        ], [
            'rut.required' => 'Escribe tu RUT sin puntos y con guion, por ejemplo: 12345678-5.',
            'rut.regex' => 'El RUT debe escribirse sin puntos y con guion, por ejemplo: 12345678-5.',
            'nombre.required' => 'Escribe tu nombre o razón social.',
            'correo.email' => 'Escribe un correo electrónico válido.',
            'telefono.required' => 'Escribe los 8 dígitos de tu teléfono.',
            'telefono.regex' => 'El teléfono debe contener exactamente 8 dígitos.',
            'correo.required' => 'Escribe el correo donde recibirás tu cotización.',
            'preferencia_cotizacion.required' => 'Selecciona cómo deseas recibir tu cotización.',
            'destino_whatsapp.required_if' => 'Selecciona el número de WhatsApp de destino.',
            'telefono_whatsapp_otro.required_if' => 'Escribe el número de WhatsApp alternativo.',
            'telefono_whatsapp_otro.digits_between' => 'Ingresa un número local de 4 a 14 dígitos, sin el código de país.',
            'prefijo_telefono_whatsapp.required_if' => 'Selecciona el código de país del WhatsApp alternativo.',
            'prefijo_telefono_whatsapp.regex' => 'Selecciona un código de país válido.',
            'detalles_productos.required' => 'Agrega al menos un repuesto al carrito.',
            'detalles_productos.min' => 'Agrega al menos un repuesto al carrito.',
        ]);

        $telefono = $data['prefijo_telefono'].$data['telefono'];
        $telefonoWhatsApp = null;

        if ($data['preferencia_cotizacion'] === 'correo_whatsapp') {
            $telefonoWhatsApp = $data['destino_whatsapp'] === 'mismo_numero'
                ? $data['prefijo_telefono'].$data['telefono']
                : $data['prefijo_telefono_whatsapp'].$data['telefono_whatsapp_otro'];

            if (! preg_match('/^\\+[1-9][0-9]{6,14}$/', $telefonoWhatsApp)) {
                throw ValidationException::withMessages([
                    'telefono_whatsapp_otro' => 'Ingresa un número válido con el código de país seleccionado.',
                ]);
            }
        }

        $solicitud = DB::transaction(function () use (
            $data,
            $telefono,
            $telefonoWhatsApp,
            $reservas
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
                'nombre' => $data['nombre'],
                'correo' => $data['correo'] ?? null,
                'telefono' => $telefono,
                'telefono_whatsapp' => $telefonoWhatsApp,
                'direccion' => $data['direccion'] ?? null,
                'region' => $data['region'],
                'comuna' => $data['comuna'],
                'ciudad' => $data['comuna'],
            ];

            if ($telefonoWhatsApp !== null) {
                $datosCliente['consentimiento_whatsapp_at'] = now();
                $datosCliente['consentimiento_whatsapp_origen'] = 'catalogo';
            } else {
                $datosCliente['consentimiento_whatsapp_at'] = null;
                $datosCliente['consentimiento_whatsapp_origen'] = null;
            }

            $cliente = Cliente::updateOrCreate(
                ['rut' => $data['rut']],
                $datosCliente
            );

            $solicitud = SolicitudWeb::create([
                'fecha' => now(),
                'estado' => 'Pendiente',
                'id_cliente' => $cliente->id_cliente,
                'detalles_productos' => $data['detalles_productos'],
                'metodo_entrega' => $data['metodo_entrega'] ?? 'retiro',
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
            ->each(function (User $user) use (
                $notificacion,
                $solicitud
            ): void {
                try {
                    $user->notify($notificacion);
                } catch (TransportExceptionInterface $exception) {
                    Log::warning(
                        'La solicitud quedó registrada, pero falló el correo interno.',
                        [
                            'solicitud_id' => $solicitud->id_solicitud,
                            'usuario_id' => $user->id,
                            'error' => $exception->getMessage(),
                        ]
                    );
                } catch (\Throwable $exception) {
                    Log::warning(
                        'No fue posible enviar la notificación de solicitud web.',
                        [
                            'solicitud_id' => $solicitud->id_solicitud,
                            'usuario_id' => $user->id,
                            'error' => $exception->getMessage(),
                        ]
                    );
                }
            });

        return redirect()
            ->route('catalogo.index')
            ->with(
                'success',
                'Solicitud enviada. El equipo de RESMAP se pondrá en contacto contigo.'
            );
    }
}
