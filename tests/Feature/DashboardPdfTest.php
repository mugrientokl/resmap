<?php

namespace Tests\Feature;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DashboardPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_displays_pdf_download_button(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="dashboard-export-pdf"', false)
            ->assertSee(route('dashboard.exportar.pdf', ['agrupacion' => 'month']), false);
    }

    public function test_dashboard_pdf_download_includes_selected_chart_filters(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $mesActual = now()->format('Y-m');
        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('download')
            ->once()
            ->with('dashboard-resmap-'.now()->format('Y-m-d').'.pdf')
            ->andReturn(response('pdf')->header(
                'Content-Disposition',
                'attachment; filename=dashboard-resmap-'.now()->format('Y-m-d').'.pdf'
            ));

        Pdf::shouldReceive('loadView')
            ->once()
            ->with('dashboard.pdf', Mockery::on(
                fn (array $data): bool => $data['agrupacion'] === 'week'
                    && $data['mesSeleccionado'] === now()->locale('es')->isoFormat('MMMM YYYY')
                    && $data['ventasPorPeriodo'] === $data['ventasSemanalesPorMes'][$mesActual]
                    && isset($data['fechaGeneracion'])
            ))
            ->andReturn($pdf);

        $this->actingAs($admin)
            ->get(route('dashboard.exportar.pdf', [
                'agrupacion' => 'week',
                'mes' => $mesActual,
            ]))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=dashboard-resmap-'.now()->format('Y-m-d').'.pdf');
    }

    public function test_dashboard_pdf_is_rendered_and_downloaded_as_a_pdf(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)
            ->get(route('dashboard.exportar.pdf'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader(
                'Content-Disposition',
                'attachment; filename=dashboard-resmap-'.now()->format('Y-m-d').'.pdf'
            );
    }
}
