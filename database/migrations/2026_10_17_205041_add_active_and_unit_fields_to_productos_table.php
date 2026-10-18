<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('productos')->update(['unidad' => 'unidad']);

        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('unidad');
            $table->string('unidad')->default('unidad')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('activo');
            $table->string('unidad')->nullable()->default(null)->change();
        });
    }
};
