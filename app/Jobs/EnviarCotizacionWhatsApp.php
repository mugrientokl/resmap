<?php

namespace App\Jobs;

use App\Models\CotizacionVersion;
use App\Services\WhatsAppCloudApi;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnviarCotizacionWhatsApp implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [5, 15, 45];

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $versionId,
        public string $telefono,
        public string $urlPdf,
        public ?string $sufijoUrl = null,
    ) {}

    public function uniqueId(): string
    {
        return 'cotizacion-whatsapp-'.$this->versionId;
    }

    public function handle(WhatsAppCloudApi $whatsapp): void
    {
        $version = CotizacionVersion::query()
            ->with(['cotizacion.solicitud.cliente'])
            ->findOrFail($this->versionId);

        if ($version->estado !== 'enviada'
            || $version->numero_version !== $version->cotizacion->ultima_version
            || $version->vigente_hasta?->isFuture() !== true) {
            $version->cancelarEnviosPendientes();
            Log::info('Se omitió el envío de una cotización que ya no está vigente.', [
                'version_id' => $this->versionId,
                'canal' => 'whatsapp',
                'estado' => $version->estado,
            ]);

            return;
        }
        if ($version->whatsapp_estado !== 'en_cola') {
            return;
        }

        $cliente = $version->snapshotDatosCliente();
        $cotizacion = $version->cotizacion;
        $usarBotonUrl = (bool) config('services.whatsapp.quote_url_button');
        $parametros = [
            (string) ($cliente['nombre'] ?? 'Cliente'),
            (string) $cotizacion->solicitud_id,
            (string) $version->numero_version,
        ];
        if (! $usarBotonUrl) {
            $parametros[] = $this->urlPdf;
        }

        try {
            $whatsapp->enviarPlantillaCotizacion(
                $this->telefono,
                "Cotización #{$cotizacion->solicitud_id}, versión {$version->numero_version}. Comunícate con RESMAP para responder o solicitar cambios.",
                $parametros,
                [],
                $cotizacion,
                $version,
                $usarBotonUrl ? $this->sufijoUrl : null,
            );
            $version->update(['whatsapp_estado' => 'aceptado_por_meta']);
        } catch (Throwable $exception) {
            if (config('queue.default') === 'sync') {
                $this->failed($exception);

                return;
            }

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        CotizacionVersion::query()
            ->whereKey($this->versionId)
            ->where('whatsapp_estado', 'en_cola')
            ->update(['whatsapp_estado' => 'fallido']);

        Log::warning('Falló definitivamente el envío de una cotización por WhatsApp.', [
            'version_id' => $this->versionId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
