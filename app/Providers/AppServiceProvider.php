<?php

namespace App\Providers;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\SolicitudWeb;
use App\Models\User;
use App\Models\Venta;
use App\Observers\AuditoriaObserver;
use App\Services\Facturacion\FacturacionSimulada;
use App\Services\Facturacion\ProveedorFacturacion;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->app->bind(ProveedorFacturacion::class, FacturacionSimulada::class);

        foreach ([Categoria::class, Cliente::class, DetalleVenta::class, InventarioMovimiento::class, Producto::class, SolicitudWeb::class, User::class, Venta::class] as $modelo) {
            $modelo::observe(AuditoriaObserver::class);
        }
    }
}
