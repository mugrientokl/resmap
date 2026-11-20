<?php

use App\Models\Producto;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->actualizarClaves(fn (string $nombre): string => Producto::claveNombre($nombre));
    }

    public function down(): void
    {
        $this->actualizarClaves(
            fn (string $nombre): string => hash('sha256', mb_convert_case(trim($nombre), MB_CASE_FOLD, 'UTF-8'))
        );
    }

    private function actualizarClaves(callable $claveNombre): void
    {
        DB::transaction(function () use ($claveNombre): void {
            $productos = DB::table('productos')->orderBy('id_producto')->get(['id_producto', 'nombre']);
            DB::table('productos')->update(['nombre_normalizado' => null]);
            $clavesAsignadas = [];

            foreach ($productos as $producto) {
                $clave = $claveNombre($producto->nombre);
                if (isset($clavesAsignadas[$clave])) {
                    continue;
                }

                DB::table('productos')->where('id_producto', $producto->id_producto)
                    ->update(['nombre_normalizado' => $clave]);
                $clavesAsignadas[$clave] = true;
            }
        });
    }
};
