<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Carga datos de demostración con 14 meses de historia.
 * Los clientes creados usan el correo @demo.resmap.test para poder identificarlos.
 * Las ventas históricas no alteran el stock actual ni generan movimientos.
 */
class HistorialDemoSeeder extends Seeder
{
    private const MESES = 14;

    private Carbon $ahora;

    private Carbon $inicio;

    /** @var array<int, int> */
    private array $usuarios = [];

    /** @var array<int, array{id: int, empresa: bool, creado: Carbon}> */
    private array $clientes = [];

    /** @var array<int, object> */
    private array $productos = [];

    /** @var array<string, bool> */
    private array $ruts = [];

    private int $contadorOc = 1000;

    private int $contadorClave = 0;

    /** @var array<string, int> */
    private array $resumen = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Este seeder de demostración no puede ejecutarse en producción.');
        }

        if (DB::table('clientes')->where('correo', 'like', '%@demo.resmap.test')->exists()) {
            $this->command?->warn('Los datos demo ya existen; no se carga nada.');

            return;
        }

        mt_srand(20261103);
        $this->ahora = now();
        $this->inicio = $this->ahora->copy()->subMonths(self::MESES);
        $this->usuarios = DB::table('users')->whereIn('rol', ['Administrador', 'Vendedor'])->pluck('id')->all();

        DB::transaction(function (): void {
            $this->reponerStock();
            $this->cargarProductos();
            $this->crearClientes();
            $this->crearVentasPos();
            $this->crearPedidosB2B();
            $this->crearServicios();
        });

        foreach ($this->resumen as $clave => $valor) {
            $this->command?->info("{$clave}: {$valor}");
        }
    }

    private function reponerStock(): void
    {
        $productos = DB::table('productos')->where('stock', '<=', 0)->get(['id_producto', 'stock']);

        foreach ($productos as $producto) {
            DB::table('productos')->where('id_producto', $producto->id_producto)->update(['stock' => 10, 'updated_at' => $this->ahora]);
            DB::table('inventario_movimientos')->insert([
                'id_producto' => $producto->id_producto,
                'user_id' => $this->usuario(),
                'tipo' => 'ajuste',
                'cantidad' => 10 - (int) $producto->stock,
                'stock_anterior' => (int) $producto->stock,
                'stock_nuevo' => 10,
                'motivo' => 'Reposición de stock para pruebas',
                'fecha_evento' => $this->ahora->toDateString(),
                'created_at' => $this->ahora,
                'updated_at' => $this->ahora,
            ]);
        }

        $this->resumen['Productos repuestos a stock 10'] = $productos->count();
    }

    private function cargarProductos(): void
    {
        $productos = DB::table('productos')
            ->where('precio', '>', 0)
            ->where('stock', '>=', 8)
            ->get(['id_producto', 'nombre', 'codigo_barra', 'precio'])
            ->all();
        shuffle($productos);
        $this->productos = array_slice($productos, 0, 400);
    }

    private function producto(): object
    {
        $popular = mt_rand(1, 100) <= 60;

        return $popular ? $this->productos[mt_rand(0, 59)] : $this->productos[mt_rand(60, count($this->productos) - 1)];
    }

    private function usuario(): int
    {
        return $this->usuarios[array_rand($this->usuarios)];
    }

    private function crearClientes(): void
    {
        foreach (DB::table('clientes')->get(['id_cliente', 'rut', 'razon_social', 'created_at']) as $existente) {
            $this->ruts[(string) $existente->rut] = true;
            $this->clientes[] = [
                'id' => $existente->id_cliente,
                'empresa' => ! empty($existente->razon_social),
                'creado' => Carbon::parse($existente->created_at ?? $this->inicio),
            ];
        }

        $nombres = ['Camila', 'Matías', 'Francisca', 'Sebastián', 'Valentina', 'Cristóbal', 'Javiera', 'Felipe', 'Constanza', 'Nicolás', 'Daniela', 'Ignacio', 'Fernanda', 'Rodrigo', 'Carolina', 'Pablo'];
        $apellidos = ['González', 'Muñoz', 'Rojas', 'Díaz', 'Pérez', 'Soto', 'Contreras', 'Silva', 'Martínez', 'Sepúlveda', 'Morales', 'Rodríguez', 'López', 'Fuentes', 'Vega', 'Araya'];
        $empresas = ['Constructora', 'Transportes', 'Agrícola', 'Ferretería', 'Servicios Industriales', 'Maestranza', 'Forestal', 'Inversiones', 'Comercial', 'Metalúrgica'];
        $sufijos = ['Biobío', 'Andes', 'Del Sur', 'Pacífico', 'Araucanía', 'Costa', 'Lota', 'Nacimiento', 'Centro', 'Huertos'];
        $giros = ['Construcción', 'Transporte de carga', 'Agricultura', 'Venta de herramientas', 'Servicios mecánicos', 'Industria metalmecánica', 'Explotación forestal', 'Comercio al por menor'];
        $zonas = [
            ['Biobío', 'Concepción', 'Concepción'], ['Biobío', 'Talcahuano', 'Talcahuano'], ['Biobío', 'Hualpén', 'Concepción'],
            ['Biobío', 'San Pedro de la Paz', 'Concepción'], ['Biobío', 'Chiguayante', 'Concepción'], ['Biobío', 'Coronel', 'Coronel'],
            ['Biobío', 'Los Ángeles', 'Los Ángeles'], ['Biobío', 'Lota', 'Lota'], ['Ñuble', 'Chillán', 'Chillán'],
            ['Araucanía', 'Temuco', 'Temuco'], ['Metropolitana de Santiago', 'Santiago', 'Santiago'],
        ];
        $total = 45;

        for ($i = 0; $i < $total; $i++) {
            $empresa = $i % 3 === 0;
            $dias = $i < 12 ? mt_rand(0, 25) : (int) (($i / $total) * ($this->inicio->diffInDays($this->ahora) - 12)) + mt_rand(0, 8);
            $creado = $this->inicio->copy()->addDays($dias)->setTime(mt_rand(9, 18), mt_rand(0, 59));
            $zona = $zonas[mt_rand(0, 100) <= 75 ? mt_rand(0, 7) : mt_rand(8, 10)];
            $nombre = $empresa
                ? $empresas[array_rand($empresas)].' '.$sufijos[array_rand($sufijos)].' '.['SpA', 'Ltda.', 'S.A.'][mt_rand(0, 2)]
                : $nombres[array_rand($nombres)].' '.$apellidos[array_rand($apellidos)].' '.$apellidos[array_rand($apellidos)];
            $slug = 'demo'.($i + 1);

            $id = DB::table('clientes')->insertGetId([
                'rut' => $this->generarRut($empresa),
                'nombre' => $nombre,
                'razon_social' => $empresa ? $nombre : null,
                'giro' => $empresa ? $giros[array_rand($giros)] : null,
                'correo' => "{$slug}@demo.resmap.test",
                'telefono' => '+569'.mt_rand(30000000, 99999999),
                'direccion' => ['Av. Los Carrera', 'Calle O\'Higgins', 'Pasaje Los Aromos', 'Av. Collao', 'Calle Maipú'][mt_rand(0, 4)].' '.mt_rand(100, 3500),
                'region' => $zona[0],
                'comuna' => $zona[1],
                'ciudad' => $zona[2],
                'created_at' => $creado,
                'updated_at' => $creado,
            ]);
            $this->clientes[] = ['id' => $id, 'empresa' => $empresa, 'creado' => $creado];
        }

        $this->resumen['Clientes nuevos'] = $total;
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

    private function clienteEn(Carbon $fecha, bool $soloEmpresas = false): ?array
    {
        $candidatos = array_values(array_filter(
            $this->clientes,
            fn (array $c): bool => $c['creado']->lte($fecha) && (! $soloEmpresas || $c['empresa'])
        ));

        return $candidatos === [] ? null : $candidatos[array_rand($candidatos)];
    }

    private function fechaPonderada(): Carbon
    {
        $dias = $this->inicio->diffInDays($this->ahora);

        do {
            $u = mt_rand() / mt_getrandmax();
            $fecha = $this->inicio->copy()->addDays((int) ($dias * (1 - ($u ** 1.7))))->setTime(mt_rand(9, 19), mt_rand(0, 59), mt_rand(0, 59));
            $mesBajo = in_array($fecha->month, [1, 2, 7], true) && mt_rand(1, 100) <= 30;
        } while ($fecha->isSunday() || $mesBajo || $fecha->gte($this->ahora->copy()->subHour()));

        return $fecha;
    }

    private function crearVentasPos(): void
    {
        $cantidad = 400;

        for ($i = 0; $i < $cantidad; $i++) {
            $fecha = $this->fechaPonderada();
            $cliente = mt_rand(1, 100) <= 80 ? $this->clienteEn($fecha) : null;
            $factura = $cliente && $cliente['empresa'] && mt_rand(1, 100) <= 60;
            $lineas = $this->lineas(mt_rand(1, 4), 3);
            $total = array_sum(array_column($lineas, 'subtotal'));
            $neto = round($total / 1.19, 2);

            $idVenta = DB::table('ventas')->insertGetId([
                'fecha' => $fecha,
                'tipo_documento' => $factura ? 'Factura Electrónica' : 'Boleta Electrónica',
                'folio_sii' => null,
                'neto' => $neto,
                'iva' => round($total - $neto, 2),
                'total' => $total,
                'medio_pago' => ['Efectivo', 'Efectivo', 'Transferencia', 'Tarjeta', 'Tarjeta'][mt_rand(0, 4)],
                'estado_sii' => 'Pendiente',
                'user_id' => $this->usuario(),
                'id_cliente' => $cliente['id'] ?? null,
                'estado_venta' => 'confirmada',
                'estado_pago' => 'registrado',
                'estado_facturacion' => 'pendiente',
                'estado_entrega' => 'entregada',
                'monto_pagado' => $total,
                'stock_descontado_at' => $fecha,
                'created_at' => $fecha,
                'updated_at' => $fecha,
            ]);
            $this->detallesVenta($idVenta, $lineas, $fecha);
        }

        $this->resumen['Ventas POS'] = $cantidad;
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

    private function crearPedidosB2B(): void
    {
        $antiguos = ['entregada', 'entregada', 'entregada', 'entregada', 'entregada', 'entregada', 'rechazada', 'vencida', 'cambios_entregada', 'entregada'];
        $recientesActivos = ['pendiente_revision', 'borrador', 'enviada', 'cambios_solicitados', 'aceptada', 'confirmada_pagada', 'credito_autorizado', 'credito_parcial', 'enviada', 'aceptada'];
        $recientesCerrados = ['facturada', 'entregada', 'rechazada', 'vencida', 'facturada', 'entregada'];

        $total = 0;

        for ($i = 0; $i < 32; $i++) {
            $fecha = $this->inicio->copy()->addDays(mt_rand(2, (int) $this->inicio->diffInDays($this->ahora) - 36))->setTime(mt_rand(9, 18), mt_rand(0, 59));
            $this->crearPedido($fecha, $antiguos[$i % count($antiguos)], false);
            $total++;
        }

        foreach ($recientesCerrados as $escenario) {
            $this->crearPedido($this->ahora->copy()->subDays(mt_rand(8, 34))->setTime(mt_rand(9, 18), mt_rand(0, 59)), $escenario, true);
            $total++;
        }

        foreach ($recientesActivos as $escenario) {
            $this->crearPedido($this->ahora->copy()->subHours(mt_rand(10, 110)), $escenario, true);
            $total++;
        }

        $this->resumen['Pedidos B2B con cotización'] = $total;
    }

    private function limitar(Carbon $fecha): Carbon
    {
        return $fecha->gt($this->ahora) ? $this->ahora->copy()->subMinutes(mt_rand(1, 30)) : $fecha;
    }

    private function crearPedido(Carbon $t0, string $escenario, bool $reciente): void
    {
        $cliente = $this->clienteEn($t0, true) ?? $this->clienteEn($t0);
        $vendedor = $this->usuario();
        $lineas = $this->lineas(mt_rand(1, 3), $reciente ? 2 : 6);
        $bruto = array_sum(array_column($lineas, 'subtotal'));
        $despachar = mt_rand(1, 100) <= 40;
        $despacho = $despachar ? [3990, 5990, 7990][mt_rand(0, 2)] : 0;
        $neto = round($bruto / 1.19, 2);
        $iva = round($bruto - $neto, 2);
        $totalVersion = $bruto + $despacho;
        $zona = DB::table('clientes')->where('id_cliente', $cliente['id'])->first(['region', 'comuna', 'ciudad']);

        $estadoCot = match ($escenario) {
            'pendiente_revision' => 'pendiente_revision',
            'borrador' => 'borrador',
            'enviada' => 'enviada',
            'cambios_solicitados' => 'cambios_solicitados',
            'rechazada' => 'rechazada',
            'vencida' => 'vencida',
            default => 'aceptada',
        };
        $cerrado = in_array($escenario, ['entregada', 'cambios_entregada'], true);

        $idSolicitud = DB::table('solicitud_webs')->insertGetId([
            'fecha' => $t0,
            'estado' => $cerrado ? 'Entregado y completado' : ($escenario === 'rechazada' ? 'Rechazado' : 'Pendiente'),
            'tipo_solicitud' => 'pedido',
            'id_cliente' => $cliente['id'],
            'detalles_productos' => json_encode(array_map(fn (array $l): array => ['id_producto' => $l['producto']->id_producto, 'cantidad' => $l['cantidad']], $lineas)),
            'observaciones' => null,
            'atendida_at' => $cerrado ? $this->limitar($t0->copy()->addDays(mt_rand(5, 9))) : null,
            'metodo_entrega' => $despachar ? 'despacho' : 'retiro',
            'direccion_calle' => $despachar ? 'Av. Principal' : null,
            'direccion_numero' => $despachar ? (string) mt_rand(100, 2500) : null,
            'direccion_comuna' => $despachar ? $zona->comuna : null,
            'direccion_ciudad' => $despachar ? $zona->ciudad : null,
            'direccion_region' => $despachar ? $zona->region : null,
            'created_at' => $t0,
            'updated_at' => $t0,
        ]);

        $idCotizacion = DB::table('cotizaciones')->insertGetId([
            'solicitud_id' => $idSolicitud,
            'creada_por' => $vendedor,
            'estado' => $estadoCot,
            'ultima_version' => $escenario === 'pendiente_revision' ? 0 : ($escenario === 'cambios_entregada' ? 2 : 1),
            'revisada_at' => $escenario === 'pendiente_revision' ? null : $this->limitar($t0->copy()->addHours(3)),
            'comentarios_cliente' => in_array($escenario, ['cambios_solicitados', 'cambios_entregada'], true) ? 'Solicitamos revisar cantidades y plazo de entrega.' : null,
            'created_at' => $t0,
            'updated_at' => $t0,
        ]);

        if ($escenario === 'pendiente_revision') {
            return;
        }

        $versiones = $escenario === 'cambios_entregada' ? 2 : 1;
        $base = $t0->copy()->addHours(3);
        $idVersionFinal = null;
        $versionFinal = null;

        for ($n = 1; $n <= $versiones; $n++) {
            $esFinal = $n === $versiones;
            $creada = $this->limitar($base->copy()->addDays(($n - 1) * 3));
            $enviada = $escenario === 'borrador' ? null : $this->limitar($creada->copy()->addHours(mt_rand(2, 20)));
            $vigente = $creada->copy()->addDays(7);
            $decision = $this->limitar(($enviada ?? $creada)->copy()->addDays(mt_rand(1, 2))->addHours(mt_rand(1, 8)));

            $estadoVersion = ! $esFinal ? 'reemplazada' : match ($escenario) {
                'borrador' => 'borrador',
                'enviada' => 'enviada',
                'cambios_solicitados' => 'cambios_solicitados',
                'rechazada' => 'rechazada',
                'vencida' => 'vencida',
                default => 'aceptada',
            };
            $aceptada = $esFinal && ! in_array($escenario, ['borrador', 'enviada', 'cambios_solicitados', 'rechazada', 'vencida'], true);
            $tokenVigente = $estadoVersion === 'enviada';

            $idVersion = DB::table('cotizacion_versiones')->insertGetId([
                'cotizacion_id' => $idCotizacion,
                'revisada_por' => $vendedor,
                'numero_version' => $n,
                'estado' => $estadoVersion,
                'neto' => $neto,
                'iva' => $iva,
                'costo_despacho' => $despacho,
                'total' => $totalVersion,
                'metodo_entrega' => $despachar ? 'despacho' : 'retiro',
                'calle' => $despachar ? 'Av. Principal' : null,
                'numero' => $despachar ? (string) mt_rand(100, 2500) : null,
                'comuna' => $despachar ? $zona->comuna : null,
                'ciudad' => $despachar ? $zona->ciudad : null,
                'region' => $despachar ? $zona->region : null,
                'vigente_hasta' => $vigente,
                'token_cliente_hash' => $tokenVigente ? hash('sha256', "demo-{$idCotizacion}-{$n}") : null,
                'token_expira_at' => $tokenVigente ? $vigente : null,
                'enviada_at' => $enviada,
                'email_estado' => $enviada ? 'aceptado_por_smtp' : null,
                'whatsapp_estado' => $enviada ? 'sin_telefono' : null,
                'aceptada_at' => $aceptada ? $decision : null,
                'rechazada_at' => $esFinal && $escenario === 'rechazada' ? $decision : null,
                'cambios_solicitados_at' => (! $esFinal || $escenario === 'cambios_solicitados') ? $decision : null,
                'telefono_aceptacion' => $aceptada ? '+569'.mt_rand(30000000, 99999999) : null,
                'ip_aceptacion' => $aceptada ? '190.'.mt_rand(10, 250).'.'.mt_rand(1, 250).'.'.mt_rand(1, 250) : null,
                'created_at' => $creada,
                'updated_at' => $decision,
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
                    'created_at' => $creada,
                    'updated_at' => $creada,
                ]);
            }

            if ($esFinal) {
                $idVersionFinal = $idVersion;
                $versionFinal = ['creada' => $creada, 'aceptada' => $decision, 'vigente' => $vigente];
            }
        }

        $this->crearReservas($idCotizacion, $idVersionFinal, $lineas, $escenario, $versionFinal);

        if (! in_array($escenario, ['aceptada', 'confirmada_pagada', 'credito_autorizado', 'credito_parcial', 'facturada', 'entregada', 'cambios_entregada'], true)) {
            return;
        }

        $this->crearVentaB2B($idSolicitud, $idVersionFinal, $cliente['id'], $vendedor, $lineas, $escenario, [
            'neto' => $neto, 'iva' => $iva, 'total' => $totalVersion,
            'metodo' => $despachar ? 'despacho' : 'retiro', 'zona' => $zona, 'despacha' => $despachar,
        ], $versionFinal['aceptada']);
    }

    /**
     * @param  array<int, array{producto: object, cantidad: int, subtotal: float}>  $lineas
     * @param  array{creada: Carbon, aceptada: Carbon, vigente: Carbon}  $version
     */
    private function crearReservas(int $idCotizacion, int $idVersion, array $lineas, string $escenario, array $version): void
    {
        $estado = match ($escenario) {
            'entregada', 'cambios_entregada', 'facturada' => 'consumida',
            'rechazada' => 'liberada',
            'vencida' => 'vencida',
            default => 'activa',
        };
        $expira = $estado === 'activa' ? $this->ahora->copy()->addDays(mt_rand(2, 6)) : $version['vigente'];

        foreach ($lineas as $linea) {
            $cierre = $this->limitar($version['aceptada']->copy()->addDays(mt_rand(1, 4)));
            $idReserva = DB::table('reservas_inventario')->insertGetId([
                'cotizacion_id' => $idCotizacion,
                'version_id' => $idVersion,
                'producto_id' => $linea['producto']->id_producto,
                'cantidad' => $linea['cantidad'],
                'estado' => $estado,
                'expira_at' => $expira,
                'consumida_at' => $estado === 'consumida' ? $cierre : null,
                'liberada_at' => in_array($estado, ['liberada', 'vencida'], true) ? ($estado === 'vencida' ? $version['vigente'] : $cierre) : null,
                'created_at' => $version['creada'],
                'updated_at' => $version['creada'],
            ]);

            DB::table('eventos_reserva')->insert(['reserva_id' => $idReserva, 'user_id' => null, 'evento' => 'reservada', 'expiracion_nueva' => $expira, 'created_at' => $version['creada'], 'updated_at' => $version['creada']]);

            if ($estado !== 'activa') {
                DB::table('eventos_reserva')->insert([
                    'reserva_id' => $idReserva,
                    'user_id' => null,
                    'evento' => $estado,
                    'expiracion_anterior' => $expira,
                    'created_at' => $estado === 'vencida' ? $version['vigente'] : $cierre,
                    'updated_at' => $estado === 'vencida' ? $version['vigente'] : $cierre,
                ]);
            }
        }
    }

    /**
     * @param  array<int, array{producto: object, cantidad: int, subtotal: float}>  $lineas
     * @param  array<string, mixed>  $datos
     */
    private function crearVentaB2B(int $idSolicitud, int $idVersion, int $idCliente, int $vendedor, array $lineas, string $escenario, array $datos, Carbon $aceptada): void
    {
        $avanzado = ! in_array($escenario, ['aceptada'], true);
        $facturado = in_array($escenario, ['facturada', 'entregada', 'cambios_entregada'], true);
        $entregado = in_array($escenario, ['entregada', 'cambios_entregada'], true);
        $confirmada = $avanzado ? $this->limitar($aceptada->copy()->addDays(1)->addHours(mt_rand(1, 6))) : null;
        $fact = $facturado ? $this->limitar($confirmada->copy()->addDays(1)) : null;
        $entrega = $entregado ? $this->limitar($fact->copy()->addDays(mt_rand(1, 3))) : null;

        $estadoPago = match ($escenario) {
            'aceptada' => 'pendiente',
            'credito_autorizado' => 'credito_autorizado',
            'credito_parcial' => 'credito_parcial',
            default => 'pagado',
        };
        $total = (float) $datos['total'];
        $pagado = match ($estadoPago) {
            'pagado' => $total,
            'credito_parcial' => round($total / 2),
            default => 0,
        };
        $credito = in_array($estadoPago, ['credito_autorizado', 'credito_parcial'], true);
        $zona = $datos['zona'];

        $idVenta = DB::table('ventas')->insertGetId([
            'fecha' => $aceptada,
            'tipo_documento' => 'Factura Electrónica',
            'folio_sii' => null,
            'neto' => $datos['neto'],
            'iva' => $datos['iva'],
            'total' => $total,
            'medio_pago' => $credito ? 'Crédito autorizado' : ($avanzado ? ['Transferencia', 'Transferencia', 'Tarjeta'][mt_rand(0, 2)] : 'Pendiente de confirmación'),
            'estado_sii' => 'Pendiente',
            'user_id' => $vendedor,
            'id_cliente' => $idCliente,
            'cotizacion_version_id' => $idVersion,
            'estado_venta' => $avanzado ? 'confirmada' : 'pendiente_confirmacion',
            'estado_pago' => $estadoPago,
            'estado_facturacion' => $facturado ? 'simulada_exitosa' : 'pendiente',
            'estado_entrega' => $entregado ? 'entregada' : 'pendiente',
            'monto_pagado' => $pagado,
            'metodo_entrega' => $datos['metodo'],
            'comuna_despacho' => $datos['despacha'] ? $zona->comuna : null,
            'ciudad_despacho' => $datos['despacha'] ? $zona->ciudad : null,
            'region_despacho' => $datos['despacha'] ? $zona->region : null,
            'credito_autorizado_por' => $credito ? $vendedor : null,
            'credito_autorizado_at' => $credito ? $confirmada : null,
            'stock_descontado_at' => $fact,
            'confirmada_at' => $confirmada,
            'entregada_at' => $entrega,
            'created_at' => $aceptada,
            'updated_at' => $entrega ?? $fact ?? $confirmada ?? $aceptada,
        ]);

        $this->detallesVenta($idVenta, $lineas, $aceptada);
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

        if ($facturado) {
            DB::table('eventos_facturacion')->insert([
                'venta_id' => $idVenta,
                'user_id' => $vendedor,
                'proveedor' => 'simulado',
                'resultado' => 'exitoso',
                'clave_idempotencia' => 'demo-'.$idVenta.'-'.(++$this->contadorClave),
                'respuesta' => json_encode(['mensaje' => 'Facturación simulada correctamente.']),
                'created_at' => $fact,
                'updated_at' => $fact,
            ]);
        }
    }

    private function crearServicios(): void
    {
        $tipos = ['Mantención', 'Reparación', 'Soldadura'];
        $descripciones = [
            'Mantención' => ['Mantención preventiva de motor 2 tiempos', 'Cambio de aceite y filtros de equipo', 'Revisión general de motobomba'],
            'Reparación' => ['Reparación de desbrozadora que no enciende', 'Reparación de motosierra con pérdida de compresión', 'Cambio de carburador y bujía'],
            'Soldadura' => ['Soldadura de chasis de carro de arrastre', 'Reparación de estructura metálica de portón', 'Soldadura de estanque'],
        ];
        $antiguos = ['Entregado y completado', 'Entregado y completado', 'Entregado y completado', 'Entregado y completado', 'Rechazado', 'Entregado - pago pendiente'];
        $recientes = ['Pendiente', 'Pendiente', 'Pagado - pendiente de entrega', 'Pagado - pendiente de entrega', 'Entregado - pago pendiente', 'Entregado y completado', 'Entregado y completado', 'Rechazado'];
        $total = 30;

        for ($i = 0; $i < $total; $i++) {
            $reciente = $i >= 22;
            $fecha = $reciente
                ? $this->ahora->copy()->subDays(mt_rand(0, 20))->setTime(mt_rand(9, 18), mt_rand(0, 59))
                : $this->inicio->copy()->addDays(mt_rand(3, (int) $this->inicio->diffInDays($this->ahora) - 25))->setTime(mt_rand(9, 18), mt_rand(0, 59));
            $cliente = $this->clienteEn($fecha);
            $tipo = $tipos[array_rand($tipos)];
            $estado = $reciente ? $recientes[$i - 22] : $antiguos[$i % count($antiguos)];
            $atendida = in_array($estado, ['Entregado - pago pendiente', 'Entregado y completado'], true) ? $this->limitar($fecha->copy()->addDays(mt_rand(1, 6))) : null;

            DB::table('solicitud_webs')->insert([
                'fecha' => $fecha,
                'estado' => $estado,
                'tipo_solicitud' => 'servicio',
                'tipo_servicio' => $tipo,
                'descripcion_servicio' => $descripciones[$tipo][array_rand($descripciones[$tipo])],
                'id_cliente' => $cliente['id'] ?? null,
                'detalles_productos' => json_encode([]),
                'observaciones' => $estado === 'Rechazado' ? 'No se puede realizar el servicio solicitado.' : null,
                'atendida_at' => $atendida,
                'created_at' => $fecha,
                'updated_at' => $atendida ?? $fecha,
            ]);
        }

        $this->resumen['Solicitudes de servicio'] = $total;
    }
}
