<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\EventoWebhookWhatsApp;
use App\Models\MensajeWhatsApp;
use App\Services\InventarioReservaService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class WhatsAppWebhookController extends Controller
{
    public function verificar(Request $request): Response
    {
        $modo = $request->query->get('hub.mode');
        $token = $request->query->get('hub.verify_token');
        $challenge = $request->query->get('hub.challenge');
        $tokenEsperado = (string) config('services.whatsapp.verify_token');

        abort_if($modo !== 'subscribe' || $tokenEsperado === '' || ! is_string($token), 403);
        abort_unless(hash_equals($tokenEsperado, $token), 403);

        return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function recibir(
        Request $request,
        CotizacionController $cotizaciones,
        InventarioReservaService $reservas,
    ): Response {
        $cuerpo = $request->getContent();
        $secreto = (string) config('services.whatsapp.app_secret');
        $firma = (string) $request->header('X-Hub-Signature-256');

        abort_if($secreto === '', 503, 'El secreto de firma del webhook no está configurado.');
        abort_if(strlen($cuerpo) > 1_000_000, 413, 'El evento de webhook excede el tamaño permitido.');
        abort_if(! preg_match('/^sha256=([a-f0-9]{64})$/', $firma, $coincidencias), 401);
        abort_unless(hash_equals(hash_hmac('sha256', $cuerpo, $secreto), $coincidencias[1]), 401);

        $payload = json_decode($cuerpo, true, 512, JSON_THROW_ON_ERROR);
        $hashEvento = hash('sha256', $cuerpo);

        DB::transaction(function () use ($payload, $hashEvento, $cotizaciones, $reservas): void {
            $evento = EventoWebhookWhatsApp::query()->where('id_proveedor', $hashEvento)->lockForUpdate()->first();
            if ($evento?->procesado_at !== null) {
                return;
            }
            if (! $evento) {
                $evento = EventoWebhookWhatsApp::create([
                    'id_proveedor' => $hashEvento,
                    'payload_sha256' => $hashEvento,
                    'payload' => ['object' => data_get($payload, 'object')],
                ]);
            }

            foreach (data_get($payload, 'entry', []) as $entrada) {
                foreach (data_get($entrada, 'changes', []) as $cambio) {
                    $valor = $cambio['value'] ?? [];
                    foreach ($valor['messages'] ?? [] as $mensajeEntrante) {
                        $this->procesarMensaje($mensajeEntrante, $cotizaciones, $reservas);
                    }
                    foreach ($valor['statuses'] ?? [] as $estadoEntrante) {
                        $estado = match ($estadoEntrante['status'] ?? '') {
                            'sent' => 'enviado',
                            'delivered' => 'entregado',
                            'read' => 'leido',
                            'failed' => 'fallido',
                            default => null,
                        };
                        if ($estado !== null && isset($estadoEntrante['id'])) {
                            MensajeWhatsApp::query()
                                ->where('id_proveedor', $estadoEntrante['id'])
                                ->where('direccion', 'saliente')
                                ->update(['estado' => $estado]);
                        }
                    }
                }
            }

            $evento->update(['procesado_at' => now()]);
        });

        return response('', 200);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function procesarMensaje(
        array $datos,
        CotizacionController $cotizaciones,
        InventarioReservaService $reservas,
    ): void {
        $idProveedor = $datos['id'] ?? null;
        $telefono = $datos['from'] ?? null;
        if (! is_string($idProveedor) || ! is_string($telefono)) {
            return;
        }
        if (MensajeWhatsApp::query()->where('id_proveedor', $idProveedor)->exists()) {
            return;
        }

        $tipo = (string) ($datos['type'] ?? 'desconocido');
        $payloadRespuesta = data_get($datos, 'interactive.button_reply.id')
            ?? data_get($datos, 'button.payload');
        $texto = $tipo === 'text'
            ? (string) data_get($datos, 'text.body', '')
            : (string) (
                data_get($datos, 'interactive.button_reply.title')
                ?? data_get($datos, 'button.text')
                ?? ''
            );
        MensajeWhatsApp::create([
            'direccion' => 'entrante',
            'telefono' => $telefono,
            'tipo' => $tipo,
            'estado' => 'recibido',
            'id_proveedor' => $idProveedor,
            'contenido' => $texto,
            'datos' => ['respuesta_interactiva' => is_string($payloadRespuesta)],
        ]);

        if (is_string($payloadRespuesta)) {
            $cotizaciones->procesarRespuestaWhatsApp($telefono, $payloadRespuesta, $reservas);

            return;
        }

        if ($tipo === 'text' && in_array(strtoupper(trim($texto)), ['STOP', 'BAJA', 'SALIR', 'CANCELAR'], true)) {
            $digitos = preg_replace('/\D+/', '', $telefono);
            if (is_string($digitos) && strlen($digitos) >= 8) {
                $sufijo = substr($digitos, -8);
                $cliente = Cliente::query()
                    ->where('telefono', 'like', '%'.$sufijo)
                    ->get()
                    ->first(fn (Cliente $cliente): bool => preg_replace('/\D+/', '', (string) $cliente->telefono) === $digitos);
                $cliente?->update([
                    'consentimiento_whatsapp_at' => null,
                    'consentimiento_whatsapp_origen' => 'whatsapp_opt_out',
                ]);
            }
        }
    }
}
