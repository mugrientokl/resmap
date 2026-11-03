<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductAndCustomerSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_search_uses_names_and_codes_in_catalog_pos_and_inventory(): void
    {
        $categoryId = DB::table('categorias')->insertGetId([
            'nombre_categoria' => 'Filtros',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productId = $this->createProduct($categoryId, [
            'codigo_barra' => 'BAR-100',
            'codigo_origen' => 'ORG-7788',
            'nombre' => 'Filtro de aceite',
            'stock' => 2,
            'stock_critico' => 3,
        ]);
        $this->createProduct($categoryId, [
            'codigo_barra' => 'BAR-200',
            'codigo_origen' => 'ORG-8899',
            'nombre' => 'Bujía estándar',
            'stock' => 20,
            'stock_critico' => 3,
        ]);

        $catalogo = $this->get(route('catalogo.index', ['nombre' => 'filtro ORG-7788']));
        $catalogo->assertOk();
        $this->assertSame(
            [$productId],
            $catalogo->viewData('productos')->pluck('id_producto')->all()
        );

        $admin = User::factory()->create(['rol' => 'Administrador']);
        $pos = $this->actingAs($admin)
            ->get(route('pos.index', ['nombre' => 'filtro ORG-7788']));
        $pos->assertOk();
        $this->assertSame(
            [$productId],
            $pos->viewData('productos')->pluck('id_producto')->all()
        );

        $inventario = $this->get(route('productos.index', [
            'nombre' => 'filtro ORG-7788',
            'categoria' => $categoryId,
            'stock' => 'critico',
        ]));
        $inventario->assertOk();
        $this->assertSame(
            [$productId],
            $inventario->viewData('productos')->pluck('id_producto')->all()
        );
    }

    public function test_customer_search_matches_normalized_rut_and_suggests_rut_and_email(): void
    {
        $clienteId = DB::table('clientes')->insertGetId([
            'rut' => '76123456-K',
            'nombre' => 'Repuestos del Sur',
            'correo' => 'compras@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $vendedor = User::factory()->create(['rol' => 'Vendedor']);

        $response = $this->actingAs($vendedor)
            ->get(route('clientes.index', ['buscar' => '76123456k']));
        $response->assertOk();
        $this->assertSame(
            [$clienteId],
            $response->viewData('clientes')->pluck('id_cliente')->all()
        );

        $this->getJson(route('clientes.sugerencias', ['buscar' => 'compras@example.com']))
            ->assertOk()
            ->assertJsonFragment([
                'id_cliente' => $clienteId,
                'nombre' => 'Repuestos del Sur',
                'rut' => '76.123.456-K',
                'correo' => 'compras@example.com',
            ]);
    }

    public function test_public_catalog_rut_lookup_returns_only_fields_needed_to_prefill_the_quote_form(): void
    {
        DB::table('clientes')->insert([
            'rut' => '76123456-0',
            'nombre' => 'Repuestos del Sur',
            'giro' => 'Venta de repuestos',
            'correo' => 'compras@example.com',
            'telefono' => '+56987654321',
            'direccion' => 'Av. Central 123',
            'region' => 'Metropolitana de Santiago',
            'comuna' => 'Santiago',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson(route('catalogo.cliente.rut', ['rut' => '761234560']))
            ->assertOk()
            ->assertExactJson([
                'encontrado' => true,
                'cliente' => [
                    'nombre' => 'Repuestos del Sur',
                    'correo' => 'compras@example.com',
                    'telefono' => '+56987654321',
                    'direccion' => 'Av. Central 123',
                    'region' => 'Metropolitana de Santiago',
                    'comuna' => 'Santiago',
                ],
            ]);

        $this->getJson(route('catalogo.cliente.rut', ['rut' => '12345678-5']))
            ->assertOk()
            ->assertExactJson(['encontrado' => false]);
    }

    public function test_catalog_quote_form_includes_rut_autocomplete_feedback(): void
    {
        $this->get(route('catalogo.index'))
            ->assertOk()
            ->assertSee('id="rut_solicitud"', false)
            ->assertSee('id="mensaje-cliente-solicitud"', false)
            ->assertSee(route('catalogo.cliente.rut'), false)
            ->assertSee('RUT ya registrado. Se autocompletaron los datos del cliente.');
    }

    /**
     * @param  array{codigo_barra: string, codigo_origen: string, nombre: string, stock: int, stock_critico: int}  $attributes
     */
    private function createProduct(int $categoryId, array $attributes): int
    {
        return DB::table('productos')->insertGetId([
            ...$attributes,
            'precio' => 1000,
            'id_categoria' => $categoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
