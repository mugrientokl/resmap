<?php

namespace Tests\Feature;

use App\Mail\CotizacionCliente;
use App\Models\User;
use App\Notifications\OrdenCompraRecibida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class B2BQuotationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_b2b_quote_acceptance_keeps_stock_until_successful_simulated_billing(): void
    {
        Mail::fake();
        Notification::fake();
        Storage::fake('local');
        Http::fake();
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 2, 'cliente@example.test');
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $vendedor = User::factory()->create(['rol' => 'Vendedor']);

        $this->actingAs($admin)
            ->post(route('solicitudes.cotizacion.crear', $solicitudId))
            ->assertRedirect(route('solicitudes.show', $solicitudId));

        $this->actingAs($admin)->get(route('solicitudes.index'))
            ->assertOk()
            ->assertSee('Cotización lista para enviar');

        $versionId = (int) DB::table('cotizacion_versiones')->value('id_version');
        $this->actingAs($admin)->get(route('cotizaciones.pdf', $versionId))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->post(route('cotizaciones.enviar', $versionId))
            ->assertRedirect(route('solicitudes.show', $solicitudId));

        $mensaje = Mail::sent(CotizacionCliente::class)->first();
        $this->assertNotNull($mensaje);
        preg_match(
            '~/cotizaciones/\d+/responder/([A-Za-z0-9]{48})$~',
            (string) parse_url($mensaje->urlRespuesta, PHP_URL_PATH),
            $coincidencias,
        );
        $this->assertArrayHasKey(1, $coincidencias);
        $this->get(route('cotizaciones.responder.form', [
            'version' => $versionId,
            'token' => $coincidencias[1],
        ]))->assertOk()
            ->assertSee('Revisión de cotización')
            ->assertSee('Tu respuesta')
            ->assertSee('Adjuntar OC de mi empresa')
            ->assertSee('RESMAP');

        auth()->logout();
        $this->post(route('cotizaciones.responder', [
            'version' => $versionId,
            'token' => $coincidencias[1],
        ]), [
            'telefono' => '+56912345678',
            'decision' => 'aceptar',
            'metodo_entrega' => 'retiro',
            'autorizar_oc_automatica' => '1',
        ])->assertRedirect(route('home'));

        $ventaId = (int) DB::table('ventas')->value('id_venta');
        $ordenCompraRow = DB::table('ordenes_compra')->where('version_id', $versionId)->first();
        $this->assertNotNull($ordenCompraRow?->archivo_path);
        Storage::disk('local')->assertExists($ordenCompraRow->archivo_path);
        $this->assertSame('application/pdf', Storage::disk('local')->mimeType($ordenCompraRow->archivo_path));
        Notification::assertSentTo($admin, OrdenCompraRecibida::class);
        $notificacionOrden = Notification::sent($admin, OrdenCompraRecibida::class)->first();
        $this->assertNotNull($notificacionOrden);
        $this->assertCount(1, $notificacionOrden->toMail($admin)->attachments);
        Notification::assertSentTo($vendedor, OrdenCompraRecibida::class);
        $this->actingAs($admin)->get(route('cotizaciones.orden-compra.ver', $ordenCompraRow->id_orden_compra))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="orden-compra-'.strtolower($ordenCompraRow->numero).'.pdf"');
        $this->assertDatabaseHas('ventas', [
            'id_venta' => $ventaId,
            'estado_venta' => 'pendiente_confirmacion',
            'estado_facturacion' => 'pendiente',
            'folio_sii' => null,
        ]);
        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 5]);

        $ordenCompra = (array) DB::table('ordenes_compra')->where('version_id', $versionId)->first();
        $this->actingAs($admin)->get(route('solicitudes.show', $solicitudId))
            ->assertOk()
            ->assertSee('Orden de compra recibida; pendiente del equipo comercial')
            ->assertSee('Confirmar pago');
        $this->actingAs($admin)->get(route('solicitudes.show', $solicitudId))->assertSee('Ver OC');
        $this->get(route('cotizaciones.responder.form', [
            'version' => $versionId,
            'token' => $coincidencias[1],
        ]))->assertStatus(410)
            ->assertSee('Este enlace ya no está disponible')
            ->assertDontSee('Symfony\\Component\\HttpKernel\\Exception');
        DB::table('ordenes_compra')->where('version_id', $versionId)->delete();
        $this->actingAs($admin)->post(route('ventas.confirmar-comercial', $ventaId), [
            'tipo_confirmacion' => 'pago',
            'monto_pagado' => 2380,
            'medio_pago' => 'Transferencia',
        ])->assertSessionHasErrors('orden_compra');
        DB::table('ordenes_compra')->insert($ordenCompra);

        $this->actingAs($admin)->post(route('ventas.confirmar-comercial', $ventaId), [
            'tipo_confirmacion' => 'pago',
            'monto_pagado' => 2380,
            'medio_pago' => 'Transferencia',
        ])->assertRedirect();

        $this->post(route('ventas.simular-facturacion', $ventaId), ['resultado' => 'error'])
            ->assertRedirect();
        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 5]);

        $this->post(route('ventas.simular-facturacion', $ventaId), ['resultado' => 'exito'])
            ->assertRedirect();
        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 3]);
        $detalleVentaUrl = route('reportes.ventas.detalle', $ventaId);
        $this->actingAs($admin)->get(route('reportes.index'))
            ->assertOk()
            ->assertDontSee('data-sale-detail="'.$detalleVentaUrl.'"', false)
            ->assertDontSee('ventas.confirmar-comercial')
            ->assertDontSee('ventas.simular-facturacion');
        $this->actingAs($admin)->get(route('reportes.ventas.detalle', $ventaId))->assertNotFound();
        $this->post(route('ventas.registrar-entrega', $ventaId))->assertRedirect();
        $this->assertDatabaseHas('ventas', [
            'id_venta' => $ventaId,
            'estado_entrega' => 'entregada',
        ]);
        $this->actingAs($admin)->get(route('reportes.index'))->assertSee('data-sale-detail="'.$detalleVentaUrl.'"', false);
        $this->actingAs($admin)->get(route('reportes.ventas.detalle', $ventaId))->assertOk();
        $this->assertDatabaseHas('ventas', [
            'id_venta' => $ventaId,
            'estado_facturacion' => 'simulada_exitosa',
        ]);

        $this->post(route('ventas.simular-facturacion', $ventaId), ['resultado' => 'exito'])
            ->assertRedirect();
        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 3]);
        $this->assertDatabaseCount('inventario_movimientos', 1);
        $this->assertDatabaseCount('eventos_facturacion', 2);
        Http::assertNothingSent();
    }

    public function test_vendor_can_record_partial_and_final_payments_on_authorized_credit(): void
    {
        Mail::fake();
        Storage::fake('local');
        Http::fake();
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 2, 'cliente@example.test');
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $vendedor = User::factory()->create(['rol' => 'Vendedor']);

        $this->actingAs($admin)->post(route('solicitudes.cotizacion.crear', $solicitudId));
        $versionId = (int) DB::table('cotizacion_versiones')->value('id_version');
        $this->post(route('cotizaciones.enviar', $versionId));
        $mensaje = Mail::sent(CotizacionCliente::class)->first();
        preg_match(
            '~/cotizaciones/\d+/responder/([A-Za-z0-9]{48})$~',
            (string) parse_url($mensaje->urlRespuesta, PHP_URL_PATH),
            $coincidencias,
        );
        auth()->logout();
        $this->post(route('cotizaciones.responder', [
            'version' => $versionId,
            'token' => $coincidencias[1],
        ]), [
            'telefono' => '+56912345678',
            'decision' => 'aceptar',
            'metodo_entrega' => 'retiro',
            'modalidad_oc' => 'automatica',
        ])->assertRedirect(route('home'));

        $ventaId = (int) DB::table('ventas')->value('id_venta');
        $this->actingAs($admin)->post(route('ventas.confirmar-comercial', $ventaId), [
            'tipo_confirmacion' => 'credito',
        ])->assertRedirect();
        $this->assertDatabaseHas('ventas', [
            'id_venta' => $ventaId,
            'estado_pago' => 'credito_autorizado',
            'monto_pagado' => 0,
        ]);
        $this->actingAs($vendedor)->post(route('ventas.registrar-pago-credito', $ventaId), [
            'monto_pagado' => 1000,
            'medio_pago' => 'Transferencia',
        ])->assertRedirect();
        $this->assertDatabaseHas('ventas', [
            'id_venta' => $ventaId,
            'estado_pago' => 'credito_parcial',
            'monto_pagado' => 1000,
        ]);
        $this->actingAs($vendedor)->post(route('ventas.registrar-pago-credito', $ventaId), [
            'monto_pagado' => 1381,
            'medio_pago' => 'Transferencia',
        ])->assertSessionHasErrors('monto_pagado');
        $this->actingAs($admin)->post(route('ventas.simular-facturacion', $ventaId), [
            'resultado' => 'exito',
        ])->assertRedirect();
        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 3]);

        $this->actingAs($vendedor)->get(route('solicitudes.show', $solicitudId))
            ->assertOk()
            ->assertSee('Crédito con abonos')
            ->assertSee('Registrar abono al crédito')
            ->assertSee('Saldo pendiente');
        $respuestaPagoFinal = $this->post(route('ventas.registrar-pago-credito', $ventaId), [
            'monto_pagado' => 1380,
            'medio_pago' => 'Transferencia',
        ]);
        $respuestaPagoFinal->assertRedirect();
        $this->assertDatabaseHas('ventas', [
            'id_venta' => $ventaId,
            'estado_pago' => 'pagado',
            'monto_pagado' => 2380,
        ]);
    }

    public function test_customer_can_select_delivery_and_total_uses_configured_shipping_charge(): void
    {
        Mail::fake();
        Notification::fake();
        Storage::fake('local');
        Http::fake();
        config(['services.cotizaciones.costo_despacho' => 2500]);
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 2, 'cliente@example.test');
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)->post(route('solicitudes.cotizacion.crear', $solicitudId));
        $versionId = (int) DB::table('cotizacion_versiones')->value('id_version');
        $this->post(route('cotizaciones.enviar', $versionId));
        $mensaje = Mail::sent(CotizacionCliente::class)->first();
        preg_match(
            '~/cotizaciones/\d+/responder/([A-Za-z0-9]{48})$~',
            (string) parse_url($mensaje->urlRespuesta, PHP_URL_PATH),
            $coincidencias,
        );
        $this->get(route('cotizaciones.responder.form', [
            'version' => $versionId,
            'token' => $coincidencias[1],
        ]))->assertOk()
            ->assertSee('name="metodo_entrega"', false)
            ->assertSee('Delivery a domicilio')
            ->assertSee('name="calle"', false)
            ->assertDontSee('La enviaré más adelante');

        auth()->logout();
        $datosRespuesta = [
            'telefono' => '+56912345678',
            'decision' => 'aceptar',
            'metodo_entrega' => 'despacho',
            'calle' => 'Avenida Central',
            'numero' => '123',
            'comuna' => 'Santiago',
            'ciudad' => 'Santiago',
            'region' => 'Metropolitana',
        ];
        $this->post(route('cotizaciones.responder', [
            'version' => $versionId,
            'token' => $coincidencias[1],
        ]), [
            ...$datosRespuesta,
            'metodo_entrega' => null,
            'modalidad_oc' => 'automatica',
        ])->assertSessionHasErrors('metodo_entrega');
        $this->post(route('cotizaciones.responder', [
            'version' => $versionId,
            'token' => $coincidencias[1],
        ]), $datosRespuesta)->assertSessionHasErrors('modalidad_oc');
        $this->post(route('cotizaciones.responder', [
            'version' => $versionId,
            'token' => $coincidencias[1],
        ]), [
            ...$datosRespuesta,
            'modalidad_oc' => 'automatica',
            'calle' => '',
        ])->assertSessionHasErrors('calle');
        $this->post(route('cotizaciones.responder', [
            'version' => $versionId,
            'token' => $coincidencias[1],
        ]), [
            ...$datosRespuesta,
            'modalidad_oc' => 'automatica',
        ])->assertRedirect(route('home'));

        $venta = DB::table('ventas')->where('cotizacion_version_id', $versionId)->first();
        $this->assertNotNull($venta);
        $this->assertSame('despacho', $venta->metodo_entrega);
        $this->assertSame('Avenida Central 123', $venta->direccion_despacho);
        $this->assertSame('Santiago', $venta->comuna_despacho);
        $this->assertSame('Santiago', $venta->ciudad_despacho);
        $this->assertSame('Metropolitana', $venta->region_despacho);
        $this->assertSame(2500.0, (float) $venta->total - 2380.0);
        $this->actingAs($admin)->get(route('solicitudes.show', $solicitudId))
            ->assertOk()
            ->assertSee('Delivery a domicilio')
            ->assertSee('Avenida Central 123')
            ->assertSee('Santiago, Santiago, Metropolitana');
        $this->assertDatabaseHas('cotizacion_versiones', [
            'id_version' => $versionId,
            'metodo_entrega' => 'despacho',
            'costo_despacho' => 2500,
            'total' => 4880,
            'calle' => 'Avenida Central',
        ]);
    }

    public function test_http_errors_use_resmap_branded_pages(): void
    {
        $admin = User::factory()->create(['rol' => 'Vendedor']);

        $this->actingAs($admin)->get(route('reportes.index'))
            ->assertForbidden()
            ->assertSee('No tienes permiso para ingresar')
            ->assertSee('ERROR 403')
            ->assertDontSee('Symfony\\Component\\HttpKernel\\Exception');

        $this->get('/ruta-que-no-existe')
            ->assertNotFound()
            ->assertSee('No encontramos esta página')
            ->assertSee('RESMAP');
    }

    public function test_product_request_reserves_stock_without_decrementing_physical_inventory(): void
    {
        $productId = $this->createProductWithStock(5);

        $this->createProductRequest($productId, 3);

        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 5]);
        $this->assertDatabaseHas('reservas_inventario', [
            'producto_id' => $productId,
            'cantidad' => 3,
            'estado' => 'activa',
        ]);
    }

    public function test_legacy_request_completion_cannot_bypass_b2b_quote_flow(): void
    {
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 2);
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)
            ->patch(route('solicitudes.estado', $solicitudId), ['estado' => 'Entregado y completado'])
            ->assertRedirect(route('solicitudes.show', $solicitudId))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 5]);
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_legacy_completion_cannot_consume_stock_reserved_for_another_quote(): void
    {
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 3);
        $clienteId = (int) DB::table('clientes')->value('id_cliente');
        $solicitudId = DB::table('solicitud_webs')->insertGetId([
            'fecha' => now(),
            'estado' => 'Pendiente',
            'tipo_solicitud' => 'pedido',
            'id_cliente' => $clienteId,
            'detalles_productos' => json_encode([
                ['id_producto' => $productId, 'cantidad' => 3],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)
            ->patch(route('solicitudes.estado', $solicitudId), ['estado' => 'Entregado y completado'])
            ->assertRedirect(route('solicitudes.show', $solicitudId))
            ->assertSessionHas('error', 'Stock disponible insuficiente para Producto B2B de prueba; hay 2 unidades libres.');

        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 5]);
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_manual_product_stock_edit_cannot_reduce_stock_below_active_reservations(): void
    {
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 3);
        $producto = DB::table('productos')->where('id_producto', $productId)->first();
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)
            ->put(route('productos.update', $productId), [
                'codigo_barra' => $producto->codigo_barra,
                'nombre' => $producto->nombre,
                'precio' => $producto->precio,
                'stock' => 2,
                'stock_critico' => $producto->stock_critico,
                'unidad' => $producto->unidad,
                'activo' => $producto->activo,
                'id_categoria' => $producto->id_categoria,
            ])
            ->assertSessionHasErrors('stock');

        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 5]);
    }

    public function test_consented_customer_receives_an_approved_whatsapp_template_through_the_test_allowlist(): void
    {
        Mail::fake();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test-message']], 'contacts' => []]),
        ]);
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.api_version' => 'v25.0',
            'services.whatsapp.phone_number_id' => 'test-phone-number-id',
            'services.whatsapp.access_token' => 'test-access-token',
            'services.whatsapp.test_mode' => true,
            'services.whatsapp.test_recipients' => ['+56912345678'],
            'services.whatsapp.quote_template' => 'resmap_quote_es_cl',
        ]);
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 1);
        DB::table('clientes')->update([
            'consentimiento_whatsapp_at' => now(),
            'consentimiento_whatsapp_origen' => 'prueba',
        ]);
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)
            ->post(route('solicitudes.cotizacion.crear', $solicitudId))
            ->assertRedirect(route('solicitudes.show', $solicitudId));

        $versionId = (int) DB::table('cotizacion_versiones')->value('id_version');
        $this->post(route('cotizaciones.enviar', $versionId))
            ->assertRedirect(route('solicitudes.show', $solicitudId));

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/test-phone-number-id/messages')
            && $request->data()['type'] === 'template'
            && $request->data()['template']['name'] === 'resmap_quote_es_cl');
        $this->assertDatabaseHas('mensajes_whatsapp', [
            'version_id' => $versionId,
            'estado' => 'aceptado_por_meta',
            'id_proveedor' => 'wamid.test-message',
        ]);

        $datosRegistrados = (string) DB::table('mensajes_whatsapp')->where('version_id', $versionId)->value('datos');
        $this->assertStringContainsString('[REDACTED]', $datosRegistrados);
        $this->assertSame(0, preg_match('/quote:\d+:aceptar:[A-Za-z0-9]{48}/', $datosRegistrados));
    }

    public function test_signed_whatsapp_stop_message_revokes_customer_consent(): void
    {
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 1);
        DB::table('clientes')->update([
            'consentimiento_whatsapp_at' => now(),
            'consentimiento_whatsapp_origen' => 'catalogo',
        ]);
        config(['services.whatsapp.app_secret' => 'test-app-secret']);
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '56912345678',
                            'id' => 'wamid.inbound-stop',
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => 'STOP'],
                        ]],
                    ],
                ]],
            ]],
        ];
        $cuerpo = json_encode($payload, JSON_THROW_ON_ERROR);
        $firma = 'sha256='.hash_hmac('sha256', $cuerpo, 'test-app-secret');

        $this->call('POST', route('whatsapp.webhook.recibir'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $firma,
        ], $cuerpo)->assertOk();

        $this->assertDatabaseHas('clientes', [
            'telefono' => '+56912345678',
            'consentimiento_whatsapp_at' => null,
            'consentimiento_whatsapp_origen' => 'whatsapp_opt_out',
        ]);
    }

    public function test_signed_whatsapp_template_reply_accepts_only_from_the_registered_phone(): void
    {
        Mail::fake();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.quote-template']], 'contacts' => []]),
        ]);
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.api_version' => 'v25.0',
            'services.whatsapp.phone_number_id' => 'test-phone-number-id',
            'services.whatsapp.access_token' => 'test-access-token',
            'services.whatsapp.test_mode' => true,
            'services.whatsapp.test_recipients' => ['+56912345678'],
            'services.whatsapp.quote_template' => 'resmap_quote_es_cl',
            'services.whatsapp.app_secret' => 'test-app-secret',
        ]);
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 2);
        DB::table('clientes')->update([
            'consentimiento_whatsapp_at' => now(),
            'consentimiento_whatsapp_origen' => 'prueba',
        ]);
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)
            ->post(route('solicitudes.cotizacion.crear', $solicitudId))
            ->assertRedirect(route('solicitudes.show', $solicitudId));

        $versionId = (int) DB::table('cotizacion_versiones')->value('id_version');
        $this->post(route('cotizaciones.enviar', $versionId))
            ->assertRedirect(route('solicitudes.show', $solicitudId));
        $peticion = Http::recorded()->first()[0];
        $payloadBoton = $peticion->data()['template']['components'][1]['parameters'][0]['payload'];

        foreach (['56900000000', '56912345678'] as $indice => $remitente) {
            $payload = [
                'object' => 'whatsapp_business_account',
                'entry' => [[
                    'changes' => [[
                        'value' => [
                            'messages' => [[
                                'from' => $remitente,
                                'id' => 'wamid.inbound-'.$indice,
                                'timestamp' => (string) now()->timestamp,
                                'type' => 'button',
                                'button' => ['text' => 'Aceptar', 'payload' => $payloadBoton],
                            ]],
                        ],
                    ]],
                ]],
            ];
            $cuerpo = json_encode($payload, JSON_THROW_ON_ERROR);
            $firma = 'sha256='.hash_hmac('sha256', $cuerpo, 'test-app-secret');
            $this->call('POST', route('whatsapp.webhook.recibir'), [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $firma,
            ], $cuerpo)->assertOk();
        }

        $this->assertDatabaseHas('cotizacion_versiones', [
            'id_version' => $versionId,
            'estado' => 'aceptada',
        ]);
        $this->assertDatabaseCount('ventas', 1);
        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 5]);
    }

    private function createProductRequest(int $productId, int $cantidad, ?string $correo = null): void
    {
        $datos = [
            'rut' => '12345678-5',
            'nombre' => 'Cliente de prueba',
            'prefijo_telefono' => '+569',
            'telefono' => '12345678',
            'region' => 'Metropolitana',
            'comuna' => 'Santiago',
            'detalles_productos' => [
                ['id_producto' => $productId, 'cantidad' => $cantidad],
            ],
        ];
        if ($correo !== null) {
            $datos['correo'] = $correo;
        }

        $this->post(route('catalogo.solicitudes.store'), $datos)
            ->assertRedirect(route('catalogo.index'));
    }

    private function createProductWithStock(int $stock): int
    {
        $categoryId = DB::table('categorias')->insertGetId([
            'nombre_categoria' => 'Repuestos',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('productos')->insertGetId([
            'codigo_barra' => 'B2B-'.uniqid(),
            'nombre' => 'Producto B2B de prueba',
            'precio' => 1190,
            'stock' => $stock,
            'stock_critico' => 1,
            'id_categoria' => $categoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
