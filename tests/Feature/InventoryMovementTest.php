<?php

namespace Tests\Feature;

use App\Models\InventarioMovimiento;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InventoryMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_products_and_filter_them_by_category(): void
    {
        [$admin, $productId] = $this->createInventoryFixture(3);

        $response = $this->actingAs($admin)->get(route('inventario.movimientos'));

        $response->assertOk()
            ->assertSee('Movimientos de Inventario')
            ->assertSee('Buscar / Seleccionar Producto')
            ->assertSee('Filtrar por categoría')
            ->assertSee('Todas las categorías')
            ->assertSee('Registrar Movimiento')
            ->assertSee('role="dialog"', false)
            ->assertSee('id="movement-products-data"', false)
            ->assertSee('"id":'.$productId, false)
            ->assertSee('"stock":3', false)
            ->assertSee('data-confirm-title="Confirmar movimiento de inventario"', false)
            ->assertSee('Producto de prueba');
    }

    public function test_admin_can_adjust_stock_and_the_movement_records_the_actor(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $categoryId = DB::table('categorias')->insertGetId([
            'nombre_categoria' => 'Pruebas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productId = DB::table('productos')->insertGetId([
            'codigo_barra' => 'TEST-001',
            'nombre' => 'Producto de prueba',
            'precio' => 1190,
            'stock' => 3,
            'stock_critico' => 1,
            'id_categoria' => $categoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('inventario.movimientos.store'), [
            'id_producto' => $productId,
            'stock_nuevo' => 8,
            'motivo' => 'Conteo físico',
            'fecha_evento' => '2026-10-15',
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('inventario.movimientos'));

        $this->assertDatabaseHas('inventario_movimientos', [
            'id_producto' => $productId,
            'user_id' => $admin->id,
            'cantidad' => 5,
            'stock_anterior' => 3,
            'stock_nuevo' => 8,
            'motivo' => 'Conteo físico',
        ]);
        $movement = InventarioMovimiento::query()->latest('id_movimiento')->firstOrFail();
        $this->assertSame('2026-10-15', $movement->fecha_evento->format('Y-m-d'));

        $this->actingAs($admin)
            ->get(route('inventario.movimientos'))
            ->assertOk()
            ->assertSee('15/10/2026');
    }

    public function test_editing_a_product_without_changing_its_stock_does_not_create_a_movement(): void
    {
        [$admin, $productId] = $this->createInventoryFixture(3);
        $producto = DB::table('productos')->where('id_producto', $productId)->first();

        $this->actingAs($admin)
            ->put(route('productos.update', $productId), [
                'codigo_barra' => $producto->codigo_barra,
                'nombre' => $producto->nombre,
                'precio' => $producto->precio,
                'stock' => '3',
                'stock_critico' => $producto->stock_critico,
                'id_categoria' => $producto->id_categoria,
                'unidad' => $producto->unidad,
                'activo' => '1',
            ])
            ->assertRedirect(route('productos.index'));

        $this->assertDatabaseMissing('inventario_movimientos', [
            'id_producto' => $productId,
            'tipo' => 'ajuste',
        ]);
    }

    public function test_admin_can_register_an_acquisition_with_supplier_data(): void
    {
        [$admin, $productId] = $this->createInventoryFixture(3);
        $proveedor = Proveedor::factory()->create(['nombre' => 'Proveedor Central SpA', 'contacto' => 'Contacto inicial']);

        $this->actingAs($admin)->post(route('inventario.movimientos.store'), [
            'tipo' => 'adquisicion',
            'id_producto' => $productId,
            'cantidad' => 7,
            'motivo' => 'Compra de reposición',
            'fecha_evento' => '2026-10-15',
            'proveedor_id' => $proveedor->id,
            'precio_entrada' => 850.50,
            'documento_proveedor' => 'F-1024',
            'observaciones' => 'Entrega parcial de la orden de compra.',
        ])->assertRedirect(route('inventario.movimientos'));

        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 10]);
        $this->assertDatabaseHas('inventario_movimientos', [
            'id_producto' => $productId,
            'tipo' => 'adquisicion',
            'cantidad' => 7,
            'proveedor' => 'Proveedor Central SpA',
            'proveedor_id' => $proveedor->id,
            'contacto_proveedor' => 'Contacto inicial',
            'documento_proveedor' => 'F-1024',
            'precio_entrada' => 850.50,
            'cantidad_adquirida' => 7,
        ]);
    }

    public function test_admin_can_register_a_merma_without_going_below_zero(): void
    {
        [$admin, $productId] = $this->createInventoryFixture(3);

        $this->actingAs($admin)->post(route('inventario.movimientos.store'), [
            'tipo' => 'merma',
            'id_producto' => $productId,
            'cantidad' => 2,
            'motivo' => 'Producto dañado',
            'fecha_evento' => '2026-10-15',
            'observaciones' => 'Daño detectado durante el conteo.',
        ])->assertRedirect(route('inventario.movimientos'));

        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 1]);
        $this->assertDatabaseHas('inventario_movimientos', [
            'id_producto' => $productId,
            'tipo' => 'merma',
            'cantidad' => -2,
            'stock_anterior' => 3,
            'stock_nuevo' => 1,
        ]);
    }

    public function test_category_filter_is_optional_and_does_not_change_which_product_receives_the_movement(): void
    {
        [$admin, $productId] = $this->createInventoryFixture(3);
        [, $productInOtherCategoryId] = $this->createInventoryFixture(7);

        $this->actingAs($admin)->post(route('inventario.movimientos.store'), [
            'tipo' => 'ingreso',
            'id_producto' => $productId,
            'cantidad' => 2,
            'motivo' => 'Ingreso sin categoría seleccionada',
            'fecha_evento' => '2026-10-15',
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('inventario.movimientos'));

        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 5]);
        $this->assertDatabaseHas('productos', ['id_producto' => $productInOtherCategoryId, 'stock' => 7]);
    }

    public function test_admin_can_fetch_the_full_movement_detail(): void
    {
        [$admin, $productId] = $this->createInventoryFixture(3);
        $proveedor = Proveedor::factory()->create(['nombre' => 'Proveedor de prueba', 'contacto' => 'Contacto original']);

        $this->actingAs($admin)->post(route('inventario.movimientos.store'), [
            'tipo' => 'adquisicion',
            'id_producto' => $productId,
            'cantidad' => 2,
            'motivo' => 'Compra de prueba',
            'fecha_evento' => '2026-10-15',
            'proveedor_id' => $proveedor->id,
            'precio_entrada' => 1000,
        ]);
        $movementId = (int) DB::table('inventario_movimientos')->latest('id_movimiento')->value('id_movimiento');
        $proveedor->update(['nombre' => 'Proveedor actualizado', 'contacto' => 'Contacto actualizado']);

        $this->actingAs($admin)
            ->getJson(route('inventario.movimientos.show', $movementId))
            ->assertOk()
            ->assertJsonPath('tipo', 'adquisicion')
            ->assertJsonPath('fecha_evento', '15/10/2026')
            ->assertJsonPath('proveedor', 'Proveedor de prueba')
            ->assertJsonPath('contacto_proveedor', 'Contacto original')
            ->assertJsonPath('cantidad_adquirida', 2)
            ->assertJsonPath('precio_unitario_adquisicion', 500)
            ->assertJsonPath('precio_venta_recomendado', 595);
    }

    public function test_admin_can_create_and_edit_suppliers_with_initial_examples_available(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $this->actingAs($admin)->get(route('proveedores.index'))
            ->assertOk()->assertSee('Imbra')->assertSee('Ricardo Soto')
            ->assertSee('Brajovic')->assertSee('Victoria Rodriguez');
        $this->post(route('proveedores.store'), ['nombre' => 'Repuestos del Sur', 'contacto' => 'María Díaz'])
            ->assertRedirect(route('proveedores.index'))->assertSessionHasNoErrors();
        $proveedor = Proveedor::where('nombre', 'Repuestos del Sur')->firstOrFail();
        $this->put(route('proveedores.update', $proveedor), ['nombre' => 'Repuestos Sur', 'contacto' => 'Ana Soto'])
            ->assertRedirect(route('proveedores.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('proveedores', ['id' => $proveedor->id, 'nombre' => 'Repuestos Sur', 'contacto' => 'Ana Soto']);
        $this->post(route('proveedores.store'), ['nombre' => 'Repuestos Sur', 'contacto' => 'Otra persona'])
            ->assertSessionHasErrors('nombre');
        $this->put(route('proveedores.update', $proveedor), ['nombre' => 'Repuestos Sur', 'contacto' => ''])
            ->assertSessionHasErrors('contacto');
    }

    public function test_only_administrators_can_manage_suppliers(): void
    {
        $this->get(route('proveedores.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['rol' => 'Vendedor']))
            ->get(route('proveedores.index'))->assertForbidden();
        $this->post(route('proveedores.store'), ['nombre' => 'Proveedor', 'contacto' => 'Contacto'])->assertForbidden();
    }

    public function test_purchase_and_supplier_return_require_valid_supplier_and_correct_stock_direction(): void
    {
        [$admin, $productId] = $this->createInventoryFixture(5);
        $proveedor = Proveedor::factory()->create(['nombre' => 'Proveedor retorno', 'contacto' => 'Contacto retorno']);
        $this->actingAs($admin)->get(route('inventario.movimientos'))
            ->assertOk()->assertSee('Proveedor retorno')->assertSee('Contacto retorno')
            ->assertSee('id="movement-provider"', false);
        $datos = [
            'id_producto' => $productId, 'tipo' => 'ingreso', 'cantidad' => 2,
            'motivo' => 'Compra / Adquisición', 'fecha_evento' => '2026-10-15',
        ];
        $this->post(route('inventario.movimientos.store'), $datos)->assertSessionHasErrors('proveedor_id');
        $this->post(route('inventario.movimientos.store'), [...$datos, 'proveedor_id' => 999999])->assertSessionHasErrors('proveedor_id');
        $this->post(route('inventario.movimientos.store'), [...$datos, 'tipo' => 'egreso', 'proveedor_id' => $proveedor->id])
            ->assertSessionHasErrors('tipo');
        $this->post(route('inventario.movimientos.store'), [...$datos, 'motivo' => 'Devolución', 'proveedor_id' => $proveedor->id])
            ->assertSessionHasErrors('tipo');
        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 5]);
        $this->assertDatabaseCount('inventario_movimientos', 0);

        $this->post(route('inventario.movimientos.store'), [
            ...$datos, 'tipo' => 'egreso', 'motivo' => 'Devolución', 'proveedor_id' => $proveedor->id,
        ])->assertRedirect(route('inventario.movimientos'));
        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 3]);
        $this->assertDatabaseHas('inventario_movimientos', [
            'id_producto' => $productId, 'cantidad' => -2, 'proveedor_id' => $proveedor->id,
            'proveedor' => 'Proveedor retorno', 'contacto_proveedor' => 'Contacto retorno',
        ]);
        $proveedor->update(['nombre' => 'Nombre modificado', 'contacto' => 'Contacto modificado']);
        $this->get(route('inventario.movimientos'))
            ->assertOk()->assertSee('Proveedor retorno')->assertSee('Contacto retorno');
        $this->post(route('inventario.movimientos.store'), [
            ...$datos, 'proveedor_id' => $proveedor->id,
        ])->assertRedirect(route('inventario.movimientos'));
        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 5]);
        $this->assertDatabaseHas('inventario_movimientos', [
            'id_producto' => $productId, 'cantidad' => 2, 'motivo' => 'Compra / Adquisición',
            'proveedor' => 'Nombre modificado', 'contacto_proveedor' => 'Contacto modificado',
        ]);
    }

    public function test_supplier_return_cannot_consume_reserved_stock(): void
    {
        Notification::fake();
        [$admin, $productId] = $this->createInventoryFixture(5);
        $proveedor = Proveedor::factory()->create();
        $this->post(route('catalogo.solicitudes.store'), [
            'rut' => '12345678-5', 'nombre' => 'Empresa', 'contacto' => 'Ana',
            'correo' => 'cliente@example.test', 'prefijo_telefono' => '+569',
            'telefono' => '12345678', 'region' => 'Metropolitana', 'comuna' => 'Santiago',
            'detalles_productos' => [['id_producto' => $productId, 'cantidad' => 4]],
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('inventario.movimientos.store'), [
            'id_producto' => $productId, 'tipo' => 'egreso', 'cantidad' => 2,
            'motivo' => 'Devolución', 'fecha_evento' => '2026-10-15', 'proveedor_id' => $proveedor->id,
        ])->assertSessionHasErrors('cantidad');
        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 5]);
        $this->assertDatabaseCount('inventario_movimientos', 0);
    }

    public function test_admin_can_filter_movements_by_product_type_and_event_date_range(): void
    {
        [$admin, $matchingProductId] = $this->createInventoryFixture(3);
        [, $otherProductId] = $this->createInventoryFixture(3);

        InventarioMovimiento::create([
            'id_producto' => $matchingProductId,
            'tipo' => 'ingreso',
            'cantidad' => 2,
            'stock_anterior' => 3,
            'stock_nuevo' => 5,
            'motivo' => 'Movimiento que coincide',
            'fecha_evento' => '2026-10-15',
        ]);
        InventarioMovimiento::create([
            'id_producto' => $otherProductId,
            'tipo' => 'egreso',
            'cantidad' => -1,
            'stock_anterior' => 3,
            'stock_nuevo' => 2,
            'motivo' => 'Movimiento fuera del filtro',
            'fecha_evento' => '2026-10-14',
        ]);

        $this->actingAs($admin)
            ->get(route('inventario.movimientos', [
                'producto' => $matchingProductId,
                'tipo' => 'ingreso',
                'fecha_desde' => '2026-10-15',
                'fecha_hasta' => '2026-10-15',
            ]))
            ->assertOk()
            ->assertSee('Movimiento que coincide')
            ->assertDontSee('Movimiento fuera del filtro')
            ->assertSee('name="producto" value="'.$matchingProductId.'"', false);

        $this->actingAs($admin)
            ->get(route('inventario.movimientos', ['fecha_hasta' => '2026-10-14']))
            ->assertOk()
            ->assertSee('Movimiento fuera del filtro')
            ->assertDontSee('Movimiento que coincide');
    }

    private function createInventoryFixture(int $stock): array
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $categoryId = DB::table('categorias')->insertGetId([
            'nombre_categoria' => 'Pruebas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productId = DB::table('productos')->insertGetId([
            'codigo_barra' => 'TEST-'.uniqid(),
            'nombre' => 'Producto de prueba',
            'precio' => 1190,
            'stock' => $stock,
            'stock_critico' => 1,
            'id_categoria' => $categoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$admin, $productId];
    }
}
