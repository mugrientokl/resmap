<?php

namespace App\Services\Facturacion;

use App\Models\Venta;

interface ProveedorFacturacion
{
    /**
     * @return array{success: bool, provider: string, message: string}
     */
    public function emitir(Venta $venta, bool $simularError): array;
}
