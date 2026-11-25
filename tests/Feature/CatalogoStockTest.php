<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogoStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_show_the_public_navigation_and_mark_the_current_page(): void
    {
        DB::table('configuraciones')->insert(['clave' => 'posicion_navegacion', 'valor' => 'izquierda']);

        foreach (['home', 'catalogo.index', 'servicios.index'] as $page) {
            $response = $this->get(route($page))->assertOk()
                ->assertSee('navegacion-superior public-navigation')
                ->assertSee('id="public-menu"', false)
                ->assertDontSee('id="public-navigation-position"', false)
                ->assertDontSee('resmap-menu-publico')
                ->assertSee(route('catalogo.index'))
                ->assertSee(route('servicios.index'))
                ->assertSee('href="'.route('catalogo.index').'#seleccion-cotizacion" data-open-carrito', false)
                ->assertSee(route('login'));

            $this->assertMatchesRegularExpression(
                '/<a href="'.preg_quote(route($page), '/').'"[^>]*aria-current="page"/',
                $response->getContent(),
            );
        }
    }

    public function test_request_is_rejected_when_requested_quantity_exceeds_available_stock(): void
    {
        $productId = $this->createProductWithStock(3);

        $response = $this->from('/catalogo')->post(route('catalogo.solicitudes.store'), [
            ...$this->validCustomerData(),
            'detalles_productos' => [
                ['id_producto' => $productId, 'cantidad' => 4],
            ],
        ]);

        $response->assertRedirect('/catalogo')
            ->assertSessionHasErrors([
                'detalles_productos' => 'Stock insuficiente para Producto de prueba: solicitaste 4 y quedan 3.',
            ]);
        $this->assertDatabaseCount('solicitud_webs', 0);
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_catalog_form_preserves_customer_data_after_validation_error(): void
    {
        $productId = $this->createProductWithStock(3);
        $datos = [
            ...$this->validCustomerData(),
            'tipo_cliente' => 'Empresa',
            'giro' => 'Venta de repuestos',
            'cargo' => 'Compras',
            'correo' => 'correo-invalido',
            'prefijo_telefono' => '+56',
            'telefono' => '912345678',
            'direccion' => 'Calle 123',
            'region' => 'Metropolitana de Santiago',
            'comuna' => 'Santiago',
            'detalles_productos' => [['id_producto' => $productId, 'cantidad' => 1]],
        ];

        $response = $this->from(route('catalogo.index'))
            ->post(route('catalogo.solicitudes.store'), $datos)
            ->assertRedirect(route('catalogo.index'))
            ->assertSessionHasErrors('correo');

        foreach ($datos as $campo => $valor) {
            $response->assertSessionHasInput($campo, $valor);
        }

        $this->get(route('catalogo.index'))
            ->assertOk()
            ->assertSee('value="Empresa" checked', false)
            ->assertDontSee('value="Persona" checked', false)
            ->assertSee('name="rut" value="12345678-5"', false)
            ->assertSee('name="nombre" value="Cliente de prueba"', false)
            ->assertSee('name="giro" value="Venta de repuestos"', false)
            ->assertSee('name="contacto" value="Contacto de prueba"', false)
            ->assertSee('name="cargo" value="Compras"', false)
            ->assertSee('name="correo" type="email" value="correo-invalido"', false)
            ->assertSee('value="912345678" required', false)
            ->assertSee('name="direccion" value="Calle 123"', false)
            ->assertSee('data-region-initial="Metropolitana de Santiago"', false)
            ->assertSee('data-comuna-initial="Santiago"', false);
    }

    public function test_catalog_defaults_to_person_for_the_customer_type(): void
    {
        $this->get(route('catalogo.index'))
            ->assertOk()
            ->assertSee('value="Persona" required checked', false)
            ->assertDontSee('value="Empresa" checked', false);
    }

    public function test_duplicate_product_lines_are_combined_before_checking_stock(): void
    {
        $productId = $this->createProductWithStock(3);

        $this->from('/catalogo')->post(route('catalogo.solicitudes.store'), [
            ...$this->validCustomerData(),
            'detalles_productos' => [
                ['id_producto' => $productId, 'cantidad' => 2],
                ['id_producto' => $productId, 'cantidad' => 2],
            ],
        ])->assertRedirect('/catalogo')
            ->assertSessionHasErrors([
                'detalles_productos' => 'Stock insuficiente para Producto de prueba: solicitaste 4 y quedan 3.',
            ]);

        $this->assertDatabaseCount('solicitud_webs', 0);
    }

    public function test_request_is_accepted_when_requested_quantity_matches_available_stock(): void
    {
        $productId = $this->createProductWithStock(3);

        $this->post(route('catalogo.solicitudes.store'), [
            ...$this->validCustomerData(),
            'detalles_productos' => [
                ['id_producto' => $productId, 'cantidad' => 3],
            ],
        ])->assertRedirect(route('catalogo.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('solicitud_webs', 1);
        $this->assertDatabaseHas('clientes', [
            'telefono' => '+56912345678',
            'telefono_whatsapp' => '+56912345678',
            'consentimiento_whatsapp_origen' => 'catalogo',
        ]);
    }

    public function test_catalog_rejects_non_chilean_or_duplicated_phone_prefixes(): void
    {
        $productId = $this->createProductWithStock(3);
        $datos = [
            ...$this->validCustomerData(),
            'detalles_productos' => [['id_producto' => $productId, 'cantidad' => 1]],
        ];

        $this->post(route('catalogo.solicitudes.store'), [...$datos, 'prefijo_telefono' => '+54'])
            ->assertSessionHasErrors('prefijo_telefono');
        $this->post(route('catalogo.solicitudes.store'), [
            ...$datos, 'prefijo_telefono' => '+56', 'telefono' => '56912345678',
        ])->assertSessionHasErrors('telefono');
        $this->assertDatabaseCount('solicitud_webs', 0);
    }

    public function test_catalog_request_success_shows_a_dedicated_confirmation_without_a_duplicate_notification(): void
    {
        $this->withSession(['success' => 'Recibimos tu solicitud. Te enviaremos una cotización que indicará su fecha límite de validez.'])
            ->get(route('catalogo.index'))
            ->assertOk()
            ->assertSee('¡Tu solicitud ha sido enviada con éxito!')
            ->assertSee('Te enviaremos una cotización que indicará su fecha límite de validez.')
            ->assertSee('border-green-300 bg-green-50', false)
            ->assertSee('Cerrar confirmación')
            ->assertDontSee('<p class="text-base font-black">Operación completada</p>', false);

        $this->withSession(['success' => 'Operación de prueba'])
            ->get(route('servicios.index'))
            ->assertOk()
            ->assertSee('Operación completada')
            ->assertSee('Operación de prueba');
    }

    public function test_system_notifications_distinguish_success_and_rejection(): void
    {
        $this->withSession([
            'success' => 'Acción aprobada.',
            'rejected' => 'Acción rechazada.',
            'error' => 'No se pudo completar.',
        ])->get(route('servicios.index'))
            ->assertOk()
            ->assertSee('border-green-600 bg-green-50', false)
            ->assertSee('Acción aprobada.')
            ->assertSee('Cotización rechazada')
            ->assertSee('border-red-600 bg-red-50', false)
            ->assertSee('Acción rechazada.')
            ->assertSee('data-auto-dismiss', false);
    }

    public function test_legacy_alternate_number_is_ignored_in_favour_of_contact_number(): void
    {
        $productId = $this->createProductWithStock(3);

        $this->post(route('catalogo.solicitudes.store'), [
            ...$this->validCustomerData(),
            'preferencia_cotizacion' => 'correo_whatsapp',
            'destino_whatsapp' => 'otro_numero',
            'prefijo_telefono_whatsapp' => '+54',
            'telefono_whatsapp_otro' => '1123456789',
            'detalles_productos' => [
                ['id_producto' => $productId, 'cantidad' => 1],
            ],
        ])->assertRedirect(route('catalogo.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('clientes', [
            'telefono' => '+56912345678',
            'telefono_whatsapp' => '+56912345678',
            'consentimiento_whatsapp_origen' => 'catalogo',
        ]);
        $this->assertDatabaseMissing('clientes', [
            'consentimiento_whatsapp_at' => null,
        ]);
    }

    public function test_request_can_send_whatsapp_to_the_same_number(): void
    {
        $productId = $this->createProductWithStock(3);

        $this->post(route('catalogo.solicitudes.store'), [
            ...$this->validCustomerData(),
            'preferencia_cotizacion' => 'correo_whatsapp',
            'destino_whatsapp' => 'mismo_numero',
            'detalles_productos' => [
                ['id_producto' => $productId, 'cantidad' => 1],
            ],
        ])->assertRedirect(route('catalogo.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('clientes', [
            'telefono' => '+56912345678',
            'telefono_whatsapp' => '+56912345678',
            'consentimiento_whatsapp_origen' => 'catalogo',
        ]);
    }

    public function test_new_request_does_not_override_whatsapp_opt_out(): void
    {
        $productId = $this->createProductWithStock(3);
        $datos = [
            ...$this->validCustomerData(),
            'detalles_productos' => [
                ['id_producto' => $productId, 'cantidad' => 1],
            ],
        ];
        $this->post(route('catalogo.solicitudes.store'), $datos)->assertSessionHas('success');
        DB::table('clientes')->update([
            'consentimiento_whatsapp_at' => null,
            'consentimiento_whatsapp_origen' => 'whatsapp_opt_out',
        ]);

        $this->post(route('catalogo.solicitudes.store'), $datos)->assertSessionHas('success');
        $this->assertDatabaseHas('clientes', [
            'telefono_whatsapp' => '+56912345678',
            'consentimiento_whatsapp_at' => null,
            'consentimiento_whatsapp_origen' => 'whatsapp_opt_out',
        ]);
    }

    public function test_stale_alternate_number_is_ignored_when_same_number_is_selected(): void
    {
        $productId = $this->createProductWithStock(3);

        $this->post(route('catalogo.solicitudes.store'), [
            ...$this->validCustomerData(),
            'preferencia_cotizacion' => 'correo_whatsapp',
            'destino_whatsapp' => 'mismo_numero',
            'telefono_whatsapp_otro' => '123',
            'detalles_productos' => [
                ['id_producto' => $productId, 'cantidad' => 1],
            ],
        ])->assertRedirect(route('catalogo.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('clientes', [
            'telefono_whatsapp' => '+56912345678',
        ]);
    }

    public function test_catalog_shows_clear_filters_button_when_filters_are_active(): void
    {
        $this->get(route('catalogo.index'))
            ->assertOk()
            ->assertDontSee('Limpiar filtros');

        $this->get(route('catalogo.index', ['nombre' => 'repuesto']))
            ->assertOk()
            ->assertSee('Limpiar filtros')
            ->assertSee(route('catalogo.index'), false);
    }

    public function test_catalog_paginates_eight_filtered_products_per_page(): void
    {
        for ($number = 1; $number <= 9; $number++) {
            $productId = $this->createProductWithStock(3);
            DB::table('productos')->where('id_producto', $productId)->update([
                'nombre' => "Repuesto pagina {$number}",
            ]);
        }

        $firstPage = $this->get(route('catalogo.index', ['nombre' => 'Repuesto pagina']))
            ->assertOk()
            ->viewData('productos');

        $this->assertSame(
            ['Repuesto pagina 1', 'Repuesto pagina 2', 'Repuesto pagina 3', 'Repuesto pagina 4', 'Repuesto pagina 5', 'Repuesto pagina 6', 'Repuesto pagina 7', 'Repuesto pagina 8'],
            $firstPage->pluck('nombre')->all(),
        );
        $this->assertStringContainsString('nombre=Repuesto%20pagina', $firstPage->nextPageUrl());

        $secondPage = $this->get($firstPage->nextPageUrl())
            ->assertOk()
            ->viewData('productos');

        $this->assertSame(['Repuesto pagina 9'], $secondPage->pluck('nombre')->all());
    }

    public function test_inactive_product_is_hidden_and_cannot_be_requested(): void
    {
        $productId = $this->createProductWithStock(3);
        DB::table('productos')->where('id_producto', $productId)->update(['activo' => false]);

        $this->get(route('catalogo.index'))
            ->assertOk()
            ->assertDontSee('Producto de prueba');

        $this->from('/catalogo')->post(route('catalogo.solicitudes.store'), [
            ...$this->validCustomerData(),
            'detalles_productos' => [
                ['id_producto' => $productId, 'cantidad' => 1],
            ],
        ])->assertRedirect('/catalogo')
            ->assertSessionHasErrors([
                'detalles_productos' => 'No se pueden solicitar productos inactivos: Producto de prueba.',
            ]);

        $this->assertDatabaseCount('solicitud_webs', 0);
    }

    public function test_products_without_available_stock_are_visible_but_cannot_be_requested(): void
    {
        $availableProductId = $this->createProductWithStock(3);
        $unavailableProductId = $this->createProductWithStock(0);
        DB::table('productos')->where('id_producto', $availableProductId)->update([
            'nombre' => 'Producto disponible',
        ]);
        DB::table('productos')->where('id_producto', $unavailableProductId)->update([
            'nombre' => 'Producto agotado',
        ]);

        $response = $this->get(route('catalogo.index'));

        $response->assertOk()
            ->assertSee('Producto disponible')
            ->assertSee('Producto agotado')
            ->assertSee('Agotado')
            ->assertSee('bg-black/40', false);
        $this->assertMatchesRegularExpression(
            '/data-producto-id="'.$unavailableProductId.'"[^>]*disabled/s',
            $response->getContent(),
        );
        $this->assertSame(
            [$unavailableProductId, $availableProductId],
            $response->viewData('productosBuscador')->pluck('id_producto')->all()
        );
        $this->post(route('catalogo.solicitudes.store'), [
            ...$this->validCustomerData(),
            'detalles_productos' => [['id_producto' => $unavailableProductId, 'cantidad' => 1]],
        ])->assertSessionHasErrors('detalles_productos');
        $this->assertDatabaseCount('solicitud_webs', 0);

        DB::table('productos')->where('id_producto', $unavailableProductId)->update(['stock' => 2]);
        $restored = $this->get(route('catalogo.index', ['nombre' => 'Producto agotado']))->assertOk()
            ->assertSee('Producto agotado')
            ->assertDontSee('bg-black/40', false);
        $this->assertSame(2, $restored->viewData('productos')->first()->stock_disponible);
        $this->assertDoesNotMatchRegularExpression(
            '/data-producto-id="'.$unavailableProductId.'"[^>]*\sdisabled(?:\s|>)/s',
            $restored->getContent(),
        );
        $this->post(route('catalogo.solicitudes.store'), [
            ...$this->validCustomerData(),
            'detalles_productos' => [['id_producto' => $unavailableProductId, 'cantidad' => 1]],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    public function test_home_and_catalog_only_offer_kits_with_available_stock_after_reservations(): void
    {
        $availableId = $this->createProductWithStock(3);
        $reservedId = $this->createProductWithStock(1);
        $emptyId = $this->createProductWithStock(0);
        $categoryId = DB::table('categorias')->where('nombre_categoria', 'KITS Y SETS')->value('id_categoria');

        foreach ([
            $availableId => 'Kit disponible',
            $reservedId => 'Kit reservado',
            $emptyId => 'Kit agotado',
        ] as $id => $nombre) {
            DB::table('productos')->where('id_producto', $id)->update([
                'id_categoria' => $categoryId,
                'nombre' => $nombre,
            ]);
        }

        foreach ([$availableId, $reservedId] as $id) {
            $this->post(route('catalogo.solicitudes.store'), [
                ...$this->validCustomerData(),
                'detalles_productos' => [['id_producto' => $id, 'cantidad' => 1]],
            ])->assertSessionHasNoErrors();
        }

        $home = $this->get(route('home'))->assertOk()
            ->assertSee('Servicio técnico de confianza')
            ->assertSee('Ver todos los kits y sets')
            ->assertSee('Kit disponible')
            ->assertDontSee('Kit reservado')
            ->assertDontSee('Kit agotado');
        $this->assertSame([$availableId], $home->viewData('kitsYSets')->pluck('id_producto')->all());
        $this->assertLessThanOrEqual(3, $home->viewData('kitsYSets')->count());

        $catalog = $this->get(route('catalogo.index'))->assertOk()
            ->assertSee('Kit disponible')
            ->assertSee('Kit reservado')
            ->assertSee('Kit agotado')
            ->assertSee('Agotado');
        $product = $catalog->viewData('productosBuscador')->firstWhere('id_producto', $availableId);
        $this->assertSame(2, $product->stock_disponible);
        $this->assertMatchesRegularExpression(
            '/\["Kit disponible","[^"]+",'.$categoryId.','.$availableId.',2\]/',
            $catalog->getContent(),
        );
        $this->post(route('catalogo.solicitudes.store'), [
            ...$this->validCustomerData(),
            'detalles_productos' => [['id_producto' => $reservedId, 'cantidad' => 1]],
        ])->assertSessionHasErrors('detalles_productos');
    }

    public function test_home_escapes_product_names_before_client_side_rendering(): void
    {
        $productId = $this->createProductWithStock(1);
        $maliciousName = 'Kit <img src=x onerror=alert(1)>';
        DB::table('productos')->where('id_producto', $productId)->update([
            'nombre' => $maliciousName,
            'id_categoria' => DB::table('categorias')->where('nombre_categoria', 'KITS Y SETS')->value('id_categoria'),
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee($maliciousName, false)
            ->assertSee('escaparHTML(item.nombre)', false);
    }

    public function test_home_quotation_panel_includes_current_stock_for_catalog_products(): void
    {
        $availableId = $this->createProductWithStock(3);
        $inactiveId = $this->createProductWithStock(2);
        DB::table('productos')->where('id_producto', $inactiveId)->update(['activo' => false]);

        $this->post(route('catalogo.solicitudes.store'), [
            ...$this->validCustomerData(),
            'detalles_productos' => [['id_producto' => $availableId, 'cantidad' => 1]],
        ])->assertSessionHasNoErrors();

        $home = $this->get(route('home'))->assertOk()
            ->assertSee('id="carrito-modal"', false)
            ->assertSee('id="solicitud-modal"', false)
            ->assertSee('onclick="abrirCarrito()"', false)
            ->assertSee('id="carrito-total-precio"', false);

        $this->assertFalse($home->viewData('productosStock')->contains('id_producto', $inactiveId));
        $this->assertMatchesRegularExpression(
            '/\['.$availableId.',2,"?1000(?:\.00)?"?,"unidad"\]/',
            $home->getContent(),
        );

        $catalog = $this->get(route('catalogo.index'))->assertOk();
        $this->assertMatchesRegularExpression(
            '/\['.$availableId.',2,"?1000(?:\.00)?"?,"unidad"\]/',
            $catalog->getContent(),
        );
    }

    public function test_home_reopens_quotation_form_with_validation_errors(): void
    {
        $productId = $this->createProductWithStock(3);

        $this->from(route('home'))->post(route('catalogo.solicitudes.store'), [
            ...$this->validCustomerData(),
            'correo' => 'correo-invalido',
            'detalles_productos' => [['id_producto' => $productId, 'cantidad' => 1]],
        ])->assertRedirect(route('home'))->assertSessionHasErrors('correo');

        $this->get(route('home'))->assertOk()
            ->assertSee('value="correo-invalido"', false)
            ->assertSee("document.getElementById('solicitud-modal').classList.remove('hidden')", false);
    }

    public function test_quotation_requires_products_on_home_and_catalog(): void
    {
        foreach (['home', 'catalogo.index'] as $page) {
            $this->get(route($page))->assertOk()
                ->assertSee('id="continuar-cotizacion" type="button" onclick="abrirSolicitud()" disabled', false)
                ->assertSee('Agrega al menos un producto para continuar con la cotización.');
        }

        $this->post(route('catalogo.solicitudes.store'), [
            ...$this->validCustomerData(),
            'detalles_productos' => [],
        ])->assertSessionHasErrors('detalles_productos');

        $this->assertDatabaseCount('solicitud_webs', 0);
    }

    public function test_zero_stock_products_are_critical_and_visible_in_the_catalog(): void
    {
        $unavailableProductId = $this->createProductWithStock(0);
        $availableProductId = $this->createProductWithStock(1);
        DB::table('productos')->where('id_producto', $unavailableProductId)->update([
            'nombre' => 'Producto agotado',
        ]);
        DB::table('productos')->where('id_producto', $availableProductId)->update([
            'nombre' => 'Producto con stock bajo',
        ]);

        $inventario = $this->actingAs(User::factory()->create(['rol' => 'Administrador']))
            ->get(route('productos.index', ['stock' => 'critico']));
        $inventario->assertOk();
        $productosCriticos = $inventario->viewData('productos')->pluck('id_producto')->all();
        sort($productosCriticos);
        $idsEsperados = [$unavailableProductId, $availableProductId];
        sort($idsEsperados);
        $this->assertSame($idsEsperados, $productosCriticos);

        $catalogo = $this->get(route('catalogo.index'));
        $catalogo->assertOk()
            ->assertSee('Producto con stock bajo')
            ->assertSee('Producto agotado');
    }

    public function test_new_products_default_to_unit_and_active(): void
    {
        $productId = $this->createProductWithStock(3);

        $this->assertDatabaseHas('productos', [
            'id_producto' => $productId,
            'unidad' => 'unidad',
            'activo' => true,
        ]);
    }

    public function test_administrator_can_set_product_unit_and_catalog_visibility(): void
    {
        $productId = $this->createProductWithStock(3);
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $product = DB::table('productos')->where('id_producto', $productId)->first();

        $this->actingAs($admin)
            ->get(route('productos.edit', $productId))
            ->assertOk()
            ->assertSee('Motivo de desactivación')
            ->assertSee('3 unidades');

        $this->put(route('productos.update', $productId), [
            'codigo_barra' => $product->codigo_barra,
            'nombre' => $product->nombre,
            'precio' => $product->precio,
            'stock' => $product->stock,
            'stock_critico' => $product->stock_critico,
            'id_categoria' => $product->id_categoria,
            'unidad' => 'metro',
            'activo' => '0',
            'motivo_desactivacion' => 'Repuesto fuera de línea',
        ])->assertRedirect(route('productos.index'))
            ->assertSessionHas('warning', 'El producto fue desactivado por: Repuesto fuera de línea. Se conservaron 3 unidades en inventario y no estará disponible en el catálogo.');

        $this->assertDatabaseHas('productos', [
            'id_producto' => $productId,
            'stock' => 3,
            'unidad' => 'metro',
            'activo' => false,
        ]);
        $registro = Auditoria::query()->where('accion', 'producto_desactivado')->firstOrFail();
        $this->assertSame('Repuesto fuera de línea', $registro->datos_nuevos['motivo']);
        $this->assertSame(3, $registro->datos_nuevos['stock_conservado']);

        $this->get(route('productos.index'))
            ->assertOk()
            ->assertSee('Motivo: Repuesto fuera de línea');

        $this->get(route('catalogo.index'))
            ->assertOk()
            ->assertDontSee($product->nombre);
    }

    public function test_deactivating_product_requires_a_reason_and_preserves_stock(): void
    {
        $productId = $this->createProductWithStock(3);
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $product = DB::table('productos')->where('id_producto', $productId)->first();

        $this->actingAs($admin)->put(route('productos.update', $productId), [
            'codigo_barra' => $product->codigo_barra,
            'nombre' => $product->nombre,
            'precio' => $product->precio,
            'stock' => $product->stock,
            'stock_critico' => $product->stock_critico,
            'id_categoria' => $product->id_categoria,
            'unidad' => $product->unidad,
            'activo' => '0',
        ])->assertSessionHasErrors('motivo_desactivacion');

        $this->assertDatabaseHas('productos', [
            'id_producto' => $productId,
            'stock' => 3,
            'activo' => true,
        ]);
        $this->assertDatabaseMissing('auditorias', ['accion' => 'producto_desactivado']);
    }

    private function createProductWithStock(int $stock): int
    {
        $categoryId = DB::table('categorias')->insertGetId([
            'nombre_categoria' => 'Repuestos',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('productos')->insertGetId([
            'codigo_barra' => 'TEST-'.uniqid(),
            'nombre' => DB::table('productos')->where('nombre', 'Producto de prueba')->exists()
                ? 'Producto de prueba '.uniqid()
                : 'Producto de prueba',
            'precio' => 1000,
            'stock' => $stock,
            'stock_critico' => 1,
            'id_categoria' => $categoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function validCustomerData(): array
    {
        return [
            'rut' => '12345678-5',
            'nombre' => 'Cliente de prueba',
            'contacto' => 'Contacto de prueba',
            'correo' => 'cliente@example.com',
            'prefijo_telefono' => '+569',
            'telefono' => '12345678',
            'preferencia_cotizacion' => 'solo_correo',
            'region' => 'Metropolitana',
            'comuna' => 'Santiago',
        ];
    }
}
