<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Venta;
use App\Support\ConfiguracionSistema;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ResumenInternoVentaPdf
{
    public function descargar(Venta $venta): Response
    {
        if ($venta->cotizacion_version_id !== null && $venta->resumen_interno_pdf === null && ! empty($venta->datos_resumen)) {
            $venta = DB::transaction(function () use ($venta): Venta {
                $venta = Venta::query()->whereKey($venta->id_venta)->lockForUpdate()->firstOrFail();
                if ($venta->resumen_interno_pdf === null) {
                    $venta->resumen_interno_pdf = base64_encode($this->generar($venta));
                    $venta->save();
                }

                return $venta;
            });
        }

        if ($venta->resumen_interno_pdf !== null) {
            $pdf = base64_decode($venta->resumen_interno_pdf, true);
            if ($pdf === false) {
                throw new RuntimeException('No fue posible leer el resumen interno guardado.');
            }

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="resumen-interno-venta-'.$venta->id_venta.'.pdf"',
            ]);
        }

        abort_if(
            $venta->cotizacion_version_id !== null && empty($venta->datos_resumen),
            404,
            'El resumen interno aún no ha sido generado.'
        );

        return response($this->generar($venta), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="resumen-interno-venta-'.$venta->id_venta.'.pdf"',
        ]);
    }

    public function generar(Venta $venta): string
    {
        $venta->loadMissing([
            'cliente',
            'user',
            'detalles.producto',
            'cotizacionVersion.cotizacion',
            'solicitudServicio',
            'ordenesCompra',
            'creditoAutorizadoPor',
        ]);

        $rutaLogo = ConfiguracionSistema::logoRutaFisica();
        $logo = $rutaLogo === null ? false : file_get_contents($rutaLogo);
        if ($logo === false) {
            throw new RuntimeException('No fue posible leer el logo de RESMAP para generar el resumen interno.');
        }

        $despacho = max(0, round((float) $venta->total - (float) $venta->neto - (float) $venta->iva, 2));

        return Pdf::loadView('reportes.venta-pdf', [
            'venta' => $venta,
            'cliente' => Cliente::desdeSnapshot($venta->snapshotDatosCliente()),
            'despacho' => $despacho,
            'logoDataUri' => 'data:image/png;base64,'.base64_encode($logo),
        ])->setPaper('a4')->output();
    }
}
