<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud_webs', function (Blueprint $table): void {
            $table->string('tipo_solicitud')->default('pedido')->after('estado');
            $table->string('tipo_servicio')->nullable()->after('tipo_solicitud');
            $table->text('descripcion_servicio')->nullable()->after('tipo_servicio');
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_webs', function (Blueprint $table): void {
            $table->dropColumn(['tipo_solicitud', 'tipo_servicio', 'descripcion_servicio']);
        });
    }
};
