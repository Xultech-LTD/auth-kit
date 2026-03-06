<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AddAuthKitTwoFactorColumnsToUsersTable
 *
 * Adds two-factor fields to the users table using config-driven column names.
 *
 * Column names are resolved from:
 * - authkit.two_factor.columns.enabled
 * - authkit.two_factor.columns.secret
 * - authkit.two_factor.columns.recovery_codes
 * - authkit.two_factor.columns.methods
 *
 * If your application uses a different users table name, update the migration accordingly.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        $table = (string) config('authkit.two_factor.table', 'users');

        $enabledCol = (string) config('authkit.two_factor.columns.enabled', 'two_factor_enabled');
        $secretCol = (string) config('authkit.two_factor.columns.secret', 'two_factor_secret');
        $recoveryCol = (string) config('authkit.two_factor.columns.recovery_codes', 'two_factor_recovery_codes');
        $methodsCol = (string) config('authkit.two_factor.columns.methods', 'two_factor_methods');

        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($table, $enabledCol, $secretCol, $recoveryCol, $methodsCol) {
            if (!Schema::hasColumn($table, $enabledCol)) {
                $t->boolean($enabledCol)->default(false);
            }

            if (!Schema::hasColumn($table, $secretCol)) {
                $t->text($secretCol)->nullable();
            }

            if (!Schema::hasColumn($table, $recoveryCol)) {
                $t->json($recoveryCol)->nullable();
            }

            if (!Schema::hasColumn($table, $methodsCol)) {
                $t->json($methodsCol)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        $table = (string) config('authkit.two_factor.table', 'users');

        $enabledCol = (string) config('authkit.two_factor.columns.enabled', 'two_factor_enabled');
        $secretCol = (string) config('authkit.two_factor.columns.secret', 'two_factor_secret');
        $recoveryCol = (string) config('authkit.two_factor.columns.recovery_codes', 'two_factor_recovery_codes');
        $methodsCol = (string) config('authkit.two_factor.columns.methods', 'two_factor_methods');

        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($table, $enabledCol, $secretCol, $recoveryCol, $methodsCol) {
            $drop = [];

            if (Schema::hasColumn($table, $methodsCol)) {
                $drop[] = $methodsCol;
            }

            if (Schema::hasColumn($table, $recoveryCol)) {
                $drop[] = $recoveryCol;
            }

            if (Schema::hasColumn($table, $secretCol)) {
                $drop[] = $secretCol;
            }

            if (Schema::hasColumn($table, $enabledCol)) {
                $drop[] = $enabledCol;
            }

            if ($drop !== []) {
                $t->dropColumn($drop);
            }
        });
    }
};