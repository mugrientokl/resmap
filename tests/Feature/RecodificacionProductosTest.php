<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class RecodificacionProductosTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversion_preserves_products_relations_and_historical_quotation_codes(): void
    {
        $category = DB::table('categorias')->insertGetId(['nombre_categoria' => 'Herramientas']);
        $first = $this->producto($category, 'Mártillo pesado', 'RESMAP-000001');
        $second = $this->producto($category, 'Martillo ligero', 'MAR003');
        $third = $this->producto($category, 'Enchufe', 'RESMAP-000003');
        $seller = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($seller)->postJson('/ventas', [
            'tipo_documento' => 'Boleta Electrónica', 'medio_pago' => 'Efectivo',
            'rut' => '66666666-6', 'nombre_cliente' => 'Cliente', 'contacto' => 'Contacto',
            'correo_cliente' => 'cliente@example.test', 'direccion_cliente' => 'Calle 123',
            'region_cliente' => 'Metropolitana', 'comuna_cliente' => 'Santiago', 'ciudad_cliente' => 'Santiago',
            'acteco_emisor' => '475201', 'tipo_venta' => 'del_giro', 'tipo_compra' => 'del_giro',
            'detalles' => [['id_producto' => $first, 'cantidad' => 1]],
        ])->assertCreated();
        $this->post(route('catalogo.solicitudes.store'), [
            'rut' => '12345678-5', 'nombre' => 'Otro cliente', 'contacto' => 'Contacto',
            'correo' => 'otro@example.test', 'prefijo_telefono' => '+569', 'telefono' => '12345678',
            'region' => 'Metropolitana', 'comuna' => 'Santiago',
            'detalles_productos' => [['id_producto' => $first, 'cantidad' => 1]],
        ])->assertSessionHasNoErrors();
        $solicitud = DB::table('solicitud_webs')->value('id_solicitud');
        $this->post(route('solicitudes.cotizacion.crear', $solicitud))->assertSessionHasNoErrors();

        $original = DB::table('productos')->where('id_producto', $first)->first();
        $detalleVenta = DB::table('detalle_ventas')->first();
        $movimiento = DB::table('inventario_movimientos')->first();
        $detalleCotizacion = DB::table('cotizacion_detalles')->first();
        $this->assertSame('RESMAP-000001', $detalleCotizacion->codigo_producto);

        $this->artisan('app:recodificar-productos')->assertSuccessful();
        $this->assertDatabaseHas('productos', ['id_producto' => $first, 'codigo_barra' => 'RESMAP-000001']);
        $this->artisan('app:recodificar-productos', ['--aplicar' => true])->assertFailed();
        $this->maintenance(true);
        $this->artisan('app:recodificar-productos', ['--aplicar' => true])->assertSuccessful();

        $actual = DB::table('productos')->where('id_producto', $first)->first();
        $this->assertSame('MAR004', $actual->codigo_barra);
        $this->assertSame('MAR004', $actual->codigo_origen);
        $datosOriginales = (array) $original;
        $datosActuales = (array) $actual;
        unset($datosOriginales['codigo_barra'], $datosOriginales['codigo_origen'], $datosActuales['codigo_barra'], $datosActuales['codigo_origen']);
        $this->assertSame($datosOriginales, $datosActuales);
        $this->assertDatabaseHas('productos', ['id_producto' => $second, 'codigo_barra' => 'MAR003']);
        $this->assertDatabaseHas('productos', ['id_producto' => $third, 'codigo_barra' => 'ENC001']);
        $this->assertDatabaseHas('producto_recodificaciones', [
            'id_producto' => $first, 'codigo_barra_anterior' => 'RESMAP-000001', 'codigo_nuevo' => 'MAR004',
        ]);
        $this->assertDatabaseHas('detalle_ventas', ['id_detalle' => $detalleVenta->id_detalle, 'id_producto' => $first]);
        $this->assertDatabaseHas('inventario_movimientos', ['id_movimiento' => $movimiento->id_movimiento, 'id_producto' => $first]);
        $this->assertDatabaseHas('cotizacion_detalles', [
            'id_detalle' => $detalleCotizacion->id_detalle, 'producto_id' => $first, 'codigo_producto' => 'RESMAP-000001',
        ]);
        $this->assertDatabaseHas('reservas_inventario', ['producto_id' => $first, 'estado' => 'activa']);

        $this->artisan('app:recodificar-productos', ['--aplicar' => true])->assertSuccessful();
        $this->assertDatabaseHas('productos', ['id_producto' => $first, 'codigo_barra' => 'MAR004']);
        $this->maintenance(false);
        $this->get(route('catalogo.index', ['nombre' => 'MAR004']))->assertOk()->assertSee('Mártillo pesado');
        $this->getJson(route('pos.productos.buscar', ['buscar' => 'MAR004']))
            ->assertOk()->assertJsonPath('0.nombre', 'Mártillo pesado')
            ->assertJsonPath('0.stock', 3);
        $this->getJson(route('pos.producto.codigo', ['codigo' => 'MAR004']))->assertOk()->assertJsonPath('id_producto', $first);
        $this->get(route('productos.etiqueta', $first))->assertOk()->assertSee('MAR004')->assertSee('CODE128');

        $this->put(route('productos.update', $first), [
            'codigo_barra' => 'MAR004', 'nombre' => 'Llave pesada', 'precio' => $actual->precio,
            'stock' => $actual->stock, 'stock_critico' => $actual->stock_critico,
            'id_categoria' => $category, 'unidad' => $actual->unidad, 'activo' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('productos', ['id_producto' => $first, 'nombre' => 'Llave pesada', 'codigo_barra' => 'MAR004']);
        $this->maintenance(true);
        $this->artisan('app:recodificar-productos', ['--aplicar' => true])->assertSuccessful();
        $this->assertDatabaseHas('productos', ['id_producto' => $first, 'nombre' => 'Llave pesada', 'codigo_barra' => 'MAR004']);
    }

    public function test_repeated_codes_and_invalid_references_block_conversion_without_changes(): void
    {
        $category = DB::table('categorias')->insertGetId(['nombre_categoria' => 'Prueba']);
        $first = $this->producto($category, 'Martillo', 'RESMAP-000001');
        $second = $this->producto($category, 'Enchufe', 'ENC001');
        DB::table('productos')->where('id_producto', $first)->update(['codigo_origen' => null]);
        DB::table('productos')->where('id_producto', $second)->update(['codigo_origen' => 'RESMAP-000001']);

        $this->maintenance(true);
        $this->artisan('app:recodificar-productos', ['--aplicar' => true])
            ->expectsOutputToContain('Código anterior ambiguo')->assertFailed();
        $this->assertDatabaseCount('producto_recodificaciones', 0);
        $this->assertDatabaseHas('productos', ['id_producto' => $first, 'codigo_barra' => 'RESMAP-000001']);

        DB::table('productos')->where('id_producto', $second)->update(['codigo_origen' => null]);
        $client = DB::table('clientes')->insertGetId(['rut' => '12345678-5', 'nombre' => 'Cliente']);
        DB::table('solicitud_webs')->insert([
            'fecha' => now(), 'estado' => 'Pendiente', 'id_cliente' => $client,
            'detalles_productos' => json_encode([['id_producto' => 99999, 'cantidad' => 1]]),
        ]);
        $this->artisan('app:recodificar-productos', ['--aplicar' => true])
            ->expectsOutputToContain('referencia de producto inexistente')->assertFailed();
        $this->assertDatabaseCount('producto_recodificaciones', 0);
        $this->assertDatabaseHas('productos', ['id_producto' => $first, 'codigo_barra' => 'RESMAP-000001']);
    }

    public function test_conversion_preserves_duplicate_names_and_rejects_names_without_three_letters(): void
    {
        $category = DB::table('categorias')->insertGetId(['nombre_categoria' => 'Prueba']);
        $first = $this->producto($category, 'Martillo', 'RESMAP-1');
        $second = $this->producto($category, 'Martíllo', 'RESMAP-2');
        $short = $this->producto($category, 'A2', 'RESMAP-3');
        $this->maintenance(true);
        $this->artisan('app:recodificar-productos', ['--aplicar' => true])
            ->expectsOutputToContain('no tiene tres letras')->assertFailed();
        $this->assertDatabaseCount('producto_recodificaciones', 0);

        DB::table('productos')->where('id_producto', $short)->update(['nombre' => 'Aceite']);
        $this->artisan('app:recodificar-productos', ['--aplicar' => true])
            ->expectsOutputToContain('Nombres equivalentes')->assertSuccessful();
        $this->assertDatabaseHas('productos', ['id_producto' => $first, 'nombre' => 'Martillo', 'codigo_barra' => 'MAR001']);
        $this->assertDatabaseHas('productos', ['id_producto' => $second, 'nombre' => 'Martíllo', 'codigo_barra' => 'MAR002']);
        $this->assertTrue(Producto::nombreExiste('  MARTILLO  '));
    }

    public function test_conversion_rolls_back_codes_and_mapping_if_an_update_fails(): void
    {
        $category = DB::table('categorias')->insertGetId(['nombre_categoria' => 'Prueba']);
        $first = $this->producto($category, 'Martillo', 'RESMAP-1');
        $second = $this->producto($category, 'Martillo largo', 'RESMAP-2');
        DB::statement("CREATE TRIGGER impedir_conversion BEFORE UPDATE ON productos WHEN NEW.codigo_barra = 'MAR002' BEGIN SELECT RAISE(ABORT, 'Error de prueba'); END");
        $this->maintenance(true);
        $this->artisan('app:recodificar-productos', ['--aplicar' => true])->assertFailed();
        $this->assertDatabaseCount('producto_recodificaciones', 0);
        $this->assertDatabaseHas('productos', ['id_producto' => $first, 'codigo_barra' => 'RESMAP-1']);
        $this->assertDatabaseHas('productos', ['id_producto' => $second, 'codigo_barra' => 'RESMAP-2']);
    }

    public function test_conversion_continues_above_999_and_does_not_reuse_an_old_code_for_another_product(): void
    {
        $category = DB::table('categorias')->insertGetId(['nombre_categoria' => 'Prueba']);
        $first = $this->producto($category, 'Martillo', 'RESMAP-1');
        $preserved = $this->producto($category, 'Martillo largo', 'MAR999');
        $other = $this->producto($category, 'Pistola', 'MAR1000');
        $this->maintenance(true);
        $this->artisan('app:recodificar-productos', ['--aplicar' => true])->assertSuccessful();
        $this->assertDatabaseHas('productos', ['id_producto' => $first, 'codigo_barra' => 'MAR1001']);
        $this->assertDatabaseHas('productos', ['id_producto' => $preserved, 'codigo_barra' => 'MAR999']);
        $this->assertDatabaseHas('productos', ['id_producto' => $other, 'codigo_barra' => 'PIS001']);

        $nuevo = Producto::crearConCodigo([
            'nombre' => 'Marca nueva', 'precio' => 10, 'stock' => 1,
            'stock_critico' => 1, 'id_categoria' => $category,
        ]);
        $this->assertSame('MAR1002', $nuevo->codigo_barra);
    }

    public function test_new_products_do_not_reuse_a_historical_code_from_another_prefix(): void
    {
        $category = DB::table('categorias')->insertGetId(['nombre_categoria' => 'Prueba']);
        $this->producto($category, 'Pistola', 'MAR999');
        $this->maintenance(true);
        $this->artisan('app:recodificar-productos', ['--aplicar' => true])->assertSuccessful();

        $nuevo = Producto::crearConCodigo([
            'nombre' => 'Martillo', 'precio' => 10, 'stock' => 1,
            'stock_critico' => 1, 'id_categoria' => $category,
        ]);
        $this->assertSame('MAR1000', $nuevo->codigo_barra);
    }

    private function producto(int $category, string $nombre, string $codigo): int
    {
        return DB::table('productos')->insertGetId([
            'codigo_barra' => $codigo, 'codigo_origen' => $codigo, 'nombre' => $nombre,
            'precio' => 1190, 'stock' => 5, 'stock_critico' => 1, 'id_categoria' => $category,
        ]);
    }

    private function maintenance(bool $active): void
    {
        $maintenance = Mockery::mock(MaintenanceMode::class);
        $maintenance->shouldReceive('active')->andReturn($active);
        $this->app->instance(MaintenanceMode::class, $maintenance);
    }
}
