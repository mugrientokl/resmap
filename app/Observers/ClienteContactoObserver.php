<?php

namespace App\Observers;

use App\Models\Cliente;
use Illuminate\Support\Facades\DB;

class ClienteContactoObserver
{
    public function updating(Cliente $cliente): void
    {
        $contactoAnterior = $cliente->getOriginal('contacto');

        if ($cliente->isDirty('contacto') && filled($contactoAnterior)) {
            DB::table('cliente_contactos_historial')->insert([
                'id_cliente' => $cliente->id_cliente,
                'contacto' => $contactoAnterior,
                'cambiado_at' => now(),
            ]);
        }
    }
}
