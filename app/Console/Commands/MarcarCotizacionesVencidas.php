<?php

namespace App\Console\Commands;

use App\Models\CotizacionVersion;
use App\Services\InventarioReservaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:marcar-cotizaciones-vencidas')]
#[Description('Marca como vencidas las cotizaciones fuera de vigencia y libera sus reservas')]
class MarcarCotizacionesVencidas extends Command
{
    public function handle(InventarioReservaService $reservas): int
    {
        $marcadas = 0;

        CotizacionVersion::query()
            ->whereIn('estado', ['borrador', 'enviada'])
            ->where('vigente_hasta', '<=', now())
            ->orderBy('id_version')
            ->each(function (CotizacionVersion $version) use ($reservas, &$marcadas): void {
                DB::transaction(function () use ($version, $reservas, &$marcadas): void {
                    $version->update(['estado' => 'vencida', 'token_cliente_hash' => null]);

                    $cotizacion = $version->cotizacion;
                    if ($cotizacion
                        && (int) $cotizacion->ultima_version === (int) $version->numero_version
                        && in_array($cotizacion->estado, ['borrador', 'enviada'], true)) {
                        $cotizacion->update(['estado' => 'vencida']);
                        $reservas->liberar($cotizacion, 'cotizacion_vencida');
                    }

                    $marcadas++;
                });
            });

        $this->info("Cotizaciones vencidas marcadas: {$marcadas}.");

        return self::SUCCESS;
    }
}
