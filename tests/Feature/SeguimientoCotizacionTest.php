<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionVersion;
use App\Models\OrdenCompra;
use App\Models\SolicitudWeb;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeguimientoCotizacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_page_shows_current_status_and_who_we_are_waiting_for(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        [$solicitud] = $this->crearCotizacion($admin, 'enviada');

        $this->actingAs($admin)
            ->get(route('solicitudes.index'))
            ->assertSee(route('solicitudes.seguimiento', $solicitud->id_solicitud), false)
            ->assertSee('Ver seguimiento completo');

        $this->get(route('solicitudes.show', $solicitud->id_solicitud))
            ->assertSee(route('solicitudes.seguimiento', $solicitud->id_solicitud), false);

        $this->get(route('solicitudes.seguimiento', $solicitud->id_solicitud))
            ->assertOk()
            ->assertSee('Línea de tiempo')
            ->assertSee('Solicitud recibida')
            ->assertSee('Versión 1 enviada al cliente')
            ->assertSee('Esperando a')
            ->assertSee('Cliente');
    }

    public function test_tracking_page_lists_sale_milestones_with_recorded_dates(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        [$solicitud, $version] = $this->crearCotizacion($admin, 'aceptada');
        OrdenCompra::create([
            'version_id' => $version->id_version,
            'modalidad' => 'automatica',
            'autorizada_por' => $admin->id,
            'autorizada_at' => now()->subDays(3),
        ]);
        Venta::create([
            'fecha' => now(),
            'tipo_documento' => 'Factura Electrónica',
            'neto' => 1000,
            'iva' => 190,
            'total' => 1190,
            'medio_pago' => 'Transferencia',
            'estado_venta' => 'confirmada',
            'estado_pago' => 'pagado',
            'estado_facturacion' => 'simulada_exitosa',
            'estado_entrega' => 'entregada',
            'monto_pagado' => 1190,
            'confirmada_at' => now()->subDays(2),
            'stock_descontado_at' => now()->subDay(),
            'entregada_at' => now(),
            'cotizacion_version_id' => $version->id_version,
            'user_id' => $admin->id,
            'id_cliente' => $solicitud->id_cliente,
        ]);

        $this->actingAs($admin)
            ->get(route('solicitudes.seguimiento', $solicitud->id_solicitud))
            ->assertOk()
            ->assertSee('Cliente aceptó la versión 1')
            ->assertSee('Pago confirmado')
            ->assertSee('Stock descontado del inventario')
            ->assertSee('Entrega registrada')
            ->assertSee('Venta completada');
    }

    public function test_tracking_is_not_available_for_service_requests(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $cliente = Cliente::create(['rut' => '33.333.333-3', 'nombre' => 'Cliente servicio']);
        $servicio = SolicitudWeb::create([
            'fecha' => now(),
            'estado' => 'Pendiente',
            'tipo_solicitud' => 'servicio',
            'tipo_servicio' => 'Mantención',
            'descripcion_servicio' => 'Revisión',
            'detalles_productos' => [],
            'id_cliente' => $cliente->id_cliente,
        ]);

        $this->actingAs($admin)
            ->get(route('solicitudes.seguimiento', $servicio->id_solicitud))
            ->assertNotFound();
    }

    /**
     * @return array{0: SolicitudWeb, 1: CotizacionVersion}
     */
    private function crearCotizacion(User $usuario, string $estado): array
    {
        $cliente = Cliente::create(['rut' => '11.111.111-1', 'nombre' => 'Cliente B2B']);
        $solicitud = SolicitudWeb::create([
            'fecha' => now()->subDays(5),
            'estado' => 'Pendiente',
            'tipo_solicitud' => 'productos',
            'id_cliente' => $cliente->id_cliente,
            'detalles_productos' => [],
        ]);
        $cotizacion = Cotizacion::create([
            'solicitud_id' => $solicitud->id_solicitud,
            'creada_por' => $usuario->id,
            'estado' => $estado,
            'ultima_version' => 1,
        ]);
        $version = CotizacionVersion::create([
            'cotizacion_id' => $cotizacion->id_cotizacion,
            'revisada_por' => $usuario->id,
            'numero_version' => 1,
            'estado' => $estado,
            'neto' => 1000,
            'iva' => 190,
            'costo_despacho' => 0,
            'total' => 1190,
            'metodo_entrega' => 'retiro',
            'vigente_hasta' => now()->addDays(7),
            'enviada_at' => now()->subDays(4),
            'aceptada_at' => $estado === 'aceptada' ? now()->subDays(3) : null,
        ]);

        return [$solicitud, $version];
    }
}
