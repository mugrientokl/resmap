<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use App\Support\Rut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RutNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rut_is_normalized_and_formatted_from_any_input(): void
    {
        foreach (['12.345.678-5', '12345678-5', '123456785', ' 12345678 5 '] as $entrada) {
            $this->assertSame('12345678-5', Rut::normalizar($entrada));
            $this->assertSame('12.345.678-5', Rut::formatear($entrada));
        }

        $this->assertSame('7654321-K', Rut::normalizar('7.654.321-k'));
        $this->assertSame('1.234.567-8', Rut::formatear('12345678'));
        $this->assertSame('76.123.456-K', Rut::formatear('76123456k'));
        $this->assertNull(Rut::normalizar(null));
        $this->assertNull(Rut::normalizar('abc'));
    }

    public function test_cliente_model_always_stores_canonical_rut(): void
    {
        $cliente = Cliente::create(['rut' => '12.345.678-5', 'nombre' => 'Cliente']);

        $this->assertSame('12345678-5', $cliente->fresh()->rut);
        $this->assertSame('12.345.678-5', $cliente->fresh()->rut_formateado);
    }

    public function test_pos_sale_with_different_rut_formats_does_not_duplicate_clients(): void
    {
        $vendedor = User::factory()->create(['rol' => 'Vendedor']);
        $categoriaId = DB::table('categorias')->insertGetId([
            'nombre_categoria' => 'Pruebas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productoId = DB::table('productos')->insertGetId([
            'codigo_barra' => 'RUT-TEST-001',
            'nombre' => 'Producto',
            'precio' => 1190,
            'stock' => 10,
            'stock_critico' => 1,
            'id_categoria' => $categoriaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['12.345.678-5', '123456785', '12345678-5'] as $rut) {
            $this->actingAs($vendedor)->postJson('/ventas', [
                'tipo_documento' => 'Boleta Electrónica',
                'medio_pago' => 'Efectivo',
                'rut' => $rut,
                'nombre_cliente' => 'Cliente',
                'detalles' => [['id_producto' => $productoId, 'cantidad' => 1]],
            ])->assertCreated();
        }

        $this->assertSame(1, Cliente::count());
        $this->assertDatabaseHas('clientes', ['rut' => '12345678-5']);
    }

    public function test_rut_lookup_and_update_accept_formatted_input(): void
    {
        $vendedor = User::factory()->create(['rol' => 'Administrador']);
        $cliente = Cliente::create(['rut' => '12345678-5', 'nombre' => 'Cliente']);

        $this->getJson(route('catalogo.cliente.rut', ['rut' => '12.345.678-5']))
            ->assertOk()
            ->assertJsonPath('encontrado', true);

        $this->actingAs($vendedor)
            ->put(route('clientes.update', $cliente->id_cliente), [
                'rut' => '11.111.111-1',
                'nombre' => 'Cliente',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('11111111-1', $cliente->fresh()->rut);
    }

    public function test_updating_a_client_with_an_existing_rut_in_other_format_is_rejected(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        Cliente::create(['rut' => '12345678-5', 'nombre' => 'Uno']);
        $otro = Cliente::create(['rut' => '11111111-1', 'nombre' => 'Otro']);

        $this->actingAs($admin)
            ->put(route('clientes.update', $otro->id_cliente), [
                'rut' => '12.345.678-5',
                'nombre' => 'Otro',
            ])
            ->assertSessionHasErrors('rut');
    }
}
