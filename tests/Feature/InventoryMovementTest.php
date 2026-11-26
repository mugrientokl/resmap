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
            ->assertSee('id="movement-detail-modal"', false)
            ->assertSee('id="movement-price-confirmation-modal"', false)
            ->assertSee('Costo neto unitario de adquisición')
            ->assertSee('id="movement-products-data"', false)
            ->assertSee('"id":'.$productId, false)
            ->assertSee('"stock":3', false)
            ->assertSee('data-confirm-title="Confirmar movimiento de inventario"', false)
            ->assertSee('Producto de prueba');
    }

    public function test_catalog_includes_the_last_recorded_acquisition_for_each_product(): void
    {
        [$admin, $productId] = $this->createInventoryFixture(3);
        [, $productWithoutPurchaseId] = $this->createInventoryFixture(3);

        InventarioMovimiento::create([
            'id_producto' => $productId, 'tipo' => 'adquisicion', 'cantidad' => 2,
            'stock_anterior' => 0, 'stock_nuevo' => 2, 'motivo' => 'Compra anterior',
            'fecha_evento' => '2026-10-20', 'cantidad_adquirida' => 2, 'precio_entrada' => 1000,
        ]);
        InventarioMovimiento::create([
            'id_producto' => $productId, 'tipo' => 'ingreso', 'cantidad' => 3,
            'stock_anterior' => 2, 'stock_nuevo' => 5, 'motivo' => 'Compra / Adquisición',
            'fecha_evento' => '2026-10-10', 'cantidad_adquirida' => 3, 'precio_entrada' => 0,
            'proveedor' => 'Proveedor registrado',
        ]);
        InventarioMovimiento::create([
            'id_producto' => $productId, 'tipo' => 'egreso', 'cantidad' => -1,
            'stock_anterior' => 5, 'stock_nuevo' => 4, 'motivo' => 'Merma / Daño',
            'fecha_evento' => '2026-10-25',
        ]);

        $this->actingAs($admin)->get(route('inventario.movimientos'))
            ->assertOk()
            ->assertViewHas('catalogoProductos', function (array $products) use ($productId, $productWithoutPurchaseId): bool {
                $catalog = collect($products)->keyBy('id');

                return $catalog[$productId]['ultima_adquisicion'] === [
                    'fecha' => '10/10/2026', 'cantidad' => 3, 'costo_total' => 0.0, 'proveedor' => 'Proveedor registrado',
                ] && $catalog[$productWithoutPurchaseId]['ultima_adquisicion'] === null;
            });
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
            'precio_entrada' => 5953.50,
            'cantidad_adquirida' => 7,
            'costo_unitario_neto' => 850.50,
            'costo_total_neto' => 5953.50,
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
            ->assertJsonPath('precio_unitario_adquisicion', 1000)
            ->assertJsonPath('precio_venta_recomendado', 1547);
    }

    public function test_multiple_purchases_update_stock_prices_and_keep_supplier_details(): void
    {
        [$admin, $firstProductId] = $this->createInventoryFixture(3);
        [, $secondProductId] = $this->createInventoryFixture(7);
        $proveedor = Proveedor::factory()->create();

        $this->actingAs($admin)->post(route('inventario.movimientos.store'), [
            'tipo' => 'ingreso', 'motivo' => 'Compra / Adquisición', 'fecha_evento' => '2026-10-15',
            'proveedor_id' => $proveedor->id, 'documento_proveedor' => 'F-123',
            'productos' => [
                ['id_producto' => $firstProductId, 'cantidad' => 2, 'precio_entrada' => 1000],
                ['id_producto' => $secondProductId, 'cantidad' => 4, 'precio_entrada' => 8000],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect(route('inventario.movimientos'));

        $this->assertDatabaseHas('productos', ['id_producto' => $firstProductId, 'stock' => 5]);
        $this->assertDatabaseHas('productos', ['id_producto' => $secondProductId, 'stock' => 11]);
        $this->assertDatabaseHas('inventario_movimientos', [
            'id_producto' => $firstProductId, 'cantidad' => 2, 'precio_entrada' => 2000,
            'cantidad_adquirida' => 2, 'proveedor_id' => $proveedor->id, 'documento_proveedor' => 'F-123',
        ]);
        $this->assertDatabaseCount('inventario_movimientos', 2);
        $this->get(route('catalogo.index'))->assertOk()->assertSee('1.190');
    }

    public function test_multiple_movements_are_rolled_back_when_a_product_has_insufficient_stock(): void
    {
        [$admin, $firstProductId] = $this->createInventoryFixture(5);
        [, $secondProductId] = $this->createInventoryFixture(1);

        $this->actingAs($admin)->post(route('inventario.movimientos.store'), [
            'tipo' => 'egreso', 'motivo' => 'Merma / Daño', 'fecha_evento' => '2026-10-15',
            'productos' => [
                ['id_producto' => $firstProductId, 'cantidad' => 2],
                ['id_producto' => $secondProductId, 'cantidad' => 2],
            ],
        ])->assertSessionHasErrors('cantidad');

        $this->assertDatabaseHas('productos', ['id_producto' => $firstProductId, 'stock' => 5]);
        $this->assertDatabaseHas('productos', ['id_producto' => $secondProductId, 'stock' => 1]);
        $this->assertDatabaseCount('inventario_movimientos', 0);
    }

    public function test_multiple_movement_rejects_duplicate_products_invalid_reasons_and_missing_purchase_cost(): void
    {
        [$admin, $productId] = $this->createInventoryFixture(3);
        $proveedor = Proveedor::factory()->create();
        $datos = [
            'tipo' => 'ingreso', 'motivo' => 'Compra / Adquisición', 'fecha_evento' => '2026-10-15',
            'proveedor_id' => $proveedor->id,
            'productos' => [['id_producto' => $productId, 'cantidad' => 2, 'precio_entrada' => 1000]],
        ];
        $this->actingAs($admin)->post(route('inventario.movimientos.store'), [
            ...$datos, 'productos' => [$datos['productos'][0], $datos['productos'][0]],
        ])->assertSessionHasErrors('productos.0.id_producto');
        $this->post(route('inventario.movimientos.store'), [...$datos, 'tipo' => 'ajuste'])
            ->assertSessionHasErrors('motivo');
        $this->post(route('inventario.movimientos.store'), [
            ...$datos, 'productos' => [['id_producto' => $productId, 'cantidad' => 2]],
        ])->assertSessionHasErrors('productos.0.precio_entrada');
        $this->assertDatabaseCount('inventario_movimientos', 0);
    }

    public function test_multiple_stock_adjustments_can_set_zero_and_preserve_prices(): void
    {
        [$admin, $firstProductId] = $this->createInventoryFixture(3);
        [, $secondProductId] = $this->createInventoryFixture(7);

        $this->actingAs($admin)->post(route('inventario.movimientos.store'), [
            'tipo' => 'ajuste', 'motivo' => 'Uso interno', 'fecha_evento' => '2026-10-15',
            'productos' => [
                ['id_producto' => $firstProductId, 'cantidad' => 0],
                ['id_producto' => $secondProductId, 'cantidad' => 4],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('productos', ['id_producto' => $firstProductId, 'stock' => 0, 'precio' => 1190]);
        $this->assertDatabaseHas('productos', ['id_producto' => $secondProductId, 'stock' => 4, 'precio' => 1190]);
        $this->assertDatabaseCount('inventario_movimientos', 2);
    }

    public function test_free_acquisition_keeps_zero_cost_in_the_detail_and_catalog(): void
    {
        [$admin, $productId] = $this->createInventoryFixture(3);
        $proveedor = Proveedor::factory()->create();

        $this->actingAs($admin)->post(route('inventario.movimientos.store'), [
            'tipo' => 'ingreso', 'motivo' => 'Compra / Adquisición', 'fecha_evento' => '2026-10-15',
            'proveedor_id' => $proveedor->id,
            'productos' => [['id_producto' => $productId, 'cantidad' => 2, 'precio_entrada' => 0]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 5, 'precio' => 1190]);
        $movement = InventarioMovimiento::query()->latest('id_movimiento')->firstOrFail();
        $this->getJson(route('inventario.movimientos.show', $movement))
            ->assertOk()->assertJsonPath('precio_entrada', 0)->assertJsonPath('precio_unitario_adquisicion', 0)
            ->assertJsonPath('precio_venta_recomendado', 0);
    }

    public function test_acquisition_keeps_the_current_price_until_the_recommended_change_is_confirmed(): void
    {
        [$admin, $productId] = $this->createInventoryFixture(3);
        DB::table('productos')->where('id_producto', $productId)->update(['modo_precio' => 'automatico']);
        $proveedor = Proveedor::factory()->create();

        $this->actingAs($admin)->post(route('inventario.movimientos.store'), [
            'tipo' => 'ingreso',
            'motivo' => 'Compra / Adquisición',
            'fecha_evento' => '2026-10-15',
            'proveedor_id' => $proveedor->id,
            'productos' => [['id_producto' => $productId, 'cantidad' => 2, 'precio_entrada' => 1000]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('productos', [
            'id_producto' => $productId,
            'precio' => 1190,
            'modo_precio' => 'manual',
            'costo_promedio_neto' => 400,
        ]);
    }

    public function test_admin_can_create_and_edit_suppliers_with_initial_examples_available(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $this->actingAs($admin)->get(route('proveedores.index'))
            ->assertOk()->assertSee('Imbra')->assertSee('Ricardo Soto')
            ->assertSee('Brajovic')->assertSee('Victoria Rodriguez');
        $this->post(route('proveedores.store'), [
            'nombre' => 'Repuestos del Sur', 'contacto' => 'María Díaz',
            'telefono' => '+56912345678', 'correo' => 'ventas@example.cl', 'pagina_web' => 'https://example.cl',
        ])
            ->assertRedirect(route('proveedores.index'))->assertSessionHasNoErrors();
        $proveedor = Proveedor::where('nombre', 'Repuestos del Sur')->firstOrFail();
        $this->put(route('proveedores.update', $proveedor), [
            'nombre' => 'Repuestos Sur', 'contacto' => 'Ana Soto',
            'telefono' => '+56223456789', 'correo' => 'ana@example.cl', 'pagina_web' => '',
        ])
            ->assertRedirect(route('proveedores.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('proveedores', [
            'id' => $proveedor->id, 'nombre' => 'Repuestos Sur', 'contacto' => 'Ana Soto',
            'telefono' => '+56223456789', 'correo' => 'ana@example.cl', 'pagina_web' => null,
        ]);
        $this->get(route('proveedores.index'))->assertSee('+56223456789')->assertSee('ana@example.cl');
        $this->post(route('proveedores.store'), ['nombre' => 'Repuestos Sur', 'contacto' => 'Otra persona'])
            ->assertSessionHasErrors('nombre');
        $this->put(route('proveedores.update', $proveedor), ['nombre' => 'Repuestos Sur', 'contacto' => ''])
            ->assertSessionHasErrors('contacto');
    }

    public function test_supplier_contact_fields_are_validated_on_creation_and_editing(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'Administrador']));
        $proveedor = Proveedor::where('nombre', 'Imbra')->firstOrFail();
        $this->assertNull($proveedor->telefono);
        $this->assertNull($proveedor->correo);

        $this->postJson(route('proveedores.store'), [])
            ->assertUnprocessable()->assertJsonValidationErrors(['nombre', 'contacto', 'telefono', 'correo']);
        $invalid = [
            'nombre' => 'Proveedor nuevo', 'contacto' => 'Contacto',
            'telefono' => '1234567', 'correo' => 'invalido', 'pagina_web' => 'ftp://example.cl',
        ];
        $this->postJson(route('proveedores.store'), $invalid)
            ->assertUnprocessable()->assertJsonValidationErrors(['telefono', 'correo', 'pagina_web']);
        $this->putJson(route('proveedores.update', $proveedor), $invalid)
            ->assertUnprocessable()->assertJsonValidationErrors(['telefono', 'correo', 'pagina_web']);
        $this->assertDatabaseHas('proveedores', ['id' => $proveedor->id, 'nombre' => 'Imbra', 'contacto' => 'Ricardo Soto']);

        $this->put(route('proveedores.update', $proveedor), [
            'nombre' => 'Imbra', 'contacto' => 'Ricardo Soto',
            'telefono' => '87654321', 'correo' => 'imbra@example.cl',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('proveedores', ['id' => $proveedor->id, 'telefono' => '+56987654321', 'correo' => 'imbra@example.cl']);

        $this->putJson(route('proveedores.update', $proveedor), [
            'nombre' => 'Imbra', 'contacto' => 'Ricardo Soto',
            'telefono' => '+5656912345678', 'correo' => 'imbra@example.cl',
        ])->assertUnprocessable()->assertJsonValidationErrors('telefono');
        $this->assertDatabaseHas('proveedores', ['id' => $proveedor->id, 'telefono' => '+56987654321']);
    }

    public function test_supplier_forms_show_chilean_phone_fields(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'Administrador']));

        foreach (['proveedores.index', 'inventario.movimientos'] as $page) {
            $this->get(route($page))->assertOk()
                ->assertSee('aria-label="Código de país"', false)
                ->assertSee('pattern="[2-9][0-9]{8}" maxlength="9"', false);
        }
    }

    public function test_supplier_created_as_json_can_be_used_in_an_acquisition_and_duplicates_are_rejected(): void
    {
        [$admin, $productId] = $this->createInventoryFixture(3);
        $this->actingAs($admin);
        $supplier = [
            'nombre' => 'Proveedor modal', 'contacto' => 'Contacto modal',
            'telefono' => '12345678', 'correo' => 'modal@example.cl', 'pagina_web' => 'https://example.cl',
        ];
        $response = $this->postJson(route('proveedores.store'), $supplier)
            ->assertCreated()->assertJsonPath('nombre', 'Proveedor modal')->assertJsonPath('contacto', 'Contacto modal');
        $providerId = $response->json('id');
        $this->assertDatabaseHas('proveedores', ['id' => $providerId, ...$supplier, 'telefono' => '+56912345678']);
        $this->postJson(route('proveedores.store'), $supplier)
            ->assertUnprocessable()->assertJsonValidationErrors('nombre');
        $this->post(route('inventario.movimientos.store'), [
            'tipo' => 'ingreso', 'motivo' => 'Compra / Adquisición', 'fecha_evento' => '2026-10-15',
            'proveedor_id' => $providerId,
            'productos' => [['id_producto' => $productId, 'cantidad' => 2, 'precio_entrada' => 1000]],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inventario_movimientos', ['id_producto' => $productId, 'proveedor_id' => $providerId, 'cantidad' => 2]);
    }

    public function test_only_administrators_can_manage_suppliers(): void
    {
        $this->get(route('proveedores.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['rol' => 'Vendedor']))
            ->get(route('proveedores.index'))->assertForbidden();
        $this->post(route('proveedores.store'), ['nombre' => 'Proveedor', 'contacto' => 'Contacto'])->assertForbidden();
        $this->postJson(route('proveedores.store'), [])->assertForbidden();
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
            ...$datos, 'proveedor_id' => $proveedor->id, 'precio_entrada' => 1000,
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
            ->assertViewHas('movimientos', fn ($movimientos): bool => $movimientos->count() === 1 && $movimientos->first()->motivo === 'Movimiento que coincide')
            ->assertSee('name="producto" value="'.$matchingProductId.'"', false);

        $this->actingAs($admin)
            ->get(route('inventario.movimientos', ['fecha_hasta' => '2026-10-14']))
            ->assertOk()
            ->assertViewHas('movimientos', fn ($movimientos): bool => $movimientos->count() === 1 && $movimientos->first()->motivo === 'Movimiento fuera del filtro');

        $this->get(route('inventario.movimientos', ['motivo' => 'Movimiento que coincide']))
            ->assertOk()
            ->assertViewHas('movimientos', fn ($movimientos): bool => $movimientos->count() === 1 && $movimientos->first()->motivo === 'Movimiento que coincide')
            ->assertSee('name="motivo"', false);
    }

    public function test_reason_filter_groups_related_movements_without_manual_otros_option(): void
    {
        [$admin, $productId] = $this->createInventoryFixture(3);
        $movements = [
            ['venta', 'Venta registrada', 'grupo:venta'],
            ['venta', 'Salida por venta de cotización', 'grupo:venta'],
            ['adquisicion', 'Compra de reposición', 'grupo:compra'],
            ['ingreso', 'Compra / Adquisición', 'grupo:compra'],
            ['merma', 'Producto dañado', 'grupo:merma'],
            ['egreso', 'Merma / Daño', 'grupo:merma'],
            ['egreso', 'Devolución', 'grupo:devolucion'],
            ['ajuste', 'Stock editado desde producto', 'grupo:ajuste'],
            ['ajuste', 'Uso interno', 'grupo:uso_interno'],
            ['ingreso', 'Producto creado', 'grupo:stock_inicial'],
            ['ingreso', 'Stock inicial', 'grupo:stock_inicial'],
            ['entrada', 'Stock inicial reconstruido para el historial de ventas', 'grupo:stock_inicial'],
        ];
        $expected = [];
        foreach ($movements as [$tipo, $motivo, $group]) {
            $cantidad = in_array($tipo, ['venta', 'egreso', 'merma'], true) ? -1 : 1;
            $movement = InventarioMovimiento::create([
                'id_producto' => $productId, 'tipo' => $tipo, 'cantidad' => $cantidad,
                'stock_anterior' => 3, 'stock_nuevo' => 3 + $cantidad, 'motivo' => $motivo,
            ]);
            $expected[$group][] = $movement->id_movimiento;
        }

        $this->actingAs($admin)->get(route('inventario.movimientos'))
            ->assertOk()
            ->assertSee('value="grupo:venta"', false)
            ->assertSee('font-bold">-1</td>', false)
            ->assertSee('font-bold">+1</td>', false)
            ->assertDontSee('<option value="Salida por venta de cotización"', false)
            ->assertDontSee('>Otros<', false)
            ->assertDontSee('value="grupo:otros"', false);

        foreach ($expected as $group => $ids) {
            $this->get(route('inventario.movimientos', ['motivo' => $group, 'producto' => $productId]))
                ->assertOk()
                ->assertViewHas('movimientos', fn ($result): bool => $result->pluck('id_movimiento')->sort()->values()->all() === $ids);
        }
    }

    public function test_successive_acquisitions_update_the_product_price_and_keep_each_purchase_cost(): void
    {
        [$admin, $productId] = $this->createInventoryFixture(3);
        $proveedor = Proveedor::factory()->create();
        $purchase = [
            'tipo' => 'ingreso', 'motivo' => 'Compra / Adquisición', 'fecha_evento' => '2026-10-15',
            'proveedor_id' => $proveedor->id,
        ];

        $this->actingAs($admin)->post(route('inventario.movimientos.store'), [
            ...$purchase, 'productos' => [['id_producto' => $productId, 'cantidad' => 2, 'precio_entrada' => 1000, 'aplicar_precio_venta' => true]],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 5, 'precio' => 618.80]);

        $this->post(route('inventario.movimientos.store'), [
            ...$purchase, 'productos' => [['id_producto' => $productId, 'cantidad' => 2, 'precio_entrada' => 3001, 'aplicar_precio_venta' => true]],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 7, 'precio' => 1768.44]);
        $this->assertDatabaseHas('inventario_movimientos', [
            'id_producto' => $productId, 'precio_entrada' => 2000, 'cantidad_adquirida' => 2,
        ]);
        $latest = InventarioMovimiento::query()->latest('id_movimiento')->firstOrFail();
        $this->getJson(route('inventario.movimientos.show', $latest))
            ->assertOk()->assertJsonPath('precio_entrada', 6002)
            ->assertJsonPath('precio_unitario_adquisicion', 3001)
            ->assertJsonPath('precio_venta_recomendado', 4642.55);
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
            'nombre' => DB::table('productos')->where('nombre', 'Producto de prueba')->exists()
                ? 'Producto de prueba '.uniqid()
                : 'Producto de prueba',
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
