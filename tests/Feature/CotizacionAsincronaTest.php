<?php

namespace Tests\Feature;

use App\Jobs\EnviarCotizacionEmail;
use App\Jobs\EnviarCotizacionWhatsApp;
use App\Models\CotizacionVersion;
use App\Models\User;
use App\Services\CotizacionPdf;
use App\Services\WhatsAppCloudApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class CotizacionAsincronaTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_delivery_is_dispatched_without_blocking_external_channels(): void
    {
        $version = $this->createQuote();
        $solicitudId = $version->cotizacion->solicitud_id;
        Queue::fake();

        $this->post(route('cotizaciones.enviar', $version))
            ->assertRedirect(route('solicitudes.show', $solicitudId))
            ->assertSessionHas(
                'success',
                'La cotización quedó en cola para envío. Puedes revisar el estado de cada canal.',
            );

        Queue::assertPushed(EnviarCotizacionEmail::class, fn (EnviarCotizacionEmail $job): bool => $job->versionId === $version->id_version);
        Queue::assertPushed(EnviarCotizacionWhatsApp::class, fn (EnviarCotizacionWhatsApp $job): bool => $job->versionId === $version->id_version);
        $this->assertDatabaseHas('cotizacion_versiones', [
            'id_version' => $version->id_version,
            'estado' => 'enviada',
            'email_estado' => 'en_cola',
            'whatsapp_estado' => 'en_cola',
        ]);
    }

    public function test_delivery_status_requires_permission_and_reports_changes_without_writing_data(): void
    {
        $version = $this->createQuote();
        Queue::fake();
        $this->post(route('cotizaciones.enviar', $version))->assertSessionHasNoErrors();
        $url = route('cotizaciones.estado-envio', $version);

        $this->getJson($url)->assertOk()->assertExactJson([
            'email_estado' => 'en_cola',
            'whatsapp_estado' => 'en_cola',
            'pendiente' => true,
        ]);
        $version->update(['email_estado' => 'aceptado_por_smtp', 'whatsapp_estado' => 'fallido']);
        $this->getJson($url)->assertOk()->assertExactJson([
            'email_estado' => 'aceptado_por_smtp',
            'whatsapp_estado' => 'fallido',
            'pendiente' => false,
        ]);
        $version->update(['estado' => 'aceptada', 'whatsapp_estado' => 'en_cola']);
        $this->getJson($url)->assertOk()->assertExactJson([
            'email_estado' => 'aceptado_por_smtp',
            'whatsapp_estado' => 'cancelado',
            'pendiente' => false,
        ]);
        $this->assertSame('en_cola', $version->fresh()->whatsapp_estado);

        $this->actingAs(User::factory()->create(['rol' => 'Vendedor', 'permisos' => ['dashboard.ver']]))
            ->getJson($url)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson($url)->assertUnauthorized();
    }

    public function test_response_and_replacement_cancel_only_pending_deliveries(): void
    {
        $version = $this->createQuote();
        Queue::fake();
        $this->post(route('cotizaciones.enviar', $version))->assertSessionHasNoErrors();
        $version->update(['email_estado' => 'aceptado_por_smtp']);
        $this->post(route('cotizaciones.respuesta-interna', $version), ['decision' => 'cambios_solicitados'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cotizacion_versiones', [
            'id_version' => $version->id_version,
            'email_estado' => 'aceptado_por_smtp',
            'whatsapp_estado' => 'cancelado',
        ]);

        $version->update(['whatsapp_estado' => 'en_cola']);
        $this->post(route('solicitudes.cotizacion.crear', $version->cotizacion->solicitud_id))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cotizacion_versiones', [
            'id_version' => $version->id_version,
            'estado' => 'reemplazada',
            'email_estado' => 'aceptado_por_smtp',
            'whatsapp_estado' => 'cancelado',
        ]);
    }

    public function test_late_jobs_cancel_obsolete_deliveries_without_contacting_providers(): void
    {
        $version = $this->createQuote();
        Queue::fake();
        Mail::fake();
        $this->post(route('cotizaciones.enviar', $version))->assertSessionHasNoErrors();
        $version->cotizacion->update(['ultima_version' => 2]);
        $pdf = Mockery::mock(CotizacionPdf::class);
        $pdf->shouldNotReceive('generar');
        $whatsapp = Mockery::mock(WhatsAppCloudApi::class);
        $whatsapp->shouldNotReceive('enviarPlantillaCotizacion');

        (new EnviarCotizacionEmail($version->id_version, 'https://example.test/pdf'))->handle($pdf);
        (new EnviarCotizacionWhatsApp($version->id_version, '+56912345678', 'https://example.test/pdf'))->handle($whatsapp);

        Mail::assertNothingSent();
        $this->assertDatabaseHas('cotizacion_versiones', [
            'id_version' => $version->id_version,
            'email_estado' => 'cancelado',
            'whatsapp_estado' => 'cancelado',
        ]);
    }

    private function createQuote(): CotizacionVersion
    {
        Notification::fake();
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.api_version' => 'v25.0',
            'services.whatsapp.phone_number_id' => 'test-phone',
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.quote_template' => 'test-template',
        ]);
        $categoriaId = DB::table('categorias')->insertGetId([
            'nombre_categoria' => 'Prueba',
        ]);
        $productoId = DB::table('productos')->insertGetId([
            'codigo_barra' => 'ASY001',
            'nombre' => 'Producto asíncrono',
            'precio' => 1190,
            'stock' => 2,
            'stock_critico' => 1,
            'id_categoria' => $categoriaId,
        ]);
        $this->post(route('catalogo.solicitudes.store'), [
            'rut' => '12345678-5',
            'nombre' => 'Cliente de prueba',
            'contacto' => 'Contacto',
            'correo' => 'cliente@example.test',
            'prefijo_telefono' => '+569',
            'telefono' => '12345678',
            'region' => 'Metropolitana',
            'comuna' => 'Santiago',
            'detalles_productos' => [
                ['id_producto' => $productoId, 'cantidad' => 1],
            ],
        ])->assertRedirect(route('catalogo.index'));

        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $this->actingAs(User::factory()->create(['rol' => 'Administrador']))
            ->post(route('solicitudes.cotizacion.crear', $solicitudId))
            ->assertSessionHasNoErrors();

        return CotizacionVersion::query()->firstOrFail();
    }
}
