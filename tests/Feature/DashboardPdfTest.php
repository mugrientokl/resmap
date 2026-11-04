<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_pdf_button_keeps_the_selected_period(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $mes = now()->subMonthNoOverflow()->format('Y-m');

        $this->actingAs($admin)
            ->get(route('dashboard', ['mes' => $mes]))
            ->assertOk()
            ->assertSee('id="dashboard-export-pdf"', false)
            ->assertSee(e(route('dashboard.exportar.pdf', ['mes' => $mes])), false);
    }

    public function test_dashboard_pdf_view_renders_charts_and_selected_period(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $categoria = Categoria::create(['nombre_categoria' => 'PDF', 'descripcion' => 'PDF']);
        $producto = Producto::create([
            'codigo_barra' => 'PDF-001',
            'nombre' => 'Producto para PDF',
            'precio' => 1000,
            'stock' => 10,
            'stock_critico' => 2,
            'id_categoria' => $categoria->id_categoria,
        ]);
        $venta = Venta::create([
            'fecha' => now()->startOfMonth()->setTime(10, 0),
            'tipo_documento' => 'Boleta Electrónica',
            'neto' => 2000,
            'iva' => 380,
            'total' => 2380,
            'medio_pago' => 'Efectivo',
            'user_id' => $admin->id,
        ]);
        DetalleVenta::create([
            'id_venta' => $venta->id_venta,
            'id_producto' => $producto->id_producto,
            'cantidad' => 2,
            'precio_unitario' => 1000,
            'subtotal' => 2000,
        ]);

        $html = view('dashboard.pdf', [
            ...$this->datosVista($admin),
            'fechaGeneracion' => now(),
            'logoDataUri' => 'data:image/png;base64,',
        ])->render();

        $this->assertStringContainsString('Ventas por mes', $html);
        $this->assertStringContainsString('Ventas por día', $html);
        $this->assertStringContainsString('Top 10 productos más vendidos del mes', $html);
        $this->assertStringContainsString('Producto para PDF', $html);
        $this->assertStringContainsString('$2.380', $html);
        $this->assertStringNotContainsString('según corresponda', $html);
    }

    public function test_dashboard_pdf_is_downloaded_as_a_pdf_for_month_and_day(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $nombre = 'attachment; filename=dashboard-resmap-'.now()->format('Y-m-d').'.pdf';

        foreach ([[], ['mes' => now()->subMonthNoOverflow()->format('Y-m')], ['mes' => now()->format('Y-m'), 'dia' => 1]] as $filtros) {
            $this->actingAs($admin)
                ->get(route('dashboard.exportar.pdf', $filtros))
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf')
                ->assertHeader('Content-Disposition', $nombre);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function datosVista(User $admin): array
    {
        $respuesta = $this->actingAs($admin)->get(route('dashboard'));

        return $respuesta->original->getData();
    }
}
