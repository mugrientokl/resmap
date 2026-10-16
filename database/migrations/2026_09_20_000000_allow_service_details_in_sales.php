<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_ventas', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_producto')->nullable()->change();
            $table->string('descripcion')->nullable()->after('id_producto');
        });
    }

    public function down(): void
    {
        Schema::table('detalle_ventas', function (Blueprint $table): void {
            $table->dropColumn('descripcion');
            $table->unsignedBigInteger('id_producto')->nullable(false)->change();
        });
    }
};
