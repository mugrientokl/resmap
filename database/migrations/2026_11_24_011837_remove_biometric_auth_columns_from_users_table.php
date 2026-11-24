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
            if (Schema::hasColumn('users', 'face_descriptor')) {
                $table->dropColumn('face_descriptor');
            }

            if (Schema::hasColumn('users', 'face_consent_at')) {
                $table->dropColumn('face_consent_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The removed data cannot be reconstructed during rollback.
    }
};
