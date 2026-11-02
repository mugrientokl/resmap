<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditorias', function (Blueprint $table): void {
            $table->string('categoria', 20)->default('datos')->after('accion');
            $table->string('resultado', 10)->default('exito')->after('categoria');
            $table->string('descripcion')->nullable()->after('resultado');
            $table->string('actor_nombre')->nullable()->after('user_id');
            $table->uuid('correlation_id')->nullable()->after('ip');
            $table->uuid('lote_id')->nullable()->after('correlation_id');
            $table->string('user_agent', 255)->nullable()->after('lote_id');
            $table->char('hash_previo', 64)->nullable();
            $table->char('hash', 64)->nullable();

            $table->index('created_at');
            $table->index('accion');
            $table->index(['categoria', 'created_at']);
            $table->index('lote_id');
            $table->index('correlation_id');
        });
    }

    public function down(): void
    {
        Schema::table('auditorias', function (Blueprint $table): void {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['accion']);
            $table->dropIndex(['categoria', 'created_at']);
            $table->dropIndex(['lote_id']);
            $table->dropIndex(['correlation_id']);
            $table->dropColumn([
                'categoria', 'resultado', 'descripcion', 'actor_nombre', 'correlation_id',
                'lote_id', 'user_agent', 'hash_previo', 'hash',
            ]);
        });
    }
};
