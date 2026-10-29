<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\CotizacionVersion;
use App\Models\MensajeWhatsApp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhatsAppCloudApi
{
    /**
     * @param  array<int, array{id: string, title: string}>  $botones
     */
    public function enviarPlantillaCotizacion(
        string $telefono,
        string $textoLog,
        array $parametros,
        array $botones,
        Cotizacion $cotizacion,
        CotizacionVersion $version,
    ): string {
        $nombrePlantilla = (string) config('services.whatsapp.quote_template');
        if ($nombrePlantilla === '') {
            throw new RuntimeException('Configura WHATSAPP_QUOTE_TEMPLATE con el nombre de una plantilla aprobada por Meta.');
        }
        if (count($botones) < 1 || count($botones) > 3) {
            throw new \InvalidArgumentException('La plantilla de cotización debe definir entre uno y tres botones de respuesta.');
        }

        $componentes = [
            [
                'type' => 'body',
                'parameters' => array_map(
                    fn (string $valor): array => ['type' => 'text', 'text' => $valor],
                    $parametros,
                ),
            ],
        ];
        foreach ($botones as $indice => $boton) {
            $componentes[] = [
                'type' => 'button',
                'sub_type' => 'quick_reply',
                'index' => (string) $indice,
                'parameters' => [
                    ['type' => 'payload', 'payload' => $boton['id']],
                ],
            ];
        }

        return $this->enviar($telefono, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizarTelefono($telefono),
            'type' => 'template',
            'template' => [
                'name' => $nombrePlantilla,
                'language' => ['code' => config('services.whatsapp.template_language', 'es_CL')],
                'components' => $componentes,
            ],
        ], $textoLog, $cotizacion, $version, 'cotizacion-template-'.$version->id_version);
    }

    public function enviarTexto(
        string $telefono,
        string $texto,
        ?Cotizacion $cotizacion = null,
        ?CotizacionVersion $version = null,
        ?string $claveIdempotencia = null,
    ): string {
        return $this->enviar($telefono, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizarTelefono($telefono),
            'type' => 'text',
            'text' => ['preview_url' => true, 'body' => $texto],
        ], $texto, $cotizacion, $version, $claveIdempotencia);
    }

    /**
     * @param  array<int, array{id: string, title: string}>  $botones
     */
    public function enviarOpciones(
        string $telefono,
        string $texto,
        array $botones,
        ?Cotizacion $cotizacion = null,
        ?CotizacionVersion $version = null,
    ): string {
        if (count($botones) < 1 || count($botones) > 3) {
            throw new \InvalidArgumentException('WhatsApp permite entre uno y tres botones de respuesta.');
        }

        return $this->enviar($telefono, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizarTelefono($telefono),
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $texto],
                'action' => ['buttons' => array_map(
                    fn (array $boton): array => [
                        'type' => 'reply',
                        'reply' => $boton,
                    ],
                    $botones,
                )],
            ],
        ], $texto, $cotizacion, $version, null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function enviar(
        string $telefono,
        array $payload,
        string $contenido,
        ?Cotizacion $cotizacion,
        ?CotizacionVersion $version,
        ?string $claveIdempotencia,
    ): string {
        $telefono = $this->normalizarTelefono($telefono);
        $this->validarConfiguracion($telefono);

        if ($claveIdempotencia !== null && MensajeWhatsApp::where('clave_idempotencia', $claveIdempotencia)->exists()) {
            throw new RuntimeException('Este mensaje ya fue procesado; no se volverá a enviar.');
        }

        $datosRegistro = $payload;
        $botones = &$datosRegistro['interactive']['action']['buttons'];
        if (isset($botones)) {
            foreach ($botones as &$boton) {
                $id = $boton['reply']['id'] ?? null;
                if (is_string($id)) {
                    $boton['reply']['id'] = preg_replace(
                        '/(quote:\d+:(?:aceptar|rechazar|cambios):)[A-Za-z0-9]{48}$/',
                        '$1[REDACTED]',
                        $id,
                    ) ?? '[REDACTED]';
                }
            }
            unset($boton);
        }
        unset($botones);
        if (isset($datosRegistro['template']['components'])) {
            foreach ($datosRegistro['template']['components'] as &$componente) {
                if (($componente['type'] ?? null) === 'body') {
                    foreach ($componente['parameters'] ?? [] as $indice => $parametro) {
                        if ($indice === 3 && ($parametro['type'] ?? null) === 'text') {
                            $componente['parameters'][$indice]['text'] = '[LINK DE COTIZACION]';
                        }
                    }
                } elseif (($componente['type'] ?? null) === 'button') {
                    $respuestaBoton = $componente['parameters'][0]['payload'] ?? null;
                    if (is_string($respuestaBoton)) {
                        $componente['parameters'][0]['payload'] = preg_replace(
                            '/(quote:\d+:(?:aceptar|rechazar|cambios):)[A-Za-z0-9]{48}$/',
                            '$1[REDACTED]',
                            $respuestaBoton,
                        ) ?? '[REDACTED]';
                    }
                }
            }
            unset($componente);
        }

        $mensaje = MensajeWhatsApp::create([
            'cotizacion_id' => $cotizacion?->id_cotizacion,
            'version_id' => $version?->id_version,
            'direccion' => 'saliente',
            'telefono' => $telefono,
            'tipo' => $payload['type'],
            'estado' => 'pendiente',
            'clave_idempotencia' => $claveIdempotencia,
            'contenido' => $contenido,
            'datos' => $datosRegistro,
        ]);

        try {
            $respuesta = Http::withToken((string) config('services.whatsapp.access_token'))
                ->acceptJson()
                ->timeout(15)
                ->post(
                    'https://graph.facebook.com/'.config('services.whatsapp.api_version').'/'.config('services.whatsapp.phone_number_id').'/messages',
                    $payload,
                )
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $exception) {
            $mensaje->update([
                'estado' => 'fallido',
                'error' => $exception->getMessage(),
            ]);
            Log::warning('Falló el envío de WhatsApp Cloud API.', [
                'mensaje_id' => $mensaje->id_mensaje,
                'quote_id' => $cotizacion?->id_cotizacion,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $idProveedor = data_get($respuesta, 'messages.0.id');
        if (! is_string($idProveedor) || $idProveedor === '') {
            $mensaje->update(['estado' => 'fallido', 'error' => 'Meta no devolvió un identificador de mensaje.']);
            throw new RuntimeException('La respuesta de WhatsApp no contiene un ID de mensaje.');
        }

        $mensaje->update([
            'id_proveedor' => $idProveedor,
            'estado' => 'aceptado_por_meta',
        ]);

        return $idProveedor;
    }

    private function validarConfiguracion(string $telefono): void
    {
        if (! config('services.whatsapp.enabled')
            || blank(config('services.whatsapp.phone_number_id'))
            || blank(config('services.whatsapp.access_token'))
            || blank(config('services.whatsapp.api_version'))) {
            throw new RuntimeException('WhatsApp Cloud API no está habilitada o carece de credenciales.');
        }

        if (config('services.whatsapp.test_mode')) {
            $destinatariosPermitidos = array_map(
                fn (string $numero): string => $this->normalizarTelefono($numero),
                config('services.whatsapp.test_recipients', []),
            );
            if (! in_array($telefono, $destinatariosPermitidos, true)) {
                throw new RuntimeException('El destinatario no está autorizado en la lista de pruebas de WhatsApp.');
            }
        }
    }

    public function normalizarTelefono(string $telefono): string
    {
        $normalizado = preg_replace('/\D+/', '', $telefono);
        if (! is_string($normalizado) || ! preg_match('/^[1-9][0-9]{7,14}$/', $normalizado)) {
            throw new \InvalidArgumentException('El teléfono debe ser E.164 válido.');
        }

        return $normalizado;
    }
}
