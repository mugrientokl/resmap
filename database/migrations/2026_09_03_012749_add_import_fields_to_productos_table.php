<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('codigo_origen')->nullable()->after('codigo_barra');
            $table->string('ubicacion')->nullable()->after('id_categoria');
            $table->string('unidad')->nullable()->after('ubicacion');
            $table->unsignedInteger('fila_origen')->nullable()->after('unidad');
            $table->string('estado_importacion')->default('manual')->after('fila_origen');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn([
                'codigo_origen',
                'ubicacion',
                'unidad',
                'fila_origen',
                'estado_importacion',
            ]);
        });
    }
};
