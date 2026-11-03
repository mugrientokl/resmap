<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionVersion;
use App\Models\ReservaInventario;
use App\Models\SolicitudWeb;
use App\Models\User;
use App\Services\InventarioReservaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CotizacionVencimientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_quotes_are_marked_and_their_stock_is_released(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $categoriaId = DB::table('categorias')->insertGetId([
            'nombre_categoria' => 'Pruebas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productoId = DB::table('productos')->insertGetId([
            'codigo_barra' => 'VEN-001',
            'nombre' => 'Producto vencimiento',
            'precio' => 1190,
            'stock' => 5,
            'stock_critico' => 1,
            'id_categoria' => $categoriaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cliente = Cliente::create(['rut' => '11.111.111-1', 'nombre' => 'Cliente']);
        $solicitud = SolicitudWeb::create([
            'fecha' => now(),
            'estado' => 'Pendiente',
            'tipo_solicitud' => 'productos',
            'id_cliente' => $cliente->id_cliente,
            'detalles_productos' => [],
        ]);
        $cotizacion = Cotizacion::create([
            'solicitud_id' => $solicitud->id_solicitud,
            'creada_por' => $admin->id,
            'estado' => 'enviada',
            'ultima_version' => 1,
        ]);
        $version = CotizacionVersion::create([
            'cotizacion_id' => $cotizacion->id_cotizacion,
            'numero_version' => 1,
            'estado' => 'enviada',
            'neto' => 1000,
            'iva' => 190,
            'costo_despacho' => 0,
            'total' => 1190,
            'metodo_entrega' => 'retiro',
            'vigente_hasta' => now()->subMinute(),
            'token_cliente_hash' => 'hash',
        ]);
        $reserva = ReservaInventario::create([
            'cotizacion_id' => $cotizacion->id_cotizacion,
            'version_id' => $version->id_version,
            'producto_id' => $productoId,
            'cantidad' => 2,
            'estado' => 'activa',
            'expira_at' => now()->addDay(),
        ]);

        $this->artisan('app:marcar-cotizaciones-vencidas')->assertSuccessful();

        $this->assertSame('vencida', $version->fresh()->estado);
        $this->assertNull($version->fresh()->token_cliente_hash);
        $this->assertSame('vencida', $cotizacion->fresh()->estado);
        $this->assertSame('liberada', $reserva->fresh()->estado);

        $this->actingAs($admin)
            ->get(route('solicitudes.show', $solicitud->id_solicitud))
            ->assertOk()
            ->assertSee('Cotización vencida')
            ->assertSee('Crear cotización');
    }

    public function test_reservations_last_as_long_as_the_quote_validity(): void
    {
        config(['services.cotizaciones.vigencia_dias' => 7]);
        $this->travelTo(now()->startOfSecond());

        $admin = User::factory()->create(['rol' => 'Administrador']);
        $cotizacion = $this->crearCotizacionBasica($admin);
        $productoId = $this->crearProducto();

        app(InventarioReservaService::class)->reservar($cotizacion, [$productoId => 1]);

        $this->assertTrue(
            ReservaInventario::firstOrFail()->expira_at->equalTo(now()->addDays(7))
        );
    }

    private function crearProducto(): int
    {
        $categoriaId = DB::table('categorias')->insertGetId([
            'nombre_categoria' => 'Pruebas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('productos')->insertGetId([
            'codigo_barra' => 'VEN-002',
            'nombre' => 'Producto',
            'precio' => 1190,
            'stock' => 5,
            'stock_critico' => 1,
            'id_categoria' => $categoriaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearCotizacionBasica(User $admin): Cotizacion
    {
        $cliente = Cliente::create(['rut' => '22.222.222-2', 'nombre' => 'Cliente 2']);
        $solicitud = SolicitudWeb::create([
            'fecha' => now(),
            'estado' => 'Pendiente',
            'tipo_solicitud' => 'productos',
            'id_cliente' => $cliente->id_cliente,
            'detalles_productos' => [],
        ]);

        return Cotizacion::create([
            'solicitud_id' => $solicitud->id_solicitud,
            'creada_por' => $admin->id,
            'estado' => 'pendiente_revision',
            'ultima_version' => 0,
        ]);
    }
}
