<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_recodificaciones', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_producto')->primary();
            $table->string('codigo_barra_anterior');
            $table->string('codigo_origen_anterior')->nullable();
            $table->string('codigo_nuevo')->unique();
            $table->timestamp('recodificado_at');
        });
    }

    public function down(): void
    {
        if (DB::table('producto_recodificaciones')->exists()) {
            throw new RuntimeException('No se puede eliminar el registro de códigos convertido. Conserva el respaldo y la correspondencia.');
        }

        Schema::dropIfExists('producto_recodificaciones');
    }
};
