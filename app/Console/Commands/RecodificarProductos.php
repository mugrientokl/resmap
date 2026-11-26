<?php

namespace App\Console\Commands;

use App\Models\Producto;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

#[Signature('app:recodificar-productos {--aplicar : Ejecuta la conversión después de revisar y respaldar}')]
#[Description('Revisa o convierte los códigos de productos sin modificar documentos históricos')]
class RecodificarProductos extends Command
{
    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        if ($aplicar && ! app()->isDownForMaintenance()) {
            $this->error('Activa el modo mantenimiento y detén procesos que escriban en la base de datos antes de aplicar.');

            return self::FAILURE;
        }

        if ($aplicar && ! Schema::hasTable('producto_recodificaciones')) {
            $this->error('Falta la tabla de correspondencia. Ejecuta únicamente la migración indicada para esta conversión.');

            return self::FAILURE;
        }

        if (Schema::hasTable('producto_recodificaciones') && DB::table('producto_recodificaciones')->exists()) {
            $this->info('La conversión ya fue aplicada. No se modificaron códigos.');

            return self::SUCCESS;
        }

        try {
            $this->verificarEstructura($aplicar);
            $plan = DB::transaction(function () use ($aplicar): array {
                if ($aplicar && DB::connection()->getDriverName() === 'pgsql') {
                    DB::statement('LOCK TABLE productos IN SHARE ROW EXCLUSIVE MODE');
                }
                $consulta = DB::table('productos')->orderBy('id_producto');
                if ($aplicar) {
                    $consulta->lockForUpdate();
                }
                $productos = $consulta->get(['id_producto', 'nombre', 'nombre_normalizado', 'codigo_barra', 'codigo_origen']);
                if ($aplicar && DB::table('producto_recodificaciones')->exists()) {
                    throw new RuntimeException('La conversión ya fue aplicada por otro proceso; no se modificaron códigos.');
                }
                $plan = $this->prepararPlan($productos);
                $this->verificarReferencias($productos);

                if ($aplicar) {
                    $fecha = now();
                    foreach ($plan as $producto) {
                        DB::table('producto_recodificaciones')->insert([
                            'id_producto' => $producto['id'],
                            'codigo_barra_anterior' => $producto['barra'],
                            'codigo_origen_anterior' => $producto['origen'],
                            'codigo_nuevo' => $producto['nuevo'],
                            'recodificado_at' => $fecha,
                        ]);
                        DB::table('productos')->where('id_producto', $producto['id'])->update([
                            'codigo_barra' => 'RECOD-'.Str::uuid(),
                            'codigo_origen' => null,
                        ]);
                    }

                    foreach ($plan as $producto) {
                        DB::table('productos')->where('id_producto', $producto['id'])->update([
                            'codigo_barra' => $producto['nuevo'],
                            'codigo_origen' => $producto['nuevo'],
                        ]);
                    }
                }

                return $plan;
            }, 1);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['ID', 'Código anterior', 'Código origen anterior', 'Código nuevo'],
            array_map(fn (array $producto): array => [$producto['id'], $producto['barra'], $producto['origen'] ?? '', $producto['nuevo']], array_slice($plan, 0, 20))
        );
        $this->info(count($plan).' productos revisados. '.($aplicar
            ? 'Conversión completada; la correspondencia quedó guardada.'
            : 'Simulación sin cambios. Se muestran hasta 20 correspondencias.'));

        return self::SUCCESS;
    }

    private function verificarEstructura(bool $aplicar): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['sqlite', 'mysql', 'mariadb', 'pgsql'], true)) {
            throw new RuntimeException('El motor de base de datos no está soportado para esta conversión.');
        }

        foreach (['productos', 'detalle_ventas', 'inventario_movimientos', 'cotizacion_detalles', 'reservas_inventario', 'solicitud_webs'] as $tabla) {
            if (! Schema::hasTable($tabla)) {
                throw new RuntimeException("Falta la tabla {$tabla}; revisa las migraciones existentes antes de continuar.");
            }
        }

        if (! Schema::hasColumn('productos', 'nombre_normalizado') || ! Schema::hasColumn('productos', 'codigo_origen')) {
            throw new RuntimeException('Faltan columnas de identificadores. No ejecutes migraciones antiguas sin revisar sus efectos sobre los datos.');
        }

        $indices = Schema::getIndexes('productos');
        foreach (['codigo_barra', 'codigo_origen', 'nombre_normalizado'] as $columna) {
            if (! collect($indices)->contains(fn (array $indice): bool => $indice['unique'] && $indice['columns'] === [$columna])) {
                throw new RuntimeException("Falta la restricción UNIQUE de productos.{$columna}.");
            }
        }

        if ($aplicar && in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $tablas = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
                ->whereIn('TABLE_NAME', ['productos', 'producto_recodificaciones'])
                ->get(['TABLE_NAME', 'ENGINE']);
            if ($tablas->count() !== 2 || $tablas->contains(fn ($tabla): bool => strtoupper((string) $tabla->ENGINE) !== 'INNODB')) {
                throw new RuntimeException('La conversión requiere tablas InnoDB para garantizar la transacción.');
            }
        }
    }

    /**
     * @param  Collection<int, object>  $productos
     * @return array<int, array{id: int, barra: string, origen: ?string, nuevo: string}>
     */
    private function prepararPlan(Collection $productos): array
    {
        $codigosAnteriores = [];
        $nombres = [];
        $prefijos = [];
        $asignados = [];
        $maximos = [];
        $plan = [];

        foreach ($productos as $producto) {
            $id = (int) $producto->id_producto;
            $prefijo = Producto::prefijoCodigo($producto->nombre);
            if (strlen($prefijo) !== 3) {
                throw new RuntimeException("El producto #{$id} no tiene tres letras para generar su código. Revisa su nombre antes de continuar.");
            }
            $prefijos[$id] = $prefijo;
            $claveNombre = Producto::claveNombre($producto->nombre);
            if ($producto->nombre_normalizado !== null && $producto->nombre_normalizado !== $claveNombre) {
                throw new RuntimeException("La clave del nombre del producto #{$id} está desactualizada; revisa la migración de normalización antes de continuar.");
            }
            if (isset($nombres[$claveNombre])) {
                $this->warn("Nombres equivalentes en los productos #{$nombres[$claveNombre]} y #{$id}; se conservarán ambos sin modificar.");
            } else {
                $nombres[$claveNombre] = $id;
            }

            foreach ([$producto->codigo_barra, $producto->codigo_origen] as $codigo) {
                if ($codigo === null) {
                    continue;
                }
                $clave = mb_strtolower(trim($codigo), 'UTF-8');
                if ($clave === '') {
                    throw new RuntimeException("El producto #{$id} tiene un código vacío; revisa sus referencias antes de continuar.");
                }
                if (isset($codigosAnteriores[$clave]) && $codigosAnteriores[$clave] !== $id) {
                    throw new RuntimeException("Código anterior ambiguo en los productos #{$codigosAnteriores[$clave]} y #{$id}; resuelve el conflicto antes de continuar.");
                }
                $codigosAnteriores[$clave] = $id;
            }
        }

        foreach ($productos as $producto) {
            $id = (int) $producto->id_producto;
            $prefijo = $prefijos[$id];
            foreach ([$producto->codigo_barra, $producto->codigo_origen] as $codigo) {
                if (! is_string($codigo) || ! preg_match('/^'.$prefijo.'(\d{3})$/D', $codigo, $coincidencias)) {
                    continue;
                }
                $numeroTexto = ltrim($coincidencias[1], '0');
                if (strlen($numeroTexto) > strlen((string) PHP_INT_MAX) || (strlen($numeroTexto) === strlen((string) PHP_INT_MAX) && strcmp($numeroTexto, (string) PHP_INT_MAX) >= 0)) {
                    throw new RuntimeException("El consecutivo del producto #{$id} excede el límite admitido.");
                }
                $numero = (int) $numeroTexto;
                if ($numero < 1 || isset($asignados[$prefijo][$numero])) {
                    continue;
                }
                $plan[$id] = ['id' => $id, 'barra' => $producto->codigo_barra, 'origen' => $producto->codigo_origen, 'nuevo' => $codigo];
                $asignados[$prefijo][$numero] = true;
                $maximos[$prefijo] = max($maximos[$prefijo] ?? 0, $numero);
                break;
            }
        }

        foreach ($productos as $producto) {
            $id = (int) $producto->id_producto;
            if (isset($plan[$id])) {
                continue;
            }
            $prefijo = $prefijos[$id];
            do {
                $numero = ($maximos[$prefijo] ?? 0) + 1;
                if ($numero > 999) {
                    throw new RuntimeException("Se agotaron los códigos disponibles para el prefijo {$prefijo}.");
                }
                $maximos[$prefijo] = $numero;
                $codigo = $prefijo.sprintf('%03d', $numero);
            } while (isset($codigosAnteriores[strtolower($codigo)]) && $codigosAnteriores[strtolower($codigo)] !== $id);
            $plan[$id] = ['id' => $id, 'barra' => $producto->codigo_barra, 'origen' => $producto->codigo_origen, 'nuevo' => $codigo];
        }

        ksort($plan);

        return array_values($plan);
    }

    /** @param Collection<int, object> $productos */
    private function verificarReferencias(Collection $productos): void
    {
        foreach ([
            'detalle_ventas' => 'id_producto',
            'inventario_movimientos' => 'id_producto',
            'cotizacion_detalles' => 'producto_id',
            'reservas_inventario' => 'producto_id',
        ] as $tabla => $columna) {
            $faltantes = DB::table($tabla)
                ->leftJoin('productos', "{$tabla}.{$columna}", '=', 'productos.id_producto')
                ->whereNotNull("{$tabla}.{$columna}")
                ->whereNull('productos.id_producto')
                ->count();
            if ($faltantes > 0) {
                throw new RuntimeException("Hay {$faltantes} referencias de productos inexistentes en {$tabla}.{$columna}.");
            }
        }

        $ids = $productos->pluck('id_producto')->mapWithKeys(fn ($id): array => [(string) $id => true])->all();
        foreach (DB::table('solicitud_webs')->select('id_solicitud', 'detalles_productos')->cursor() as $solicitud) {
            try {
                $detalles = json_decode($solicitud->detalles_productos, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException("La solicitud #{$solicitud->id_solicitud} contiene detalles inválidos.", previous: $exception);
            }
            if (! is_array($detalles)) {
                throw new RuntimeException("La solicitud #{$solicitud->id_solicitud} contiene detalles inválidos.");
            }
            foreach ($detalles as $detalle) {
                $id = is_array($detalle) ? ($detalle['id_producto'] ?? null) : null;
                if ((! is_int($id) && ! is_string($id)) || ! preg_match('/^[1-9]\d*$/D', (string) $id) || ! isset($ids[(string) $id])) {
                    throw new RuntimeException("La solicitud #{$solicitud->id_solicitud} contiene una referencia de producto inexistente o inválida.");
                }
            }
        }
    }
}
