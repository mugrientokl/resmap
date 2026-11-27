<?php

namespace App\Http\Controllers;

use App\Jobs\EnviarCotizacionEmail;
use App\Jobs\EnviarCotizacionWhatsApp;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\CotizacionVersion;
use App\Models\DetalleVenta;
use App\Models\EventoFacturacion;
use App\Models\EventoReserva;
use App\Models\InventarioMovimiento;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\ReservaInventario;
use App\Models\SolicitudWeb;
use App\Models\User;
use App\Models\Venta;
use App\Notifications\OrdenCompraRecibida;
use App\Rules\CorreoConDominio;
use App\Rules\RutChileno;
use App\Services\CotizacionPdf;
use App\Services\Facturacion\ProveedorFacturacion;
use App\Services\InventarioReservaService;
use App\Services\ResumenInternoVentaPdf;
use App\Services\VentaCotizacionService;
use App\Services\WhatsAppCloudApi;
use App\Support\ConfiguracionSistema;
use App\Support\Rut;
use App\Support\TelefonoChileno;
use App\Support\TextoPersona;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class CotizacionController extends Controller
{
    public function crearVersion(Request $request, SolicitudWeb $solicitud, InventarioReservaService $reservas): RedirectResponse
    {
        $datos = $request->validate([
            'costo_despacho' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'items' => [Rule::requiredIf($request->boolean('items_edicion')), 'array', 'min:1'],
            'items.*.id_producto' => ['required_with:items', 'integer', 'distinct', 'exists:productos,id_producto'],
            'items.*.cantidad' => ['required_with:items', 'integer', 'min:1', 'max:99999'],
        ]);

        abort_if($solicitud->tipo_solicitud === 'servicio', 422, 'Las solicitudes de servicio no generan cotizaciones de repuestos.');

        DB::transaction(function () use ($request, $solicitud, $datos, $reservas): void {
            $solicitudBloqueada = SolicitudWeb::query()
                ->whereKey($solicitud->id_solicitud)
                ->lockForUpdate()
                ->firstOrFail();
            $datosCliente = $solicitudBloqueada->snapshotDatosCliente();
            if ($datosCliente === []) {
                throw ValidationException::withMessages([
                    'cotizacion' => 'No se puede cotizar esta solicitud porque no tiene datos históricos verificables del cliente.',
                ]);
            }

            $cotizacion = Cotizacion::query()
                ->where('solicitud_id', $solicitudBloqueada->id_solicitud)
                ->lockForUpdate()
                ->first();
            if (! $cotizacion) {
                $cotizacion = Cotizacion::create([
                    'solicitud_id' => $solicitudBloqueada->id_solicitud,
                    'estado' => 'pendiente_revision',
                ]);
            }

            if (in_array($cotizacion->estado, ['aceptada', 'rechazada', 'cancelada'], true)) {
                throw ValidationException::withMessages(['cotizacion' => 'La cotización ya está cerrada.']);
            }

            $items = collect($datos['items'] ?? $solicitudBloqueada->detalles_productos ?? [])
                ->groupBy('id_producto')
                ->map(fn ($detalles): int => $detalles->sum(fn (array $detalle): int => (int) $detalle['cantidad']));
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['cotizacion' => 'La solicitud no contiene productos cotizables.']);
            }

            $productos = Producto::query()
                ->whereIn('id_producto', $items->keys())
                ->orderBy('id_producto')
                ->lockForUpdate()
                ->get()
                ->keyBy('id_producto');
            $reservadosPorProducto = $reservas->cantidadReservadaPorProducto(
                $items->keys()->all(),
                $cotizacion->id_cotizacion,
            );

            foreach ($items as $productoId => $cantidad) {
                if (! $productos->has($productoId) || ! $productos->get($productoId)->activo) {
                    throw ValidationException::withMessages(['cotizacion' => 'Uno de los productos ya no está disponible.']);
                }

                $producto = $productos->get($productoId);
                $disponible = max(0, (int) $producto->stock - (int) ($reservadosPorProducto[$productoId] ?? 0));

                if ($cantidad > $disponible) {
                    throw ValidationException::withMessages([
                        'detalles_productos' => "Stock insuficiente para {$producto->nombre}: solicitaste {$cantidad} y quedan {$disponible} disponibles.",
                    ]);
                }
            }

            $costoDespacho = ($solicitudBloqueada->metodo_entrega ?? 'retiro') === 'despacho'
                ? (float) ($datos['costo_despacho'] ?? config('services.cotizaciones.costo_despacho', 0))
                : 0;
            $total = 0;
            foreach ($items as $productoId => $cantidad) {
                $total += (float) $productos->get($productoId)->precio * $cantidad;
            }
            $neto = round($total / 1.19, 2);
            $iva = round($total - $neto, 2);
            $numeroVersion = (int) $cotizacion->ultima_version + 1;
            $vigenteHasta = now()->addDays((int) config('services.cotizaciones.vigencia_dias', 7));

            $cotizacion->versiones()
                ->where('numero_version', '<', $numeroVersion)
                ->update([
                    'estado' => 'reemplazada',
                    'token_cliente_hash' => null,
                    'token_expira_at' => null,
                    'email_estado' => DB::raw("CASE WHEN email_estado = 'en_cola' THEN 'cancelado' ELSE email_estado END"),
                    'whatsapp_estado' => DB::raw("CASE WHEN whatsapp_estado = 'en_cola' THEN 'cancelado' ELSE whatsapp_estado END"),
                ]);

            $version = CotizacionVersion::create([
                'cotizacion_id' => $cotizacion->id_cotizacion,
                'revisada_por' => $request->user()->id,
                'numero_version' => $numeroVersion,
                'estado' => 'borrador',
                'neto' => $neto,
                'iva' => $iva,
                'costo_despacho' => $costoDespacho,
                'total' => $total + $costoDespacho,
                'metodo_entrega' => $solicitudBloqueada->metodo_entrega ?? 'por_coordinar',
                'calle' => $solicitudBloqueada->direccion_calle,
                'numero' => $solicitudBloqueada->direccion_numero,
                'comuna' => $solicitudBloqueada->direccion_comuna,
                'ciudad' => $solicitudBloqueada->direccion_ciudad,
                'region' => $solicitudBloqueada->direccion_region,
                'referencias' => $solicitudBloqueada->direccion_referencias,
                'vigente_hasta' => $vigenteHasta,
                'datos_cliente' => $datosCliente,
            ]);

            foreach ($items as $productoId => $cantidad) {
                $producto = $productos->get($productoId);
                CotizacionDetalle::create([
                    'version_id' => $version->id_version,
                    'producto_id' => $producto->id_producto,
                    'nombre_producto' => $producto->nombre,
                    'codigo_producto' => $producto->codigo_origen ?: $producto->codigo_barra,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $producto->precioVigente(),
                    'subtotal' => $producto->precioVigente() * $cantidad,
                ]);
            }

            $reservas->liberar($cotizacion, 'nueva_version', $request->user()->id);
            $reservas->reservar($cotizacion, $items->all(), $version->id_version);

            $cotizacion->update([
                'estado' => 'borrador',
                'ultima_version' => $numeroVersion,
                'creada_por' => $request->user()->id,
                'revisada_at' => now(),
            ]);
        });

        return redirect()->route('solicitudes.show', $solicitud)->with('success', 'Se creó la versión de cotización.');
    }

    public function enviar(CotizacionVersion $version): RedirectResponse
    {
        $version->load(['cotizacion.solicitud.cliente', 'detalles']);
        abort_unless($version->numero_version === $version->cotizacion->ultima_version, 422, 'Esta versión fue reemplazada por una nueva cotización y ya no tiene validez.');
        abort_unless($version->estado === 'borrador', 422, 'Solo se pueden enviar cotizaciones en borrador.');
        abort_if($version->vigente_hasta->isPast(), 422, 'La cotización ya venció.');

        $version->update([
            'estado' => 'enviada',
            'token_cliente_hash' => null,
            'token_expira_at' => null,
            'enviada_at' => now(),
        ]);
        $cotizacion = $version->cotizacion;
        $cliente = $cotizacion->solicitud->cliente;
        $clienteSnapshot = Cliente::desdeSnapshot($version->snapshotDatosCliente());
        $rutaPdf = URL::temporarySignedRoute(
            'cotizaciones.pdf.externo',
            $version->vigente_hasta,
            ['version' => $version->id_version],
            absolute: false,
        );
        $urlPdf = rtrim((string) config('app.url'), '/').$rutaPdf;
        $avisos = [];

        $enviarCorreo = filled($clienteSnapshot->correo);
        if ($enviarCorreo) {
            $version->update(['email_estado' => 'en_cola']);
        } else {
            $version->update(['email_estado' => 'sin_correo']);
            $avisos[] = 'El cliente no tiene correo registrado.';
        }

        $rechazoWhatsApp = $cliente->consentimiento_whatsapp_origen === 'whatsapp_opt_out';
        $tieneConsentimientoWhatsApp = $cliente->consentimiento_whatsapp_at !== null
            && ! $rechazoWhatsApp;
        $telefonoWhatsApp = $cliente->telefono_whatsapp
            ?? ($tieneConsentimientoWhatsApp ? $cliente->telefono : null);
        $whatsappConfigurado = (bool) config('services.whatsapp.enabled')
            && filled(config('services.whatsapp.api_version'))
            && filled(config('services.whatsapp.phone_number_id'))
            && filled(config('services.whatsapp.access_token'))
            && filled(config('services.whatsapp.quote_template'));
        $enviarWhatsApp = $telefonoWhatsApp && $tieneConsentimientoWhatsApp && $whatsappConfigurado;

        if ($enviarWhatsApp) {
            $version->update(['whatsapp_estado' => 'en_cola']);
        } else {
            $version->update([
                'whatsapp_estado' => $rechazoWhatsApp || ! $tieneConsentimientoWhatsApp
                    ? 'no_autorizado'
                    : (! $telefonoWhatsApp ? 'sin_telefono' : 'no_configurado'),
            ]);
            if ($telefonoWhatsApp && $tieneConsentimientoWhatsApp && ! $whatsappConfigurado) {
                $avisos[] = 'WhatsApp no está configurado para envíos.';
            }
        }

        $cotizacion->update(['estado' => 'enviada']);

        if ($enviarCorreo) {
            try {
                Bus::dispatch((new EnviarCotizacionEmail($version->id_version, $urlPdf))->afterCommit());
            } catch (\Throwable $exception) {
                $version->update(['email_estado' => 'fallido']);
                Log::warning('No fue posible encolar el correo de la cotización.', [
                    'version_id' => $version->id_version,
                    'error' => $exception->getMessage(),
                ]);
                $avisos[] = 'No fue posible poner el correo en cola.';
            }
        }
        if ($enviarWhatsApp) {
            try {
                Bus::dispatch((new EnviarCotizacionWhatsApp(
                    $version->id_version,
                    $telefonoWhatsApp,
                    $urlPdf,
                    Str::after($rutaPdf, '/cotizaciones/'),
                ))->afterCommit());
            } catch (\Throwable $exception) {
                $version->update(['whatsapp_estado' => 'fallido']);
                Log::warning('No fue posible encolar el WhatsApp de la cotización.', [
                    'version_id' => $version->id_version,
                    'error' => $exception->getMessage(),
                ]);
                $avisos[] = 'No fue posible poner WhatsApp en cola.';
            }
        }

        return redirect()->route('solicitudes.show', $cotizacion->solicitud_id)
            ->with('success', 'La cotización quedó en cola para envío. Puedes revisar el estado de cada canal.')
            ->with('warning', implode(' ', $avisos));
    }

    public function pdf(CotizacionVersion $version, CotizacionPdf $pdf)
    {
        $version->load(['cotizacion.solicitud.cliente', 'detalles']);

        return response($pdf->generar($version), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="cotizacion-'.$version->cotizacion_id.'-v'.$version->numero_version.'.pdf"',
        ]);
    }

    public function estadoEnvio(CotizacionVersion $version): JsonResponse
    {
        $version->loadMissing('cotizacion:id_cotizacion,ultima_version');

        return response()->json(
            $version->estadosEnvio($version->numero_version === $version->cotizacion->ultima_version),
            headers: ['Cache-Control' => 'no-store, private'],
        );
    }

    public function registrarRespuesta(Request $request, CotizacionVersion $version, InventarioReservaService $reservas): RedirectResponse
    {
        $datos = $request->validate([
            'decision' => ['required', 'in:aceptada,rechazada,cambios_solicitados'],
            'comentarios' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $version, $reservas, $datos): void {
            $cotizacion = Cotizacion::query()->whereKey($version->cotizacion_id)->lockForUpdate()->firstOrFail();
            $version = CotizacionVersion::query()->whereKey($version->id_version)->lockForUpdate()->firstOrFail();
            if ($cotizacion->estado !== 'enviada' || $version->estado !== 'enviada'
                || $version->numero_version !== $cotizacion->ultima_version) {
                throw ValidationException::withMessages(['cotizacion' => 'Solo se puede registrar una respuesta para la última versión enviada.']);
            }
            if ($version->vigente_hasta->isPast()) {
                throw ValidationException::withMessages(['cotizacion' => 'La cotización venció; prepara una nueva versión.']);
            }
            $version->update([
                'estado' => $datos['decision'],
                'respuesta_registrada_por' => $request->user()->id,
                'aceptada_at' => $datos['decision'] === 'aceptada' ? now() : null,
                'rechazada_at' => $datos['decision'] === 'rechazada' ? now() : null,
                'cambios_solicitados_at' => $datos['decision'] === 'cambios_solicitados' ? now() : null,
                'token_cliente_hash' => null,
                'token_expira_at' => null,
            ]);
            $version->cancelarEnviosPendientes();
            $cotizacion->update([
                'estado' => $datos['decision'],
                'comentarios_cliente' => $datos['comentarios'] ?? null,
            ]);
            if ($datos['decision'] === 'rechazada') {
                $reservas->liberar($cotizacion, 'cotizacion_rechazada', $request->user()->id);
            }
        });

        return match ($datos['decision']) {
            'aceptada' => back()->with('success', 'Se registró la aceptación de la cotización.'),
            'rechazada' => back()->with('rejected', 'Se registró el rechazo de la cotización.'),
            'cambios_solicitados' => back()->with('warning', 'Se registraron los cambios solicitados para la cotización.'),
        };
    }

    public function convertirEnVenta(Request $request, CotizacionVersion $version, VentaCotizacionService $servicio): RedirectResponse
    {
        $datos = $request->validateWithBag('pagoVenta', [
            'estado_pago' => ['required', 'in:pagado,pendiente'],
            'medio_pago' => ['required_if:estado_pago,pagado', 'nullable', 'in:Efectivo,Transferencia,Tarjeta'],
        ], [
            'estado_pago.required' => 'Selecciona el estado del pago.',
            'estado_pago.in' => 'Selecciona pagada o pendiente de pago.',
            'medio_pago.required_if' => 'Selecciona el medio de pago para registrar la venta como pagada.',
            'medio_pago.in' => 'Selecciona un medio de pago válido.',
        ]);

        DB::transaction(function () use ($request, $version, $servicio, $datos): void {
            $cotizacion = Cotizacion::query()->whereKey($version->cotizacion_id)->lockForUpdate()->firstOrFail();
            $version = CotizacionVersion::query()->whereKey($version->id_version)->lockForUpdate()->firstOrFail();
            if ($cotizacion->estado !== 'aceptada' || $version->estado !== 'aceptada'
                || $version->numero_version !== $cotizacion->ultima_version) {
                throw ValidationException::withMessages(['cotizacion' => 'Solo se puede convertir la última versión aceptada.']);
            }
            $venta = $this->crearVentaB2B($version, $request->user()->id);
            $yaCompletada = $venta->stock_descontado_at !== null;
            if ($yaCompletada && $venta->estado_pago !== $datos['estado_pago']) {
                throw ValidationException::withMessages(['estado_pago' => 'La venta ya está registrada. Usa la acción de confirmar pago total para cerrar un saldo pendiente.']);
            }
            $venta = $servicio->completar($venta, $request->user()->id);
            if (! $yaCompletada && $datos['estado_pago'] === 'pagado') {
                $servicio->confirmarPagoTotal($venta, $datos['medio_pago']);
            }
        });

        return back()->with('success', 'La venta quedó registrada y el stock descontado.');
    }

    public function confirmarPago(Request $request, Venta $venta, VentaCotizacionService $servicio): RedirectResponse
    {
        $datos = $request->validateWithBag('pagoVenta', [
            'medio_pago' => ['required', 'in:Efectivo,Transferencia,Tarjeta'],
        ], [
            'medio_pago.required' => 'Selecciona el medio de pago recibido.',
            'medio_pago.in' => 'Selecciona un medio de pago válido.',
        ]);
        $servicio->confirmarPagoTotal($venta, $datos['medio_pago']);

        return back()->with('success', 'El pago total quedó confirmado. La cotización ya no tiene saldo pendiente.');
    }

    public function generarResumen(Request $request, Venta $venta, ResumenInternoVentaPdf $resumen): RedirectResponse|Response
    {
        abort_unless(($venta->cotizacion_version_id !== null || $venta->solicitudServicio()->exists())
            && $venta->estado_venta === 'confirmada'
            && $venta->stock_descontado_at !== null, 403);
        $actividades = config('services.cotizaciones.actividades_emisor');
        $request->merge(['rut' => Rut::normalizar((string) $request->input('rut'))]);
        $request->merge([
            'nombre' => TextoPersona::normalizar($request->input('nombre')),
            'contacto' => TextoPersona::normalizar($request->input('contacto')),
            'razon_social' => TextoPersona::normalizar($request->input('razon_social')),
        ]);
        $datos = $request->validate([
            'tipo_documento' => ['required', 'string', 'in:Boleta Electrónica,Factura Electrónica'],
            'medio_pago' => ['nullable', 'string', 'in:Efectivo,Transferencia,Tarjeta,Crédito'],
            'forma_pago' => ['nullable', 'string', 'in:Contado,Crédito'],
            'acteco_emisor' => ['required', Rule::in(array_keys($actividades))],
            'tipo_venta' => ['required', Rule::in(['del_giro'])],
            'nombre' => ['required', 'string', 'max:255'],
            'rut' => ['required', 'string', 'max:20', new RutChileno],
            'giro' => [
                Rule::requiredIf($request->input('tipo_documento') === 'Factura Electrónica'),
                'nullable',
                'string',
                'max:255',
            ],
            'direccion' => [
                Rule::requiredIf($request->input('tipo_documento') === 'Factura Electrónica'),
                'nullable',
                'string',
                'max:255',
            ],
            'region' => [
                Rule::requiredIf($request->input('tipo_documento') === 'Factura Electrónica'),
                'nullable',
                'string',
                'max:100',
            ],
            'comuna' => [
                Rule::requiredIf($request->input('tipo_documento') === 'Factura Electrónica'),
                'nullable',
                'string',
                'max:100',
            ],
            'ciudad' => [
                Rule::requiredIf($request->input('tipo_documento') === 'Factura Electrónica'),
                'nullable',
                'string',
                'max:100',
            ],
            'contacto' => ['required', 'string', 'max:255'],
            'tipo_compra' => ['required', Rule::in(['del_giro'])],
            'correo' => ['required', 'email', new CorreoConDominio, 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'numero_oc' => [Rule::requiredIf($venta->cotizacion_version_id !== null), 'nullable', 'string', 'max:100'],
        ]);
        $datos['giro_emisor'] = $actividades[$datos['acteco_emisor']];
        $datos['forma_pago'] = $datos['forma_pago'] ?? 'Contado';
        $datos['medio_pago'] = $datos['medio_pago'] ?? (
            in_array($venta->medio_pago, ['Efectivo', 'Transferencia', 'Tarjeta', 'Crédito'], true)
                ? $venta->medio_pago
                : 'Efectivo'
        );
        if ($venta->cotizacion_version_id === null) {
            $venta->update([
                'tipo_documento' => $datos['tipo_documento'],
                'medio_pago' => $datos['medio_pago'],
                'datos_resumen' => $datos,
            ]);

            return $resumen->descargar($venta);
        }

        DB::transaction(function () use ($venta, $datos, $resumen): void {
            $venta = Venta::query()->whereKey($venta->id_venta)->lockForUpdate()->firstOrFail();
            if ($venta->resumen_interno_pdf !== null || ! empty($venta->datos_resumen)) {
                throw ValidationException::withMessages(['resumen' => 'El resumen interno ya fue generado y no puede modificarse.']);
            }

            $venta->datos_resumen = $datos;
            $venta->tipo_documento = $datos['tipo_documento'];
            $venta->medio_pago = $datos['medio_pago'];
            $venta->resumen_interno_pdf = base64_encode($resumen->generar($venta));
            $venta->save();
        });

        return back()->with('success', 'Resumen interno generado. Puedes descargarlo desde el detalle de la cotización.');
    }

    public function descargarOrdenCompra(OrdenCompra $ordenCompra): StreamedResponse
    {
        abort_unless($ordenCompra->archivo_path !== null, 404);
        abort_unless(Storage::disk('local')->exists($ordenCompra->archivo_path), 404);

        $extension = pathinfo($ordenCompra->archivo_path, PATHINFO_EXTENSION) ?: 'bin';

        return Storage::disk('local')->download(
            $ordenCompra->archivo_path,
            'orden-compra-'.($ordenCompra->numero ?: $ordenCompra->id_orden_compra).'.'.$extension,
        );
    }

    public function verOrdenCompra(OrdenCompra $ordenCompra): BinaryFileResponse
    {
        abort_unless($ordenCompra->archivo_path !== null, 404);
        abort_unless(Storage::disk('local')->exists($ordenCompra->archivo_path), 404);

        $extension = strtolower(pathinfo($ordenCompra->archivo_path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => abort(404),
        };
        $nombreArchivo = 'orden-compra-'.Str::slug($ordenCompra->numero ?: (string) $ordenCompra->id_orden_compra).'.'.$extension;

        return response()->file(Storage::disk('local')->path($ordenCompra->archivo_path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$nombreArchivo.'"',
        ]);
    }

    public function pdfPublico(CotizacionVersion $version, CotizacionPdf $pdf)
    {
        $version->load(['cotizacion.solicitud.cliente', 'detalles']);
        abort_if(
            $version->estado === 'reemplazada' || $version->numero_version < $version->cotizacion->ultima_version,
            410,
            'Esta versión fue reemplazada por una nueva cotización y ya no tiene validez.',
        );
        abort_if($version->vigente_hasta->isPast(), 410, 'La cotización ya no está vigente.');

        return response($pdf->generar($version), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="cotizacion-'.$version->cotizacion_id.'-v'.$version->numero_version.'.pdf"',
        ]);
    }

    /**
     * La plantilla aprobada de WhatsApp antepone el texto literal «{{1}}» al identificador.
     */
    public function mostrarRespuesta(string $version, string $token)
    {
        $version = (int) preg_replace('/\D+/', '', str_replace('{{1}}', '', $version));
        $cotizacionVersion = CotizacionVersion::with([
            'cotizacion.solicitud.cliente',
            'detalles',
        ])->findOrFail($version);
        if (! $this->tokenValido($cotizacionVersion, $token)) {
            return response()->view('errors.system', [
                'status' => 410,
                'title' => 'Este enlace ya no está disponible',
                'message' => 'La cotización pudo haber vencido o ya fue respondida. Si necesitas revisarla, comunícate con RESMAP.',
            ], 410);
        }

        return view('cotizaciones.responder', [
            'version' => $cotizacionVersion,
            'cliente' => Cliente::desdeSnapshot($cotizacionVersion->snapshotDatosCliente()),
            'token' => $token,
        ]);
    }

    public function responder(
        Request $request,
        int $version,
        string $token,
        InventarioReservaService $reservas,
    ): RedirectResponse {
        if (is_string($request->input('telefono'))) {
            $request->merge(['telefono' => TelefonoChileno::normalizar($request->input('telefono'))]);
        }

        $datos = $request->validate([
            'telefono' => ['required', 'string', 'regex:/^\+56[2-9][0-9]{8}$/'],
            'decision' => ['required', 'in:aceptar,rechazar,cambios'],
            'comentarios' => ['nullable', 'string', 'max:2000'],
            'metodo_entrega' => ['required_if:decision,aceptar', 'in:retiro,despacho'],
            'calle' => [
                Rule::requiredIf(fn (): bool => $request->input('decision') === 'aceptar'
                    && $request->input('metodo_entrega') === 'despacho'),
                'nullable',
                'string',
                'max:255',
            ],
            'numero' => [
                Rule::requiredIf(fn (): bool => $request->input('decision') === 'aceptar'
                    && $request->input('metodo_entrega') === 'despacho'),
                'nullable',
                'string',
                'max:50',
            ],
            'comuna' => [
                Rule::requiredIf(fn (): bool => $request->input('decision') === 'aceptar'
                    && $request->input('metodo_entrega') === 'despacho'),
                'nullable',
                'string',
                'max:100',
            ],
            'ciudad' => [
                Rule::requiredIf(fn (): bool => $request->input('decision') === 'aceptar'
                    && $request->input('metodo_entrega') === 'despacho'),
                'nullable',
                'string',
                'max:100',
            ],
            'region' => [
                Rule::requiredIf(fn (): bool => $request->input('decision') === 'aceptar'
                    && $request->input('metodo_entrega') === 'despacho'),
                'nullable',
                'string',
                'max:100',
            ],
            'referencias' => ['nullable', 'string', 'max:1000'],
            'modalidad_oc' => ['nullable', 'in:automatica,propia'],
            'autorizar_oc_automatica' => ['sometimes', 'accepted'],
            'numero_oc' => ['nullable', 'string', 'max:100'],
            'archivo_oc' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);
        $autorizaOrdenAutomatica = ($datos['modalidad_oc'] ?? null) === 'automatica'
            || ! empty($datos['autorizar_oc_automatica']);
        if ($datos['decision'] !== 'aceptar' && (
            $autorizaOrdenAutomatica
            || (($datos['modalidad_oc'] ?? 'ninguna') !== 'ninguna')
            || ! empty($datos['numero_oc'])
            || $request->hasFile('archivo_oc')
        )) {
            throw ValidationException::withMessages([
                'orden_compra' => 'Solo puedes adjuntar o autorizar una orden de compra al aceptar la cotización.',
            ]);
        }
        if ($datos['decision'] === 'aceptar'
            && ! $autorizaOrdenAutomatica
            && ($datos['modalidad_oc'] ?? null) !== 'propia') {
            throw ValidationException::withMessages([
                'modalidad_oc' => 'Para aceptar, autoriza una OC generada por RESMAP o adjunta la OC de tu empresa.',
            ]);
        }
        if ($autorizaOrdenAutomatica && (
            ! empty($datos['numero_oc'])
            || $request->hasFile('archivo_oc')
        )) {
            throw ValidationException::withMessages([
                'orden_compra' => 'Elige una sola modalidad de orden de compra.',
            ]);
        }
        if (($datos['modalidad_oc'] ?? null) === 'propia'
            && empty($datos['numero_oc'])
            && ! $request->hasFile('archivo_oc')) {
            throw ValidationException::withMessages([
                'orden_compra' => 'Indica el número de la OC de tu empresa o adjunta su archivo.',
            ]);
        }
        if (($datos['modalidad_oc'] ?? null) === 'automatica'
            && (! empty($datos['numero_oc']) || $request->hasFile('archivo_oc'))) {
            throw ValidationException::withMessages([
                'orden_compra' => 'Selecciona “Adjuntar OC de mi empresa” para incluir sus datos o archivo.',
            ]);
        }

        $versionCotizacion = CotizacionVersion::with('cotizacion.solicitud.cliente')->findOrFail($version);
        if (! $this->tokenValido($versionCotizacion, $token)) {
            throw ValidationException::withMessages(['cotizacion' => 'El enlace de respuesta venció o ya fue utilizado.']);
        }

        $telefono = $this->soloDigitos($datos['telefono']);
        $clienteSnapshot = Cliente::desdeSnapshot($versionCotizacion->snapshotDatosCliente());
        $telefonoRegistrado = $this->soloDigitos((string) TelefonoChileno::normalizar($clienteSnapshot->telefono));
        if ($telefono === '' || ! hash_equals($telefonoRegistrado, $telefono)) {
            throw ValidationException::withMessages(['telefono' => 'El teléfono no coincide con el registrado para el cliente.']);
        }

        $archivoPath = null;
        if ($request->hasFile('archivo_oc')) {
            $archivo = $request->file('archivo_oc');
            $archivoPath = $archivo->storeAs(
                'ordenes-compra',
                Str::uuid().'.'.$archivo->extension(),
                'local',
            );
        }

        try {
            $ordenCompraId = DB::transaction(function () use (
                $request,
                $datos,
                $autorizaOrdenAutomatica,
                $versionCotizacion,
                $token,
                $telefono,
                &$archivoPath,
                $reservas,
            ): ?int {
                $version = CotizacionVersion::query()
                    ->whereKey($versionCotizacion->id_version)
                    ->lockForUpdate()
                    ->firstOrFail();
                if (! $this->tokenValido($version, $token)) {
                    throw ValidationException::withMessages(['cotizacion' => 'La cotización ya fue respondida o venció.']);
                }

                $cotizacion = Cotizacion::query()->whereKey($version->cotizacion_id)->lockForUpdate()->firstOrFail();
                $usuarioResponsable = $version->revisada_por ?? $cotizacion->creada_por;
                if ($usuarioResponsable === null) {
                    throw new RuntimeException('La cotización no tiene un usuario responsable registrado.');
                }
                $momento = now();
                $version->update([
                    'estado' => match ($datos['decision']) {
                        'aceptar' => 'aceptada',
                        'rechazar' => 'rechazada',
                        default => 'cambios_solicitados',
                    },
                    'aceptada_at' => $datos['decision'] === 'aceptar' ? $momento : null,
                    'rechazada_at' => $datos['decision'] === 'rechazar' ? $momento : null,
                    'cambios_solicitados_at' => $datos['decision'] === 'cambios' ? $momento : null,
                    'telefono_aceptacion' => $telefono,
                    'ip_aceptacion' => $request->ip(),
                    'token_cliente_hash' => null,
                    'token_expira_at' => null,
                ]);
                $cotizacion->update([
                    'estado' => match ($datos['decision']) {
                        'aceptar' => 'aceptada',
                        'rechazar' => 'rechazada',
                        default => 'cambios_solicitados',
                    },
                    'comentarios_cliente' => $datos['comentarios'] ?? null,
                ]);

                if ($datos['decision'] !== 'aceptar') {
                    if ($datos['decision'] === 'rechazar') {
                        $reservas->liberar($cotizacion, 'cotizacion_rechazada', $usuarioResponsable);
                    }

                    return null;
                }

                $this->asegurarReservaVigente($version, $reservas, $usuarioResponsable);
                $metodoEntrega = $datos['metodo_entrega'] ?? $version->metodo_entrega ?? 'retiro';
                $costoDespacho = $metodoEntrega === $version->metodo_entrega
                    ? (float) $version->costo_despacho
                    : ($metodoEntrega === 'despacho'
                        ? (float) config('services.cotizaciones.costo_despacho', 0)
                        : 0);
                $version->update([
                    'metodo_entrega' => $metodoEntrega,
                    'calle' => $metodoEntrega === 'despacho' ? $datos['calle'] : null,
                    'numero' => $metodoEntrega === 'despacho' ? $datos['numero'] : null,
                    'comuna' => $metodoEntrega === 'despacho' ? $datos['comuna'] : null,
                    'ciudad' => $metodoEntrega === 'despacho' ? $datos['ciudad'] : null,
                    'region' => $metodoEntrega === 'despacho' ? $datos['region'] : null,
                    'referencias' => $metodoEntrega === 'despacho' ? ($datos['referencias'] ?? null) : null,
                    'costo_despacho' => $costoDespacho,
                    'total' => round((float) $version->neto + (float) $version->iva + $costoDespacho, 2),
                ]);
                $venta = $this->crearVentaB2B($version);
                $ordenCompra = null;
                if ($autorizaOrdenAutomatica) {
                    $ordenCompra = OrdenCompra::create([
                        'version_id' => $version->id_version,
                        'venta_id' => $venta->id_venta,
                        'modalidad' => 'automatica_autorizada',
                        'numero' => 'RESMAP-'.$venta->id_venta,
                        'autorizada_at' => $momento,
                    ]);
                    $rutaLogo = ConfiguracionSistema::logoRutaFisica();
                    $logo = $rutaLogo === null ? false : file_get_contents($rutaLogo);
                    if ($logo === false) {
                        throw new RuntimeException('No fue posible leer el logo de RESMAP para generar la orden de compra.');
                    }
                    $contenidoPdf = Pdf::loadView('cotizaciones.orden-compra-pdf', [
                        'ordenCompra' => $ordenCompra,
                        'version' => $version,
                        'venta' => $venta,
                        'cliente' => Cliente::desdeSnapshot($version->snapshotDatosCliente()),
                        'logoDataUri' => 'data:image/png;base64,'.base64_encode($logo),
                    ])->setPaper('a4')->output();
                    $archivoPath = 'ordenes-compra/'.Str::uuid().'.pdf';
                    if (! Storage::disk('local')->put($archivoPath, $contenidoPdf)) {
                        throw new RuntimeException('No fue posible guardar la orden de compra autorizada.');
                    }
                    $ordenCompra->update([
                        'archivo_path' => $archivoPath,
                        'archivo_sha256' => hash('sha256', $contenidoPdf),
                    ]);
                } elseif ($archivoPath !== null || ! empty($datos['numero_oc'])) {
                    $ordenCompra = OrdenCompra::create([
                        'version_id' => $version->id_version,
                        'venta_id' => $venta->id_venta,
                        'modalidad' => 'cliente',
                        'numero' => $datos['numero_oc'] ?? null,
                        'archivo_path' => $archivoPath,
                        'archivo_sha256' => $archivoPath ? hash_file('sha256', Storage::disk('local')->path($archivoPath)) : null,
                    ]);
                }

                ReservaInventario::query()
                    ->where('cotizacion_id', $cotizacion->id_cotizacion)
                    ->where('estado', 'activa')
                    ->where('expira_at', '>', now())
                    ->update(['version_id' => $version->id_version]);

                return $ordenCompra?->id_orden_compra;
            });
        } catch (\Throwable $exception) {
            if ($archivoPath !== null) {
                Storage::disk('local')->delete($archivoPath);
            }

            throw $exception;
        }

        if ($datos['decision'] === 'aceptar' && $ordenCompraId !== null) {
            $ordenCompra = OrdenCompra::with([
                'version.cotizacion.solicitud.cliente',
                'venta.detalles',
            ])->findOrFail($ordenCompraId);
            $falloEnvioCorreo = false;

            User::query()->whereIn('rol', ['Administrador', 'Vendedor'])->get()
                ->filter(fn (User $user): bool => $user->tienePermiso('cotizaciones.ver'))->each(
                    function (User $usuario) use ($ordenCompra, &$falloEnvioCorreo): void {
                        try {
                            $usuario->notify(new OrdenCompraRecibida($ordenCompra));
                        } catch (TransportExceptionInterface|RuntimeException $exception) {
                            Log::error('La orden de compra quedó registrada, pero no se pudo enviar el aviso al equipo por correo.', [
                                'orden_compra_id' => $ordenCompra->id_orden_compra,
                                'user_id' => $usuario->id,
                                'error' => $exception->getMessage(),
                            ]);
                            $falloEnvioCorreo = true;
                        }
                    },
                );

            if ($falloEnvioCorreo) {
                return redirect()->route('home')
                    ->with('success', 'Tu respuesta y la orden de compra quedaron registradas.')
                    ->with('warning', 'El sistema no pudo notificar por correo a todos los integrantes del equipo RESMAP. El equipo puede revisar la orden desde el sistema.');
            }
        }

        return redirect()->route('home')->with('success', 'La respuesta a la cotización fue registrada.');
    }

    public function confirmarVenta(Request $request, Venta $venta): RedirectResponse
    {
        $datos = $request->validate([
            'tipo_confirmacion' => ['required', 'in:pago,credito'],
            'monto_pagado' => ['nullable', 'numeric', 'min:0'],
            'medio_pago' => ['nullable', 'in:Efectivo,Transferencia,Tarjeta'],
        ]);

        abort_if($venta->cotizacion_version_id === null, 422, 'Esta acción solo aplica a ventas originadas por cotización B2B.');
        if (! OrdenCompra::query()->where('version_id', $venta->cotizacion_version_id)->exists()) {
            throw ValidationException::withMessages([
                'orden_compra' => 'No se puede confirmar la venta hasta recibir o autorizar la orden de compra del cliente.',
            ]);
        }

        if ($datos['tipo_confirmacion'] === 'credito') {
            abort_unless($request->user()->rol === 'Administrador', 403, 'Solo un administrador puede autorizar crédito.');
        } elseif ((float) ($datos['monto_pagado'] ?? 0) < (float) $venta->total) {
            throw ValidationException::withMessages(['monto_pagado' => 'El pago debe cubrir el total de la venta.']);
        }

        DB::transaction(function () use ($request, $datos, $venta): void {
            $ventaBloqueada = Venta::query()->whereKey($venta->id_venta)->lockForUpdate()->firstOrFail();
            if ($ventaBloqueada->estado_venta === 'confirmada') {
                return;
            }
            $esCredito = $datos['tipo_confirmacion'] === 'credito';
            $ventaBloqueada->update([
                'estado_venta' => 'confirmada',
                'estado_pago' => $esCredito ? 'credito_autorizado' : 'pagado',
                'medio_pago' => $esCredito ? 'Crédito autorizado' : $datos['medio_pago'],
                'monto_pagado' => $esCredito ? 0 : $datos['monto_pagado'],
                'credito_autorizado_por' => $esCredito ? $request->user()->id : null,
                'credito_autorizado_at' => $esCredito ? now() : null,
                'confirmada_at' => now(),
            ]);
        });

        return back()->with('success', 'La venta B2B fue confirmada; el inventario se descontará al facturar.');
    }

    public function registrarPagoCredito(Request $request, Venta $venta): RedirectResponse
    {
        $datos = $request->validate([
            'monto_pagado' => ['required', 'numeric', 'gt:0'],
            'medio_pago' => ['required', 'in:Efectivo,Transferencia,Tarjeta'],
        ]);
        abort_if($venta->cotizacion_version_id === null, 422, 'Esta acción solo aplica a ventas originadas por cotización B2B.');

        $estadoPago = DB::transaction(function () use ($datos, $venta): string {
            $ventaBloqueada = Venta::query()->whereKey($venta->id_venta)->lockForUpdate()->firstOrFail();
            if (! in_array($ventaBloqueada->estado_pago, ['credito_autorizado', 'credito_parcial'], true)) {
                throw ValidationException::withMessages([
                    'credito' => 'Esta venta no tiene un crédito autorizado con saldo pendiente.',
                ]);
            }

            $total = round((float) $ventaBloqueada->total, 2);
            $pagado = round((float) ($ventaBloqueada->monto_pagado ?? 0), 2);
            $montoPago = round((float) $datos['monto_pagado'], 2);
            $saldoPendiente = round($total - $pagado, 2);
            if ($montoPago > $saldoPendiente) {
                throw ValidationException::withMessages([
                    'monto_pagado' => 'El abono no puede superar el saldo pendiente de $'.number_format($saldoPendiente, 0, ',', '.').'.',
                ]);
            }

            $nuevoMontoPagado = round($pagado + $montoPago, 2);
            $estadoPago = $nuevoMontoPagado >= $total ? 'pagado' : 'credito_parcial';
            $ventaBloqueada->update([
                'estado_pago' => $estadoPago,
                'monto_pagado' => $nuevoMontoPagado,
                'medio_pago' => $datos['medio_pago'],
            ]);

            return $estadoPago;
        });

        return back()->with(
            'success',
            $estadoPago === 'pagado'
                ? 'El saldo del crédito quedó totalmente pagado.'
                : 'El abono quedó registrado. La venta mantiene un saldo de crédito pendiente.',
        );
    }

    public function registrarEntrega(Venta $venta): RedirectResponse
    {
        abort_if($venta->cotizacion_version_id === null, 422, 'Esta acción solo aplica a ventas originadas por cotización B2B.');

        DB::transaction(function () use ($venta): void {
            $ventaBloqueada = Venta::query()->whereKey($venta->id_venta)->lockForUpdate()->firstOrFail();
            if ($ventaBloqueada->estado_facturacion !== 'simulada_exitosa'
                || $ventaBloqueada->stock_descontado_at === null) {
                throw ValidationException::withMessages([
                    'venta' => 'Registra la entrega solo después de completar la facturación de prueba.',
                ]);
            }

            if ($ventaBloqueada->estado_entrega !== 'entregada') {
                $ventaBloqueada->update(['estado_entrega' => 'entregada', 'entregada_at' => now()]);
            }
        });

        return back()->with('success', 'La venta quedó marcada como entregada o retirada.');
    }

    public function simularFacturacion(
        Request $request,
        Venta $venta,
        ProveedorFacturacion $proveedor,
        InventarioReservaService $reservas,
    ): RedirectResponse {
        abort_if(app()->isProduction(), 404);
        abort_if($venta->cotizacion_version_id === null, 422, 'La simulación solo está disponible para ventas B2B.');
        $datos = $request->validate(['resultado' => ['required', 'in:exito,error']]);
        $mensaje = DB::transaction(function () use ($request, $venta, $proveedor, $reservas, $datos): string {
            $venta = Venta::query()->whereKey($venta->id_venta)->lockForUpdate()->firstOrFail();
            if ($venta->estado_facturacion === 'simulada_exitosa' || $venta->stock_descontado_at !== null) {
                return 'Esta facturación ya fue simulada con éxito; no se generó otro descuento.';
            }
            if ($venta->estado_venta !== 'confirmada'
                || ! in_array($venta->estado_pago, ['pagado', 'credito_autorizado', 'credito_parcial'], true)) {
                throw ValidationException::withMessages([
                    'venta' => 'Confirma primero el pago total o una autorización de crédito.',
                ]);
            }

            $version = CotizacionVersion::with('cotizacion')->findOrFail($venta->cotizacion_version_id);
            $this->asegurarReservaVigente($version, $reservas, $request->user()->id);
            $resultado = $proveedor->emitir($venta, $datos['resultado'] === 'error');
            $evento = EventoFacturacion::create([
                'venta_id' => $venta->id_venta,
                'user_id' => $request->user()->id,
                'proveedor' => 'simulado',
                'resultado' => $resultado['success'] ? 'exitoso' : 'fallido',
                'clave_idempotencia' => 'simulado-'.$venta->id_venta.'-'.Str::uuid(),
                'respuesta' => [
                    'proveedor' => 'SIMULADO',
                    'resultado' => $resultado['success'] ? 'simulada_exitosa' : 'simulada_fallida',
                    'mensaje' => $resultado['message'],
                ],
            ]);

            if (! $resultado['success']) {
                $venta->update(['estado_facturacion' => 'simulada_fallida']);

                return 'Emisión simulada fallida; no se descontó inventario.';
            }

            $version = CotizacionVersion::with(['cotizacion', 'detalles'])->findOrFail($venta->cotizacion_version_id);
            $cantidades = $version->detalles
                ->groupBy('producto_id')
                ->map(fn ($items): int => $items->sum('cantidad'));
            $reservasActivas = $version->cotizacion->reservas()
                ->where('estado', 'activa')
                ->where('expira_at', '>', now())
                ->get()
                ->groupBy('producto_id');
            $faltantes = [];
            foreach ($cantidades as $productoId => $cantidad) {
                $reservado = (int) $reservasActivas->get($productoId, collect())->sum('cantidad');
                if ($reservado < $cantidad) {
                    $faltantes[$productoId] = $cantidad - $reservado;
                }
            }
            if ($faltantes !== []) {
                $reservas->reservar($version->cotizacion, $faltantes, $version->id_version);
                $reservasActivas = $version->cotizacion->reservas()
                    ->where('estado', 'activa')
                    ->where('expira_at', '>', now())
                    ->get()
                    ->groupBy('producto_id');
            }

            foreach ($cantidades as $productoId => $cantidad) {
                $producto = Producto::query()->whereKey($productoId)->lockForUpdate()->firstOrFail();
                $reservasProducto = $reservasActivas->get($productoId, collect());
                if ($producto->stock < $cantidad || $reservasProducto->sum('cantidad') < $cantidad) {
                    throw ValidationException::withMessages([
                        'stock' => "No hay unidades suficientes para facturar {$producto->nombre}.",
                    ]);
                }
                $otrasReservas = ReservaInventario::query()
                    ->where('producto_id', $productoId)
                    ->where('cotizacion_id', '!=', $version->cotizacion_id)
                    ->where('estado', 'activa')
                    ->where('expira_at', '>', now())
                    ->sum('cantidad');
                if ((int) $producto->stock - (int) $otrasReservas < $cantidad) {
                    throw ValidationException::withMessages([
                        'stock' => "La disponibilidad de {$producto->nombre} fue comprometida por otra operación.",
                    ]);
                }

                $stockAnterior = (int) $producto->stock;
                $producto->decrement('stock', $cantidad);
                InventarioMovimiento::create([
                    'id_producto' => $producto->id_producto,
                    'user_id' => $request->user()->id,
                    'tipo' => 'venta',
                    'cantidad' => -$cantidad,
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $stockAnterior - $cantidad,
                    'motivo' => 'Salida física por facturación simulada',
                    'fecha_evento' => now(),
                    'id_venta' => $venta->id_venta,
                ]);
                $this->consumirReservas($reservasProducto, $cantidad, $request->user()->id);
            }

            $venta->update([
                'estado_facturacion' => 'simulada_exitosa',
                'stock_descontado_at' => now(),
            ]);

            return 'Facturación de prueba exitosa; se descontó el inventario una sola vez. No se emitió un DTE.';
        });

        return back()->with('success', $mensaje);
    }

    public function procesarRespuestaWhatsApp(
        string $telefono,
        string $payload,
        InventarioReservaService $reservas,
    ): bool {
        if (! preg_match('/^quote:(\d+):(aceptar|rechazar|cambios):([A-Za-z0-9]{48})$/', $payload, $matches)) {
            return false;
        }

        $version = CotizacionVersion::with('cotizacion.solicitud.cliente')->find((int) $matches[1]);
        if (! $version) {
            return false;
        }

        $telefonoRemitente = $this->soloDigitos($telefono);
        $cliente = Cliente::desdeSnapshot($version->snapshotDatosCliente());
        $telefonoCliente = $this->soloDigitos((string) (
            $cliente->telefono_whatsapp ?? $cliente->telefono
        ));
        if (! $telefonoRemitente || ! hash_equals($telefonoCliente, $telefonoRemitente)) {
            return false;
        }

        if (! $this->tokenValido($version, $matches[3])) {
            $this->avisarCotizacionYaRespondida($telefono, $version);

            return false;
        }

        DB::transaction(function () use ($version, $matches, $telefonoRemitente, $reservas): void {
            $versionBloqueada = CotizacionVersion::query()->whereKey($version->id_version)->lockForUpdate()->firstOrFail();
            if (! $this->tokenValido($versionBloqueada, $matches[3])) {
                return;
            }
            $estado = match ($matches[2]) {
                'aceptar' => 'aceptada',
                'rechazar' => 'rechazada',
                default => 'cambios_solicitados',
            };
            $versionBloqueada->update([
                'estado' => $estado,
                'aceptada_at' => $estado === 'aceptada' ? now() : null,
                'rechazada_at' => $estado === 'rechazada' ? now() : null,
                'cambios_solicitados_at' => $estado === 'cambios_solicitados' ? now() : null,
                'telefono_aceptacion' => $telefonoRemitente,
                'token_cliente_hash' => null,
                'token_expira_at' => null,
            ]);
            $versionBloqueada->cotizacion()->update(['estado' => $estado]);

            if ($estado === 'aceptada') {
                $this->asegurarReservaVigente($versionBloqueada, $reservas, $versionBloqueada->revisada_por);
                $this->crearVentaB2B($versionBloqueada);
            } elseif ($estado === 'rechazada') {
                $reservas->liberar(
                    $versionBloqueada->cotizacion,
                    'cotizacion_rechazada',
                    $versionBloqueada->revisada_por,
                );
            }
        });

        return true;
    }

    private function avisarCotizacionYaRespondida(string $telefono, CotizacionVersion $version): void
    {
        try {
            app(WhatsAppCloudApi::class)->enviarTexto(
                $telefono,
                'Esta cotización ya fue respondida o ya no está vigente. Si necesitas revisarla, comunícate con RESMAP.',
                $version->cotizacion,
                $version,
            );
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::warning('No fue posible avisar por WhatsApp que la cotización ya fue respondida.', [
                'version_id' => $version->id_version,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function crearVentaB2B(CotizacionVersion $version, ?int $usuarioResponsable = null): Venta
    {
        $existente = Venta::query()->where('cotizacion_version_id', $version->id_version)->first();
        if ($existente) {
            return $existente;
        }
        $version->loadMissing(['cotizacion.solicitud.cliente', 'detalles']);
        $solicitud = $version->cotizacion->solicitud;
        $usuarioId = $usuarioResponsable ?? $version->revisada_por ?? $version->cotizacion->creada_por;
        if ($usuarioId === null) {
            throw new RuntimeException('La cotización no tiene un usuario responsable para registrar la venta.');
        }
        $venta = Venta::create([
            'fecha' => now(),
            'tipo_documento' => $usuarioResponsable === null ? 'Factura Electrónica' : 'Resumen interno',
            'folio_sii' => null,
            'neto' => $version->neto,
            'iva' => $version->iva,
            'total' => $version->total,
            'medio_pago' => 'Pendiente de confirmación',
            'estado_sii' => 'Pendiente',
            'estado_venta' => 'pendiente_confirmacion',
            'estado_pago' => 'pendiente',
            'estado_facturacion' => 'pendiente',
            'estado_entrega' => 'pendiente',
            'metodo_entrega' => $version->metodo_entrega,
            'direccion_despacho' => $version->calle ? $version->calle.' '.$version->numero : null,
            'comuna_despacho' => $version->comuna,
            'ciudad_despacho' => $version->ciudad,
            'region_despacho' => $version->region,
            'referencias_despacho' => $version->referencias,
            'cotizacion_version_id' => $version->id_version,
            'user_id' => $usuarioId,
            'id_cliente' => $solicitud->id_cliente,
            'datos_cliente' => $version->snapshotDatosCliente(),
            'contacto' => $solicitud->contacto,
            'cargo' => $solicitud->cargo,
        ]);
        $solicitud->update(['venta_id' => $venta->id_venta]);

        foreach ($version->detalles as $detalle) {
            DetalleVenta::create([
                'id_venta' => $venta->id_venta,
                'id_producto' => $detalle->producto_id,
                'descripcion' => $detalle->nombre_producto,
                'cantidad' => $detalle->cantidad,
                'precio_unitario' => $detalle->precio_unitario,
                'subtotal' => $detalle->subtotal,
            ]);
        }

        OrdenCompra::query()
            ->where('version_id', $version->id_version)
            ->whereNull('venta_id')
            ->update(['venta_id' => $venta->id_venta]);

        return $venta;
    }

    private function asegurarReservaVigente(
        CotizacionVersion $version,
        InventarioReservaService $reservas,
        ?int $userId,
    ): void {
        $version->loadMissing(['cotizacion', 'detalles']);
        $cantidades = $version->detalles
            ->groupBy('producto_id')
            ->map(fn ($items): int => $items->sum('cantidad'));
        $activas = $version->cotizacion->reservas()
            ->where('estado', 'activa')
            ->where('expira_at', '>', now())
            ->get()
            ->groupBy('producto_id');
        $faltantes = [];
        foreach ($cantidades as $productoId => $cantidad) {
            $reservado = (int) $activas->get($productoId, collect())->sum('cantidad');
            if ($reservado < $cantidad) {
                $faltantes[$productoId] = $cantidad - $reservado;
            }
        }

        if ($faltantes !== []) {
            $reservas->reservar($version->cotizacion, $faltantes, $version->id_version);
        }

        if ($userId !== null) {
            $version->cotizacion->reservas()
                ->where('estado', 'activa')
                ->where('expira_at', '>', now())
                ->whereNull('version_id')
                ->update(['version_id' => $version->id_version]);
        }
    }

    /**
     * @param  Collection<int, ReservaInventario>  $reservas
     */
    private function consumirReservas(Collection $reservas, int $cantidad, int $userId): void
    {
        $pendiente = $cantidad;
        foreach ($reservas as $reserva) {
            if ($pendiente <= 0) {
                break;
            }
            $cantidadConsumida = min($pendiente, (int) $reserva->cantidad);
            $expiracion = $reserva->expira_at;

            if ($cantidadConsumida === (int) $reserva->cantidad) {
                $reserva->update(['estado' => 'consumida', 'consumida_at' => now()]);
                EventoReserva::create([
                    'reserva_id' => $reserva->id_reserva,
                    'user_id' => $userId,
                    'evento' => 'consumida',
                    'expiracion_anterior' => $expiracion,
                ]);
            } else {
                $reserva->decrement('cantidad', $cantidadConsumida);
                $reservaConsumida = ReservaInventario::create([
                    'cotizacion_id' => $reserva->cotizacion_id,
                    'version_id' => $reserva->version_id,
                    'producto_id' => $reserva->producto_id,
                    'cantidad' => $cantidadConsumida,
                    'estado' => 'consumida',
                    'expira_at' => $expiracion,
                    'consumida_at' => now(),
                ]);
                EventoReserva::create([
                    'reserva_id' => $reservaConsumida->id_reserva,
                    'user_id' => $userId,
                    'evento' => 'consumida',
                    'expiracion_anterior' => $expiracion,
                ]);
            }

            $pendiente -= $cantidadConsumida;
        }

        if ($pendiente !== 0) {
            throw ValidationException::withMessages(['reserva' => 'La reserva no alcanza para consumir las unidades facturadas.']);
        }
    }

    private function tokenValido(CotizacionVersion $version, string $token): bool
    {
        return $version->estado === 'enviada'
            && $version->vigente_hasta->isFuture()
            && $version->token_expira_at?->isFuture() === true
            && is_string($version->token_cliente_hash)
            && hash_equals($version->token_cliente_hash, hash('sha256', $token));
    }

    private function soloDigitos(string $telefono): string
    {
        return preg_replace('/\D+/', '', $telefono) ?? '';
    }
}
