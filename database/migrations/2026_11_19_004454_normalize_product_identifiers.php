<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_identificadores_anteriores', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_producto')->primary();
            $table->string('codigo_barra');
            $table->string('codigo_origen')->nullable();
            $table->string('nombre');
        });
        Schema::table('productos', function (Blueprint $table): void {
            $table->char('nombre_normalizado', 64)->nullable();
        });

        DB::transaction(function (): void {
            $productos = DB::table('productos')->orderBy('id_producto')->get();
            $nombresOriginales = $productos->mapWithKeys(fn ($producto): array => [
                mb_convert_case(trim($producto->nombre), MB_CASE_FOLD, 'UTF-8') => true,
            ])->all();
            $nombresAsignados = [];

            foreach ($productos as $producto) {
                DB::table('producto_identificadores_anteriores')->insert([
                    'id_producto' => $producto->id_producto,
                    'codigo_barra' => $producto->codigo_barra,
                    'codigo_origen' => $producto->codigo_origen,
                    'nombre' => $producto->nombre,
                ]);
                DB::table('productos')->where('id_producto', $producto->id_producto)->update([
                    'codigo_barra' => 'TEMP-'.Str::uuid(),
                    'codigo_origen' => null,
                ]);
            }

            foreach ($productos as $producto) {
                $nombre = trim($producto->nombre);
                $clave = mb_convert_case($nombre, MB_CASE_FOLD, 'UTF-8');
                if (isset($nombresAsignados[$clave]) || DB::table('productos')
                    ->where('id_producto', '!=', $producto->id_producto)
                    ->where('nombre', $nombre)
                    ->exists()) {
                    $sufijo = ' - '.$producto->id_producto;
                    $nombre = mb_substr($nombre, 0, 255 - mb_strlen($sufijo)).$sufijo;
                    while (isset($nombresOriginales[mb_convert_case($nombre, MB_CASE_FOLD, 'UTF-8')]) || isset($nombresAsignados[mb_convert_case($nombre, MB_CASE_FOLD, 'UTF-8')])
                        || DB::table('productos')->where('id_producto', '!=', $producto->id_producto)->where('nombre', $nombre)->exists()) {
                        $sufijo .= '-1';
                        $nombre = mb_substr(trim($producto->nombre), 0, 255 - mb_strlen($sufijo)).$sufijo;
                    }
                }
                $nombresAsignados[mb_convert_case($nombre, MB_CASE_FOLD, 'UTF-8')] = true;
                $codigo = sprintf('RESMAP-%06d', $producto->id_producto);
                DB::table('productos')->where('id_producto', $producto->id_producto)->update([
                    'codigo_barra' => $codigo,
                    'codigo_origen' => $codigo,
                    'nombre' => $nombre,
                    'nombre_normalizado' => hash('sha256', mb_convert_case($nombre, MB_CASE_FOLD, 'UTF-8')),
                ]);
            }
        });

        Schema::table('productos', function (Blueprint $table): void {
            $table->unique('codigo_origen');
            $table->unique('nombre');
            $table->unique('nombre_normalizado');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            $table->dropUnique(['codigo_origen']);
            $table->dropUnique(['nombre']);
            $table->dropUnique(['nombre_normalizado']);
        });

        DB::transaction(function (): void {
            $anteriores = DB::table('producto_identificadores_anteriores')->get();
            foreach ($anteriores as $anterior) {
                DB::table('productos')->where('id_producto', $anterior->id_producto)->update([
                    'codigo_barra' => 'TEMP-'.Str::uuid(),
                ]);
            }
            foreach ($anteriores as $anterior) {
                DB::table('productos')->where('id_producto', $anterior->id_producto)->update([
                    'codigo_barra' => $anterior->codigo_barra,
                    'codigo_origen' => $anterior->codigo_origen,
                    'nombre' => $anterior->nombre,
                ]);
            }
        });

        Schema::table('productos', function (Blueprint $table): void {
            $table->dropColumn('nombre_normalizado');
        });

        Schema::drop('producto_identificadores_anteriores');
    }
};
