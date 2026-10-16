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
        Schema::table('solicitud_webs', function (Blueprint $table) {
            $table->text('observaciones')->nullable()->after('detalles_productos');
            $table->timestamp('atendida_at')->nullable()->after('observaciones');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitud_webs', function (Blueprint $table) {
            $table->dropColumn(['observaciones', 'atendida_at']);
        });
    }
};
