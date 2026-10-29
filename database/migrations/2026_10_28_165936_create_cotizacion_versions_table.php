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
        Schema::create('cotizacion_versiones', function (Blueprint $table): void {
            $table->id('id_version');
            $table->foreignId('cotizacion_id')->constrained('cotizaciones', 'id_cotizacion')->cascadeOnDelete();
            $table->foreignId('revisada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('numero_version');
            $table->string('estado', 40)->default('borrador')->index();
            $table->decimal('neto', 12, 2);
            $table->decimal('iva', 12, 2);
            $table->decimal('costo_despacho', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('metodo_entrega', 30);
            $table->string('calle')->nullable();
            $table->string('numero')->nullable();
            $table->string('comuna')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('region')->nullable();
            $table->string('referencias')->nullable();
            $table->timestamp('vigente_hasta');
            $table->char('token_cliente_hash', 64)->nullable()->unique();
            $table->timestamp('token_expira_at')->nullable();
            $table->timestamp('enviada_at')->nullable();
            $table->string('email_estado', 30)->nullable();
            $table->string('whatsapp_estado', 30)->nullable();
            $table->timestamp('aceptada_at')->nullable();
            $table->timestamp('rechazada_at')->nullable();
            $table->timestamp('cambios_solicitados_at')->nullable();
            $table->string('telefono_aceptacion')->nullable();
            $table->string('ip_aceptacion', 45)->nullable();
            $table->timestamps();

            $table->unique(['cotizacion_id', 'numero_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizacion_versiones');
    }
};
