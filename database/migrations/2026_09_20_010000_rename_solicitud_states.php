<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('solicitud_webs')->where('estado', 'Pagado')->update(['estado' => 'Pagado - pendiente de entrega']);
        DB::table('solicitud_webs')->where('estado', 'Pendiente de pago')->update(['estado' => 'Entregado - pago pendiente']);
        DB::table('solicitud_webs')->where('estado', 'Entregado')->update(['estado' => 'Entregado y completado']);
    }

    public function down(): void
    {
        DB::table('solicitud_webs')->where('estado', 'Pagado - pendiente de entrega')->update(['estado' => 'Pagado']);
        DB::table('solicitud_webs')->where('estado', 'Entregado - pago pendiente')->update(['estado' => 'Pendiente de pago']);
        DB::table('solicitud_webs')->where('estado', 'Entregado y completado')->update(['estado' => 'Entregado']);
    }
};
