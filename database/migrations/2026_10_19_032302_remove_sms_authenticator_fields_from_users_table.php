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
        $obsoleteColumns = [
            'mfa_secret',
            'mfa_recovery_codes',
            'mfa_enabled_at',
            'mfa_phone',
            'mfa_sms_code_hash',
            'mfa_sms_code_expires_at',
            'mfa_sms_sent_at',
        ];
        $columns = array_values(array_filter(
            $obsoleteColumns,
            static fn (string $column): bool => Schema::hasColumn('users', $column),
        ));

        if ($columns !== []) {
            Schema::table('users', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
