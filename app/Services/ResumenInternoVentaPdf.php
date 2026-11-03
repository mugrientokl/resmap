<?php

namespace App\Services;

use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use RuntimeException;

class ResumenInternoVentaPdf
{
    public function descargar(Venta $venta): Response
    {
        $venta->loadMissing([
            'cliente',
            'user',
            'detalles.producto',
            'cotizacionVersion.cotizacion',
            'ordenesCompra',
            'creditoAutorizadoPor',
        ]);

        $logo = file_get_contents(public_path('images/resmap sin fondo.png'));
        if ($logo === false) {
            throw new RuntimeException('No fue posible leer el logo de RESMAP para generar el resumen interno.');
        }

        $despacho = max(0, round((float) $venta->total - (float) $venta->neto - (float) $venta->iva, 2));

        return Pdf::loadView('reportes.venta-pdf', [
            'venta' => $venta,
            'despacho' => $despacho,
            'logoDataUri' => 'data:image/png;base64,'.base64_encode($logo),
        ])->setPaper('a4')->download('resumen-interno-venta-'.$venta->id_venta.'.pdf');
    }
}
