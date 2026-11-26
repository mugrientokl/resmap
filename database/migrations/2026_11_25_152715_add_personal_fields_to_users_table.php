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
        Schema::table('users', function (Blueprint $table) {
            $table->string('apellido_paterno', 255)->nullable()->after('name');
            $table->string('apellido_materno', 255)->nullable()->after('apellido_paterno');
            $table->string('run', 20)->nullable()->unique()->after('apellido_materno');
            $table->string('telefono', 25)->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['run']);
            $table->dropColumn(['apellido_paterno', 'apellido_materno', 'run', 'telefono']);
        });
    }
};
