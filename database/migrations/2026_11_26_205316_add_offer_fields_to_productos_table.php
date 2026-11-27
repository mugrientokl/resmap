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
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('precio_oferta', 10, 2)->nullable()->after('precio');
            $table->decimal('porcentaje_descuento', 5, 2)->nullable()->after('precio_oferta');
            $table->timestamp('oferta_inicio_at')->nullable()->after('porcentaje_descuento');
            $table->timestamp('oferta_fin_at')->nullable()->after('oferta_inicio_at');
            $table->index(['activo', 'oferta_fin_at'], 'productos_oferta_vigente_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex('productos_oferta_vigente_index');
            $table->dropColumn([
                'precio_oferta',
                'porcentaje_descuento',
                'oferta_inicio_at',
                'oferta_fin_at',
            ]);
        });
    }
};
