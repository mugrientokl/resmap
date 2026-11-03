<?php

use App\Support\Rut;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $clientes = DB::table('clientes')->orderBy('id_cliente')->get(['id_cliente', 'rut']);
        $conservados = [];

        foreach ($clientes as $cliente) {
            $normalizado = Rut::normalizar($cliente->rut);

            if ($normalizado === null) {
                continue;
            }

            if (isset($conservados[$normalizado])) {
                $this->fusionar($conservados[$normalizado], $cliente->id_cliente);

                continue;
            }

            $conservados[$normalizado] = $cliente->id_cliente;
        }

        foreach ($conservados as $normalizado => $idCliente) {
            DB::table('clientes')
                ->where('id_cliente', $idCliente)
                ->update(['rut' => $normalizado]);
        }
    }

    public function down(): void
    {
        // La normalización y la fusión de duplicados no son reversibles.
    }

    private function fusionar(int $idConservado, int $idDuplicado): void
    {
        DB::table('ventas')
            ->where('id_cliente', $idDuplicado)
            ->update(['id_cliente' => $idConservado]);

        DB::table('solicitud_webs')
            ->where('id_cliente', $idDuplicado)
            ->update(['id_cliente' => $idConservado]);

        DB::table('clientes')->where('id_cliente', $idDuplicado)->delete();
    }
};
