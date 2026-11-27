<?php

namespace App\Jobs;

use App\Mail\CotizacionCliente;
use App\Models\CotizacionVersion;
use App\Services\CotizacionPdf;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EnviarCotizacionEmail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [5, 15, 45];

    public int $timeout = 90;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $versionId,
        public string $urlPdf,
    ) {}

    public function uniqueId(): string
    {
        return 'cotizacion-email-'.$this->versionId;
    }

    public function handle(CotizacionPdf $pdf): void
    {
        $version = CotizacionVersion::query()
            ->with(['cotizacion.solicitud.cliente', 'detalles'])
            ->findOrFail($this->versionId);

        if ($version->estado !== 'enviada'
            || $version->numero_version !== $version->cotizacion->ultima_version
            || $version->vigente_hasta?->isFuture() !== true) {
            $version->cancelarEnviosPendientes();
            Log::info('Se omitió el envío de una cotización que ya no está vigente.', [
                'version_id' => $this->versionId,
                'canal' => 'correo',
                'estado' => $version->estado,
            ]);

            return;
        }
        if ($version->email_estado !== 'en_cola') {
            return;
        }

        $correo = (string) ($version->snapshotDatosCliente()['correo'] ?? '');
        if ($correo === '') {
            $version->update(['email_estado' => 'sin_correo']);

            return;
        }

        try {
            Mail::to($correo)->send(new CotizacionCliente(
                $version,
                $this->urlPdf,
                $pdf->generar($version),
            ));
            $version->update(['email_estado' => 'aceptado_por_smtp']);
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
            ->where('email_estado', 'en_cola')
            ->update(['email_estado' => 'fallido']);

        Log::warning('Falló definitivamente el envío de una cotización por correo.', [
            'version_id' => $this->versionId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
