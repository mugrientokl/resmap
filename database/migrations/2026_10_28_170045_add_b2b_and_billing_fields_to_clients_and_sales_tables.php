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
        Schema::table('clientes', function (Blueprint $table): void {
            $table->timestamp('consentimiento_whatsapp_at')->nullable();
            $table->string('consentimiento_whatsapp_origen')->nullable();
        });

        Schema::table('solicitud_webs', function (Blueprint $table): void {
            $table->string('metodo_entrega', 30)->nullable();
            $table->string('direccion_calle')->nullable();
            $table->string('direccion_numero', 50)->nullable();
            $table->string('direccion_comuna', 100)->nullable();
            $table->string('direccion_ciudad', 100)->nullable();
            $table->string('direccion_region', 100)->nullable();
            $table->string('direccion_referencias', 1000)->nullable();
        });

        Schema::table('ventas', function (Blueprint $table): void {
            $table->foreignId('cotizacion_version_id')->nullable()->constrained('cotizacion_versiones', 'id_version')->nullOnDelete();
            $table->string('estado_venta', 30)->nullable()->index();
            $table->string('estado_pago', 30)->nullable()->index();
            $table->string('estado_facturacion', 30)->nullable()->index();
            $table->string('estado_entrega', 30)->nullable()->index();
            $table->decimal('monto_pagado', 12, 2)->nullable();
            $table->string('metodo_entrega', 30)->nullable();
            $table->string('direccion_despacho')->nullable();
            $table->string('comuna_despacho')->nullable();
            $table->string('ciudad_despacho')->nullable();
            $table->string('region_despacho')->nullable();
            $table->string('referencias_despacho')->nullable();
            $table->foreignId('credito_autorizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('credito_autorizado_at')->nullable();
            $table->timestamp('stock_descontado_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cotizacion_version_id');
            $table->dropConstrainedForeignId('credito_autorizado_por');
            $table->dropColumn([
                'estado_venta',
                'estado_pago',
                'estado_facturacion',
                'estado_entrega',
                'monto_pagado',
                'metodo_entrega',
                'direccion_despacho',
                'comuna_despacho',
                'ciudad_despacho',
                'region_despacho',
                'referencias_despacho',
                'credito_autorizado_at',
                'stock_descontado_at',
            ]);
        });

        Schema::table('solicitud_webs', function (Blueprint $table): void {
            $table->dropColumn([
                'metodo_entrega',
                'direccion_calle',
                'direccion_numero',
                'direccion_comuna',
                'direccion_ciudad',
                'direccion_region',
                'direccion_referencias',
            ]);
        });

        Schema::table('clientes', function (Blueprint $table): void {
            $table->dropColumn(['consentimiento_whatsapp_at', 'consentimiento_whatsapp_origen']);
        });
    }
};
