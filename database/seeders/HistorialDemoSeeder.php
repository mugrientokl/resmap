<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HistorialDemoSeeder extends Seeder
{
    private const MESES = 24;

    private const VENTAS_MINIMAS_MES = 45;

    private const VENTAS_MAXIMAS_MES = 55;

    private Carbon $ahora;

    private Carbon $inicio;

    /** @var array<int, int> */
    private array $usuarios = [];

    /** @var array<int, array{id: int, empresa: bool, nombre: string, rut: string, creado: Carbon}> */
    private array $clientes = [];

    /** @var array<int, object> */
    private array $productos = [];

    /** @var array<int, object> */
    private array $todosLosProductos = [];

    /** @var array<int, object> */
    private array $proveedores = [];

    /** @var array<string, bool> */
    private array $ruts = [];

    /** @var array<int, int> */
    private array $stockHistorico = [];

    /** @var array<int, float> */
    private array $ultimosCostos = [];

    private int $contadorOc = 2000;

    private int $contadorDocumento = 20000;

    private int $contadorClave = 0;

    /** @var array<string, int> */
    private array $resumen = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Este seeder demo no puede ejecutarse en producción.');
        }

        mt_srand(20261122);
        $this->ahora = now()->subMinute();
        $this->inicio = $this->ahora->copy()->subMonthsNoOverflow(self::MESES - 1)->startOfMonth();
        $this->usuarios = DB::table('users')
            ->whereIn('rol', ['Administrador', 'Vendedor'])
            ->pluck('id')
            ->all();
        $this->todosLosProductos = DB::table('productos')
            ->orderBy('id_producto')
            ->get([
                'id_producto',
                'nombre',
                'codigo_barra',
                'precio',
                'stock',
                'stock_critico',
                'activo',
            ])
            ->all();
        $this->productos = array_values(array_filter(
            $this->todosLosProductos,
            fn (object $producto): bool => (float) $producto->precio > 0 && (bool) $producto->activo
        ));
        $this->proveedores = DB::table('proveedores')->orderBy('id')->get()->all();

        if ($this->usuarios === []) {
            throw new \RuntimeException('Se requiere al menos un usuario Administrador o Vendedor.');
        }
        if (count($this->productos) < 10) {
            throw new \RuntimeException('Se requieren al menos 10 productos activos con precio para generar la historia.');
        }
        if ($this->proveedores === []) {
            throw new \RuntimeException('Se requiere al menos un proveedor para registrar adquisiciones.');
        }

        shuffle($this->productos);

        DB::transaction(function (): void {
            $this->limpiarHistoria();
            $this->crearClientes();
            $this->crearVentasMensuales();
            $this->crearServicios();
            $this->ajustarStockFinal();
            $this->validarHistoria();
        });

        foreach ($this->resumen as $clave => $valor) {
            $this->command?->info("{$clave}: {$valor}");
        }
    }

    private function limpiarHistoria(): void
    {
        foreach ([
            'eventos_webhook_whatsapp',
            'mensajes_whatsapp',
            'eventos_reserva',
            'reservas_inventario',
            'eventos_facturacion',
            'ordenes_compra',
            'inventario_movimientos',
            'cotizacion_detalles',
            'cotizacion_versiones',
            'cotizaciones',
            'solicitud_webs',
            'detalle_ventas',
            'ventas',
        ] as $tabla) {
            DB::table($tabla)->delete();
        }

        DB::table('clientes')->where('correo', 'like', '%@demo.resmap.test')->delete();

        $this->resumen['Registros operacionales reemplazados'] = 1;
    }

    private function crearClientes(): void
    {
        foreach (DB::table('clientes')->get(['id_cliente', 'rut', 'nombre', 'razon_social', 'created_at']) as $existente) {
            $this->ruts[(string) $existente->rut] = true;
            $this->clientes[] = [
                'id' => $existente->id_cliente,
                'empresa' => ! empty($existente->razon_social),
                'nombre' => (string) $existente->nombre,
                'rut' => (string) $existente->rut,
                'creado' => Carbon::parse($existente->created_at ?? $this->inicio->copy()->subDay()),
            ];
        }

        $nombres = ['Camila', 'Matías', 'Francisca', 'Sebastián', 'Valentina', 'Cristóbal', 'Javiera', 'Felipe', 'Constanza', 'Nicolás', 'Daniela', 'Ignacio', 'Fernanda', 'Rodrigo', 'Carolina', 'Pablo'];
        $apellidos = ['González', 'Muñoz', 'Rojas', 'Díaz', 'Pérez', 'Soto', 'Contreras', 'Silva', 'Martínez', 'Sepúlveda', 'Morales', 'Rodríguez', 'López', 'Fuentes', 'Vega', 'Araya'];
        $empresas = ['Constructora', 'Transportes', 'Agrícola', 'Ferretería', 'Servicios Industriales', 'Maestranza', 'Forestal', 'Inversiones', 'Comercial', 'Metalúrgica'];
        $sufijos = ['Biobío', 'Andes', 'Del Sur', 'Pacífico', 'Araucanía', 'Costa', 'Lota', 'Nacimiento', 'Centro', 'Huertos'];
        $giros = ['Construcción', 'Transporte de carga', 'Agricultura', 'Venta de herramientas', 'Servicios mecánicos', 'Industria metalmecánica', 'Explotación forestal', 'Comercio al por menor'];
        $zonas = [
            ['Biobío', 'Concepción', 'Concepción'],
            ['Biobío', 'Talcahuano', 'Talcahuano'],
            ['Biobío', 'Hualpén', 'Concepción'],
            ['Biobío', 'San Pedro de la Paz', 'Concepción'],
            ['Biobío', 'Chiguayante', 'Concepción'],
            ['Biobío', 'Coronel', 'Coronel'],
            ['Biobío', 'Los Ángeles', 'Los Ángeles'],
            ['Biobío', 'Lota', 'Lota'],
            ['Ñuble', 'Chillán', 'Chillán'],
            ['Araucanía', 'Temuco', 'Temuco'],
            ['Metropolitana de Santiago', 'Santiago', 'Santiago'],
        ];

        for ($i = 0; $i < 45; $i++) {
            $empresa = $i % 3 === 0;
            $creado = $this->inicio->copy()->subDays(mt_rand(15, 120))->setTime(mt_rand(9, 18), mt_rand(0, 59));
            $zona = $zonas[mt_rand(0, 100) <= 75 ? mt_rand(0, 7) : mt_rand(8, 10)];
            $nombre = $empresa
                ? $empresas[array_rand($empresas)].' '.$sufijos[array_rand($sufijos)].' '.['SpA', 'Ltda.', 'S.A.'][mt_rand(0, 2)]
                : $nombres[array_rand($nombres)].' '.$apellidos[array_rand($apellidos)].' '.$apellidos[array_rand($apellidos)];
            $rut = $this->generarRut($empresa);
            $correo = 'demo'.($i + 1).'@demo.resmap.test';

            $id = DB::table('clientes')->insertGetId([
                'rut' => $rut,
                'nombre' => $nombre,
                'razon_social' => $empresa ? $nombre : null,
                'giro' => $empresa ? $giros[array_rand($giros)] : null,
                'contacto' => $empresa ? $nombres[array_rand($nombres)].' '.$apellidos[array_rand($apellidos)] : $nombre,
                'correo' => $correo,
                'telefono' => '+569'.mt_rand(30000000, 99999999),
                'direccion' => ['Av. Los Carrera', 'Calle O\'Higgins', 'Pasaje Los Aromos', 'Av. Collao', 'Calle Maipú'][mt_rand(0, 4)].' '.mt_rand(100, 3500),
                'region' => $zona[0],
                'comuna' => $zona[1],
                'ciudad' => $zona[2],
                'created_at' => $creado,
                'updated_at' => $creado,
            ]);
            $this->clientes[] = [
                'id' => $id,
                'empresa' => $empresa,
                'nombre' => $nombre,
                'rut' => $rut,
                'creado' => $creado,
            ];
        }

        $this->resumen['Clientes demo nuevos'] = 45;
    }

    private function generarRut(bool $empresa): string
    {
        do {
            $numero = $empresa ? mt_rand(76000000, 99999999) : mt_rand(6000000, 22999999);
            $suma = 0;
            $factor = 2;

            foreach (array_reverse(str_split((string) $numero)) as $digito) {
                $suma += (int) $digito * $factor;
                $factor = $factor === 7 ? 2 : $factor + 1;
            }

            $resto = 11 - ($suma % 11);
            $dv = $resto === 11 ? '0' : ($resto === 10 ? 'K' : (string) $resto);
            $rut = "{$numero}-{$dv}";
        } while (isset($this->ruts[$rut]));

        $this->ruts[$rut] = true;

        return $rut;
    }

    private function crearVentasMensuales(): void
    {
        $ventasTotales = 0;
        $ventasPos = 0;
        $ventasCotizaciones = 0;

        for ($indiceMes = 0; $indiceMes < self::MESES; $indiceMes++) {
            $mes = $this->inicio->copy()->addMonthsNoOverflow($indiceMes);
            $cantidad = self::VENTAS_MINIMAS_MES
                + (($indiceMes * 7 + 4) % (self::VENTAS_MAXIMAS_MES - self::VENTAS_MINIMAS_MES + 1));
            $cantidadPos = intdiv($cantidad, 2);
            $cantidadCotizaciones = $cantidad - $cantidadPos;
            $origenes = array_merge(
                array_fill(0, $cantidadPos, 'pos'),
                array_fill(0, $cantidadCotizaciones, 'cotizacion')
            );
            shuffle($origenes);
            $ultimoDia = $mes->isSameMonth($this->ahora)
                ? max(1, $this->ahora->day - 1)
                : $mes->daysInMonth;

            foreach ($origenes as $indiceVenta => $origen) {
                $dia = 1 + (int) floor(($indiceVenta * max(0, $ultimoDia - 1)) / max(1, $cantidad - 1));
                $fecha = $mes->copy()->day($dia)->setTime(mt_rand(9, 18), mt_rand(0, 59), mt_rand(0, 59));

                if ($fecha->gte($this->ahora)) {
                    $fecha = $this->ahora->copy()->subHours(mt_rand(1, 20));
                }

                if ($origen === 'pos') {
                    $this->crearVentaPos($fecha);
                    $ventasPos++;
                } else {
                    $this->crearVentaCotizada($fecha);
                    $ventasCotizaciones++;
                }

                $ventasTotales++;
            }
        }

        $this->resumen['Ventas completas (24 meses)'] = $ventasTotales;
        $this->resumen['Ventas POS completas'] = $ventasPos;
        $this->resumen['Ventas por cotización completas'] = $ventasCotizaciones;
    }

    private function crearVentaPos(Carbon $fecha): void
    {
        $cliente = mt_rand(1, 100) <= 78 ? $this->clienteEn($fecha) : null;
        $lineas = $this->lineas(mt_rand(1, 4), 3);
        $total = array_sum(array_column($lineas, 'subtotal'));
        $neto = round($total / 1.19, 2);
        $vendedor = $this->usuario();

        $idVenta = DB::table('ventas')->insertGetId([
            'fecha' => $fecha,
            'tipo_documento' => $cliente && $cliente['empresa'] ? 'Factura Electrónica' : 'Boleta Electrónica',
            'folio_sii' => null,
            'neto' => $neto,
            'iva' => round($total - $neto, 2),
            'total' => $total,
            'medio_pago' => ['Efectivo', 'Efectivo', 'Transferencia', 'Tarjeta', 'Tarjeta'][mt_rand(0, 4)],
            'estado_sii' => 'Emitido',
            'user_id' => $vendedor,
            'id_cliente' => $cliente['id'] ?? null,
            'contacto' => $cliente ? $cliente['nombre'] : null,
            'cargo' => $cliente && $cliente['empresa'] ? 'Encargado de compras' : null,
            'datos_cliente' => $cliente ? $this->datosCliente($cliente) : null,
            'estado_venta' => 'confirmada',
            'estado_pago' => 'pagado',
            'estado_facturacion' => 'simulada_exitosa',
            'estado_entrega' => 'entregada',
            'monto_pagado' => $total,
            'stock_descontado_at' => $fecha,
            'confirmada_at' => $fecha,
            'entregada_at' => $fecha,
            'created_at' => $fecha,
            'updated_at' => $fecha,
        ]);

        $this->detallesVenta($idVenta, $lineas, $fecha);
        $this->registrarSalidasHistoricas($idVenta, $lineas, $fecha, $vendedor);
    }

    private function crearVentaCotizada(Carbon $fecha): void
    {
        $cliente = $this->clienteEn($fecha, true) ?? $this->clienteEn($fecha);
        $vendedor = $this->usuario();
        $lineas = $this->lineas(mt_rand(1, 4), 5);
        $totalProductos = array_sum(array_column($lineas, 'subtotal'));
        $neto = round($totalProductos / 1.19, 2);
        $iva = round($totalProductos - $neto, 2);
        $despacho = mt_rand(1, 100) <= 38 ? [3990, 5990, 7990][mt_rand(0, 2)] : 0;
        $total = $totalProductos + $despacho;
        $solicitada = $fecha->copy()->subDays(4)->setTime(mt_rand(9, 17), mt_rand(0, 59));
        $revisada = $solicitada->copy()->addHours(mt_rand(2, 12));
        $aceptada = $fecha->copy()->subDay()->setTime(mt_rand(9, 18), mt_rand(0, 59));
        $zona = DB::table('clientes')->where('id_cliente', $cliente['id'])->first([
            'region',
            'comuna',
            'ciudad',
        ]);
        $datosCliente = $this->datosCliente($cliente);

        $idSolicitud = DB::table('solicitud_webs')->insertGetId([
            'fecha' => $solicitada,
            'estado' => 'Entregado y completado',
            'tipo_solicitud' => 'pedido',
            'id_cliente' => $cliente['id'],
            'contacto' => $cliente['nombre'],
            'cargo' => 'Encargado de compras',
            'datos_cliente' => $datosCliente,
            'detalles_productos' => json_encode(array_map(
                fn (array $linea): array => [
                    'id_producto' => $linea['producto']->id_producto,
                    'cantidad' => $linea['cantidad'],
                ],
                $lineas
            )),
            'observaciones' => null,
            'atendida_at' => $fecha,
            'metodo_entrega' => $despacho > 0 ? 'despacho' : 'retiro',
            'direccion_calle' => $despacho > 0 ? 'Av. Principal' : null,
            'direccion_numero' => $despacho > 0 ? (string) mt_rand(100, 2500) : null,
            'direccion_comuna' => $despacho > 0 ? $zona->comuna : null,
            'direccion_ciudad' => $despacho > 0 ? $zona->ciudad : null,
            'direccion_region' => $despacho > 0 ? $zona->region : null,
            'created_at' => $solicitada,
            'updated_at' => $fecha,
        ]);
        $idCotizacion = DB::table('cotizaciones')->insertGetId([
            'solicitud_id' => $idSolicitud,
            'creada_por' => $vendedor,
            'estado' => 'aceptada',
            'ultima_version' => 1,
            'revisada_at' => $revisada,
            'comentarios_cliente' => null,
            'created_at' => $solicitada,
            'updated_at' => $aceptada,
        ]);
        $vigente = $revisada->copy()->addDays(7);
        $idVersion = DB::table('cotizacion_versiones')->insertGetId([
            'cotizacion_id' => $idCotizacion,
            'revisada_por' => $vendedor,
            'numero_version' => 1,
            'estado' => 'aceptada',
            'neto' => $neto,
            'iva' => $iva,
            'costo_despacho' => $despacho,
            'total' => $total,
            'metodo_entrega' => $despacho > 0 ? 'despacho' : 'retiro',
            'calle' => $despacho > 0 ? 'Av. Principal' : null,
            'numero' => $despacho > 0 ? (string) mt_rand(100, 2500) : null,
            'comuna' => $despacho > 0 ? $zona->comuna : null,
            'ciudad' => $despacho > 0 ? $zona->ciudad : null,
            'region' => $despacho > 0 ? $zona->region : null,
            'vigente_hasta' => $vigente,
            'token_cliente_hash' => null,
            'token_expira_at' => null,
            'enviada_at' => $revisada,
            'email_estado' => 'aceptado_por_smtp',
            'whatsapp_estado' => 'sin_telefono',
            'aceptada_at' => $aceptada,
            'telefono_aceptacion' => '+569'.mt_rand(30000000, 99999999),
            'ip_aceptacion' => '190.'.mt_rand(10, 250).'.'.mt_rand(1, 250).'.'.mt_rand(1, 250),
            'datos_cliente' => $datosCliente,
            'created_at' => $revisada,
            'updated_at' => $aceptada,
        ]);

        foreach ($lineas as $linea) {
            DB::table('cotizacion_detalles')->insert([
                'version_id' => $idVersion,
                'producto_id' => $linea['producto']->id_producto,
                'nombre_producto' => $linea['producto']->nombre,
                'codigo_producto' => $linea['producto']->codigo_barra,
                'cantidad' => $linea['cantidad'],
                'precio_unitario' => $linea['producto']->precio,
                'subtotal' => $linea['subtotal'],
                'created_at' => $revisada,
                'updated_at' => $revisada,
            ]);

            $idReserva = DB::table('reservas_inventario')->insertGetId([
                'cotizacion_id' => $idCotizacion,
                'version_id' => $idVersion,
                'producto_id' => $linea['producto']->id_producto,
                'cantidad' => $linea['cantidad'],
                'estado' => 'consumida',
                'expira_at' => $vigente,
                'consumida_at' => $fecha,
                'created_at' => $revisada,
                'updated_at' => $fecha,
            ]);
            DB::table('eventos_reserva')->insert([
                'reserva_id' => $idReserva,
                'user_id' => $vendedor,
                'evento' => 'reservada',
                'expiracion_nueva' => $vigente,
                'created_at' => $revisada,
                'updated_at' => $revisada,
            ]);
            DB::table('eventos_reserva')->insert([
                'reserva_id' => $idReserva,
                'user_id' => $vendedor,
                'evento' => 'consumida',
                'expiracion_anterior' => $vigente,
                'created_at' => $fecha,
                'updated_at' => $fecha,
            ]);
        }

        $idVenta = DB::table('ventas')->insertGetId([
            'fecha' => $fecha,
            'tipo_documento' => 'Factura Electrónica',
            'folio_sii' => null,
            'neto' => $neto,
            'iva' => $iva,
            'total' => $total,
            'medio_pago' => ['Transferencia', 'Transferencia', 'Tarjeta'][mt_rand(0, 2)],
            'estado_sii' => 'Emitido',
            'user_id' => $vendedor,
            'id_cliente' => $cliente['id'],
            'contacto' => $cliente['nombre'],
            'cargo' => 'Encargado de compras',
            'datos_cliente' => $datosCliente,
            'cotizacion_version_id' => $idVersion,
            'estado_venta' => 'confirmada',
            'estado_pago' => 'pagado',
            'estado_facturacion' => 'simulada_exitosa',
            'estado_entrega' => 'entregada',
            'monto_pagado' => $total,
            'metodo_entrega' => $despacho > 0 ? 'despacho' : 'retiro',
            'comuna_despacho' => $despacho > 0 ? $zona->comuna : null,
            'ciudad_despacho' => $despacho > 0 ? $zona->ciudad : null,
            'region_despacho' => $despacho > 0 ? $zona->region : null,
            'stock_descontado_at' => $fecha,
            'confirmada_at' => $aceptada,
            'entregada_at' => $fecha,
            'created_at' => $fecha,
            'updated_at' => $fecha,
        ]);

        $this->detallesVenta($idVenta, $lineas, $fecha);
        $this->registrarSalidasHistoricas($idVenta, $lineas, $fecha, $vendedor);
        DB::table('solicitud_webs')->where('id_solicitud', $idSolicitud)->update(['venta_id' => $idVenta]);
        DB::table('ordenes_compra')->insert([
            'version_id' => $idVersion,
            'venta_id' => $idVenta,
            'modalidad' => 'automatica',
            'numero' => 'OC-'.(++$this->contadorOc),
            'autorizada_por' => $vendedor,
            'autorizada_at' => $aceptada,
            'created_at' => $aceptada,
            'updated_at' => $aceptada,
        ]);
        DB::table('eventos_facturacion')->insert([
            'venta_id' => $idVenta,
            'user_id' => $vendedor,
            'proveedor' => 'simulado',
            'resultado' => 'exitoso',
            'clave_idempotencia' => 'historial-'.$idVenta.'-'.(++$this->contadorClave),
            'respuesta' => json_encode(['mensaje' => 'Facturación simulada correctamente.']),
            'created_at' => $fecha,
            'updated_at' => $fecha,
        ]);
    }

    /**
     * @param  array<int, array{producto: object, cantidad: int, subtotal: float}>  $lineas
     */
    private function detallesVenta(int $idVenta, array $lineas, Carbon $fecha): void
    {
        foreach ($lineas as $linea) {
            DB::table('detalle_ventas')->insert([
                'id_venta' => $idVenta,
                'id_producto' => $linea['producto']->id_producto,
                'descripcion' => $linea['producto']->nombre,
                'cantidad' => $linea['cantidad'],
                'precio_unitario' => $linea['producto']->precio,
                'subtotal' => $linea['subtotal'],
                'created_at' => $fecha,
                'updated_at' => $fecha,
            ]);
        }
    }

    /**
     * @param  array<int, array{producto: object, cantidad: int, subtotal: float}>  $lineas
     */
    private function registrarSalidasHistoricas(int $idVenta, array $lineas, Carbon $fecha, int $usuario): void
    {
        foreach ($lineas as $linea) {
            $this->asegurarAbastecimiento($linea['producto'], $linea['cantidad'], $fecha, $usuario);
            $idProducto = (int) $linea['producto']->id_producto;
            $stockAnterior = $this->stockHistorico[$idProducto] ?? 0;
            $stockNuevo = $stockAnterior - $linea['cantidad'];

            if ($stockNuevo < 0) {
                throw new \RuntimeException("El stock histórico del producto {$idProducto} quedó negativo.");
            }

            DB::table('inventario_movimientos')->insert([
                'id_producto' => $idProducto,
                'user_id' => $usuario,
                'tipo' => 'venta',
                'cantidad' => -$linea['cantidad'],
                'stock_anterior' => $stockAnterior,
                'stock_nuevo' => $stockNuevo,
                'motivo' => 'Venta registrada',
                'fecha_evento' => $fecha->toDateString(),
                'id_venta' => $idVenta,
                'created_at' => $fecha,
                'updated_at' => $fecha,
            ]);
            $this->stockHistorico[$idProducto] = $stockNuevo;
        }
    }

    private function asegurarAbastecimiento(object $producto, int $cantidad, Carbon $fecha, int $usuario): void
    {
        $idProducto = (int) $producto->id_producto;
        $stockAnterior = $this->stockHistorico[$idProducto] ?? 0;
        if ($stockAnterior >= $cantidad) {
            return;
        }

        $proveedor = $this->proveedores[mt_rand(0, count($this->proveedores) - 1)];
        $cantidadAdquirida = max(20, $cantidad * 5, $cantidad - $stockAnterior);
        $margenComercial = mt_rand(25, 42);
        $variacionTemporal = 0.95 + ($fecha->year - $this->inicio->year) * 0.015;
        $costoUnitario = round(
            ((float) $producto->precio / 1.19 / (1 + ($margenComercial / 100))) * $variacionTemporal,
            2
        );
        $costoTotal = round($costoUnitario * $cantidadAdquirida, 2);
        $fechaAdquisicion = $fecha->copy()->subDays(mt_rand(2, 12))->startOfDay();
        $stockNuevo = $stockAnterior + $cantidadAdquirida;

        DB::table('inventario_movimientos')->insert([
            'id_producto' => $idProducto,
            'user_id' => $usuario,
            'tipo' => 'adquisicion',
            'cantidad' => $cantidadAdquirida,
            'stock_anterior' => $stockAnterior,
            'stock_nuevo' => $stockNuevo,
            'motivo' => 'Compra / Adquisición',
            'proveedor_id' => $proveedor->id,
            'proveedor' => $proveedor->nombre,
            'contacto_proveedor' => $proveedor->contacto,
            'documento_proveedor' => 'FC-HIST-'.(++$this->contadorDocumento),
            'precio_entrada' => $costoTotal,
            'cantidad_adquirida' => $cantidadAdquirida,
            'costo_unitario_neto' => $costoUnitario,
            'costo_total_neto' => $costoTotal,
            'observaciones' => 'Adquisición histórica para abastecer ventas demostrativas.',
            'fecha_evento' => $fechaAdquisicion->toDateString(),
            'created_at' => $fechaAdquisicion,
            'updated_at' => $fechaAdquisicion,
        ]);
        $this->stockHistorico[$idProducto] = $stockNuevo;
        $this->ultimosCostos[$idProducto] = $costoUnitario;
    }

    /**
     * @return array<int, array{producto: object, cantidad: int, subtotal: float}>
     */
    private function lineas(int $cuantas, int $maxCantidad): array
    {
        $lineas = [];

        while (count($lineas) < $cuantas) {
            $producto = $this->producto();
            $cantidad = mt_rand(1, $maxCantidad);
            $lineas[$producto->id_producto] = [
                'producto' => $producto,
                'cantidad' => $cantidad,
                'subtotal' => round((float) $producto->precio * $cantidad, 2),
            ];
        }

        return array_values($lineas);
    }

    private function producto(): object
    {
        $totalProductos = count($this->productos);
        $limitePopular = min(80, $totalProductos);
        if ($limitePopular === $totalProductos) {
            return $this->productos[mt_rand(0, $totalProductos - 1)];
        }
        $popular = mt_rand(1, 100) <= 65;

        return $popular
            ? $this->productos[mt_rand(0, $limitePopular - 1)]
            : $this->productos[mt_rand($limitePopular, $totalProductos - 1)];
    }

    private function usuario(): int
    {
        return $this->usuarios[array_rand($this->usuarios)];
    }

    private function clienteEn(Carbon $fecha, bool $soloEmpresas = false): array
    {
        $candidatos = array_values(array_filter(
            $this->clientes,
            fn (array $cliente): bool => $cliente['creado']->lte($fecha)
                && (! $soloEmpresas || $cliente['empresa'])
        ));

        return $candidatos[array_rand($candidatos)];
    }

    /** @param array{id: int, empresa: bool, nombre: string, rut: string, creado: Carbon} $cliente */
    private function datosCliente(array $cliente): string
    {
        return json_encode([
            'id_cliente' => $cliente['id'],
            'rut' => $cliente['rut'],
            'nombre' => $cliente['nombre'],
            'empresa' => $cliente['empresa'],
        ], JSON_THROW_ON_ERROR);
    }

    private function ajustarStockFinal(): void
    {
        $idsCriticos = collect($this->todosLosProductos)
            ->pluck('id_producto')
            ->take(3)
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($this->todosLosProductos as $producto) {
            $idProducto = (int) $producto->id_producto;
            $esCritico = in_array($idProducto, $idsCriticos, true);
            $stockObjetivo = $esCritico
                ? min(1, (int) $producto->stock_critico)
                : max((int) $producto->stock + 10, (int) $producto->stock_critico + 1);
            $stockAnteriorHistorico = $this->stockHistorico[$idProducto] ?? 0;
            $cantidad = $stockObjetivo - $stockAnteriorHistorico;

            DB::table('productos')->where('id_producto', $idProducto)->update([
                'stock' => $stockObjetivo,
                'costo_promedio_neto' => $this->ultimosCostos[$idProducto] ?? DB::raw('costo_promedio_neto'),
                'updated_at' => $this->ahora,
            ]);

            if ($cantidad !== 0) {
                DB::table('inventario_movimientos')->insert([
                    'id_producto' => $idProducto,
                    'user_id' => $this->usuario(),
                    'tipo' => 'ajuste',
                    'cantidad' => $cantidad,
                    'stock_anterior' => $stockAnteriorHistorico,
                    'stock_nuevo' => $stockObjetivo,
                    'motivo' => $esCritico
                        ? 'Ajuste final de stock crítico demo'
                        : 'Ajuste final de stock demo (+10 unidades)',
                    'fecha_evento' => $this->ahora->toDateString(),
                    'created_at' => $this->ahora,
                    'updated_at' => $this->ahora,
                ]);
            }

            $this->stockHistorico[$idProducto] = $stockObjetivo;
        }

        $this->resumen['Productos con stock crítico'] = count($idsCriticos);
        $this->resumen['Productos con stock incrementado'] = count($this->todosLosProductos) - count($idsCriticos);
    }

    private function crearServicios(): void
    {
        $tipos = [
            'Mantención' => 'Mantención preventiva y ajuste general de equipo.',
            'Reparación' => 'Reparación de equipo con falla de encendido y revisión mecánica.',
            'Soldadura' => 'Soldadura estructural y reforzamiento de pieza metálica.',
        ];

        for ($i = 0; $i < 36; $i++) {
            $mes = $this->inicio->copy()->addMonthsNoOverflow($i % self::MESES);
            $fecha = $mes->copy()->day(mt_rand(2, max(2, $mes->daysInMonth - 2)))->setTime(mt_rand(9, 18), mt_rand(0, 59));
            if ($fecha->gte($this->ahora)) {
                $fecha = $this->ahora->copy()->subDays(mt_rand(1, 7))->setTime(mt_rand(9, 18), 0);
            }
            $cliente = $this->clienteEn($fecha);
            $tipo = array_rand($tipos);
            $completado = $i % 6 !== 0;

            DB::table('solicitud_webs')->insert([
                'fecha' => $fecha,
                'estado' => $completado ? 'Entregado y completado' : 'Pendiente',
                'tipo_solicitud' => 'servicio',
                'tipo_servicio' => $tipo,
                'descripcion_servicio' => $tipos[$tipo],
                'id_cliente' => $cliente['id'],
                'contacto' => $cliente['nombre'],
                'cargo' => $cliente['empresa'] ? 'Encargado de compras' : null,
                'datos_cliente' => $this->datosCliente($cliente),
                'detalles_productos' => json_encode([]),
                'observaciones' => null,
                'atendida_at' => $completado ? $fecha->copy()->addDays(mt_rand(1, 5)) : null,
                'created_at' => $fecha,
                'updated_at' => $completado ? $fecha->copy()->addDays(1) : $fecha,
            ]);
        }

        $this->resumen['Solicitudes de servicio'] = 36;
    }

    private function validarHistoria(): void
    {
        for ($indiceMes = 0; $indiceMes < self::MESES; $indiceMes++) {
            $mes = $this->inicio->copy()->addMonthsNoOverflow($indiceMes);
            $consulta = DB::table('ventas')
                ->whereBetween('fecha', [$mes->copy()->startOfMonth(), $mes->copy()->endOfMonth()])
                ->where('estado_venta', 'confirmada')
                ->where(function ($query): void {
                    $query->whereNull('cotizacion_version_id')
                        ->orWhereNotNull('stock_descontado_at');
                });
            $total = (int) (clone $consulta)->count();
            $pos = (int) (clone $consulta)->whereNull('cotizacion_version_id')->count();
            $cotizaciones = (int) (clone $consulta)->whereNotNull('cotizacion_version_id')->count();

            if ($total < self::VENTAS_MINIMAS_MES || $total > self::VENTAS_MAXIMAS_MES) {
                throw new \RuntimeException("El mes {$mes->format('Y-m')} tiene {$total} ventas completas.");
            }
            if (abs($pos - $cotizaciones) > 1) {
                throw new \RuntimeException("Las ventas POS y por cotización no están equilibradas en {$mes->format('Y-m')}.");
            }
        }

        $stockCritico = DB::table('productos')->whereColumn('stock', '<=', 'stock_critico')->count();
        if ($stockCritico !== 3) {
            throw new \RuntimeException("Se esperaban 3 productos en stock crítico y quedaron {$stockCritico}.");
        }

        $detallesSinCosto = DB::table('detalle_ventas as detalle')
            ->join('ventas as venta', 'venta.id_venta', '=', 'detalle.id_venta')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('inventario_movimientos as movimiento')
                    ->whereColumn('movimiento.id_producto', 'detalle.id_producto')
                    ->whereIn('movimiento.tipo', ['ingreso', 'adquisicion'])
                    ->whereNotNull('movimiento.precio_entrada')
                    ->whereNotNull('movimiento.cantidad_adquirida')
                    ->whereColumn('movimiento.fecha_evento', '<', DB::raw('DATE(venta.fecha)'));
            })
            ->count();

        if ($detallesSinCosto !== 0) {
            throw new \RuntimeException("Hay {$detallesSinCosto} detalles de venta sin adquisición previa.");
        }
    }
}
