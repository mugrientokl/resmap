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
        Schema::create('eventos_webhook_whatsapp', function (Blueprint $table): void {
            $table->id('id_evento_webhook');
            $table->string('id_proveedor')->unique();
            $table->char('payload_sha256', 64);
            $table->json('payload');
            $table->timestamp('procesado_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eventos_webhook_whatsapp');
    }
};
