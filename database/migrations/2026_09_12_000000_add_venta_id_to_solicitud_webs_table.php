<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud_webs', function (Blueprint $table): void {
            $table->unsignedBigInteger('venta_id')->nullable()->after('id_cliente')->unique();
            $table->foreign('venta_id')->references('id_venta')->on('ventas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_webs', function (Blueprint $table): void {
            $table->dropForeign(['venta_id']);
            $table->dropUnique(['venta_id']);
            $table->dropColumn('venta_id');
        });
    }
};
