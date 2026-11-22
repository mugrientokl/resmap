<?php

namespace Tests\Feature;

use App\Jobs\EnviarCotizacionEmail;
use App\Jobs\EnviarCotizacionWhatsApp;
use App\Models\CotizacionVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CotizacionAsincronaTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_delivery_is_dispatched_without_blocking_external_channels(): void
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

        $version = CotizacionVersion::query()->firstOrFail();
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
}
