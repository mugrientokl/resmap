<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryMovementTest extends TestCase
{
    use RefreshDatabase;

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
        ])->assertRedirect(route('inventario.movimientos'));

        $this->assertDatabaseHas('inventario_movimientos', [
            'id_producto' => $productId,
            'user_id' => $admin->id,
            'cantidad' => 5,
            'stock_anterior' => 3,
            'stock_nuevo' => 8,
            'motivo' => 'Conteo físico',
        ]);
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

        $this->actingAs($admin)->post(route('inventario.movimientos.store'), [
            'tipo' => 'adquisicion',
            'id_producto' => $productId,
            'cantidad' => 7,
            'motivo' => 'Compra de reposición',
            'proveedor' => 'Proveedor Central SpA',
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

    public function test_admin_can_fetch_the_full_movement_detail(): void
    {
        [$admin, $productId] = $this->createInventoryFixture(3);

        $this->actingAs($admin)->post(route('inventario.movimientos.store'), [
            'tipo' => 'adquisicion',
            'id_producto' => $productId,
            'cantidad' => 2,
            'motivo' => 'Compra de prueba',
            'proveedor' => 'Proveedor de prueba',
            'precio_entrada' => 1000,
        ]);
        $movementId = (int) DB::table('inventario_movimientos')->latest('id_movimiento')->value('id_movimiento');

        $this->actingAs($admin)
            ->getJson(route('inventario.movimientos.show', $movementId))
            ->assertOk()
            ->assertJsonPath('tipo', 'adquisicion')
            ->assertJsonPath('proveedor', 'Proveedor de prueba')
            ->assertJsonPath('cantidad_adquirida', 2)
            ->assertJsonPath('precio_unitario_adquisicion', 500)
            ->assertJsonPath('precio_venta_recomendado', 595);
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
