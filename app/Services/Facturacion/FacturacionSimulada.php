<?php

namespace App\Services\Facturacion;

use App\Models\Venta;

class FacturacionSimulada implements ProveedorFacturacion
{
    public function emitir(Venta $venta, bool $simularError): array
    {
        return [
            'success' => ! $simularError,
            'provider' => 'simulado',
            'message' => $simularError
                ? 'Error de emisión simulado para la venta '.$venta->id_venta.'.'
                : 'Emisión simulada exitosa para la venta '.$venta->id_venta.'.',
        ];
    }
}
