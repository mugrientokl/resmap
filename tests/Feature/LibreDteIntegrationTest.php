<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venta;
use App\Services\Facturacion\LibreDteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LibreDteIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_temporary_mode_calls_emitir_once_and_stores_temporal_code(): void
    {
        config([
            'services.libredte.enabled' => true,
            'services.libredte.mode' => 'temporary',
            'services.libredte.environment' => 'testing',
            'services.libredte.url' => 'https://libredte.test',
            'services.libredte.api_key' => 'encoded-api-key',
            'services.libredte.rut_emisor' => '76192083-9',
        ]);

        Http::fake([
            'https://libredte.test/api/dte/documentos/emitir*' => Http::response(['codigo' => 'temporary-code']),
        ]);

        $seller = User::factory()->create(['rol' => 'Vendedor']);
        $categoryId = DB::table('categorias')->insertGetId([
            'nombre_categoria' => 'Pruebas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productId = DB::table('productos')->insertGetId([
            'codigo_barra' => 'DTE-TEST-001',
            'nombre' => 'Producto DTE',
            'precio' => 1190,
            'stock' => 5,
            'stock_critico' => 1,
            'id_categoria' => $categoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($seller)->postJson('/ventas', [
            'tipo_documento' => 'Boleta Electrónica',
            'medio_pago' => 'Efectivo',
            'rut' => '66666666-6',
            'nombre_cliente' => 'Cliente temporal',
            'detalles' => [
                ['id_producto' => $productId, 'cantidad' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('resultado.dte.status', 'temporary')
            ->assertJsonPath('resultado.dte.codigo_temporal', 'temporary-code');

        $this->assertDatabaseHas('ventas', [
            'codigo_dte_temporal' => 'temporary-code',
            'estado_dte' => 'temporal',
        ]);

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/api/dte/documentos/emitir')
                && str_contains($request->url(), '_contribuyente_certificacion=1')
                && $request->data()['Encabezado']['IdDoc']['TipoDTE'] === 39;
        });
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/api/dte/documentos/generar') === false;
        });
    }

    public function test_simulation_mode_never_calls_libredte(): void
    {
        config([
            'services.libredte.enabled' => true,
            'services.libredte.mode' => 'simulation',
        ]);

        Http::fake();

        $result = (new LibreDteService)->emitir(\Mockery::mock(Venta::class));

        $this->assertSame('simulated', $result['status']);
        Http::assertNothingSent();
    }

    public function test_production_mode_is_blocked(): void
    {
        config([
            'services.libredte.enabled' => true,
            'services.libredte.mode' => 'production',
        ]);

        Http::fake();

        $result = (new LibreDteService)->emitir(\Mockery::mock(Venta::class));

        $this->assertSame('blocked', $result['status']);
        Http::assertNothingSent();
    }
}
