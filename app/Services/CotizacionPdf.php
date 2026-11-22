<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\CotizacionVersion;
use Barryvdh\DomPDF\Facade\Pdf;
use RuntimeException;

class CotizacionPdf
{
    public function generar(CotizacionVersion $version): string
    {
        $version->loadMissing(['cotizacion.solicitud', 'detalles']);
        $logo = file_get_contents(public_path('images/resmap sin fondo.png'));
        if ($logo === false) {
            throw new RuntimeException('No fue posible leer el logo de RESMAP para generar la cotización.');
        }

        return Pdf::loadView('cotizaciones.pdf', [
            'version' => $version,
            'cliente' => Cliente::desdeSnapshot($version->snapshotDatosCliente()),
            'logoDataUri' => 'data:image/png;base64,'.base64_encode($logo),
        ])->setPaper('a4')->output();
    }
}
