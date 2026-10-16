<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('razon_social')->nullable()->after('nombre');
            $table->string('giro')->nullable()->after('razon_social');
            $table->string('comuna')->nullable()->after('direccion');
            $table->string('ciudad')->nullable()->after('comuna');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->string('estado_dte')->default('pendiente')->after('estado_sii');
            $table->string('track_id_sii')->nullable()->after('estado_dte');
            $table->longText('xml_dte')->nullable()->after('track_id_sii');
            $table->longText('pdf_dte')->nullable()->after('xml_dte');
            $table->dateTime('fecha_envio_sii')->nullable()->after('pdf_dte');
            $table->dateTime('fecha_respuesta_sii')->nullable()->after('fecha_envio_sii');
            $table->text('error_sii')->nullable()->after('fecha_respuesta_sii');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn([
                'estado_dte',
                'track_id_sii',
                'xml_dte',
                'pdf_dte',
                'fecha_envio_sii',
                'fecha_respuesta_sii',
                'error_sii',
            ]);
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['razon_social', 'giro', 'comuna', 'ciudad']);
        });
    }
};
