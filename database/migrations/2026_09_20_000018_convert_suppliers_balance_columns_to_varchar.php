<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Type/default correction, separate from the suppliers nullability
     * correction above. create_suppliers_table declares
     * `debit_opening_balance` and `opening_credit_balance` as TEXT NOT
     * NULL with no default, but live Dev has both as
     * varchar(255) NOT NULL DEFAULT '0'. Confirmed by code evidence: both
     * inputs are marked `required` on create.blade.php and edit.blade.php,
     * default to value="0", and are numeric-only (inputmode="numeric"
     * with JS stripping non-digit characters) -- confirming the live
     * NOT NULL DEFAULT '0' semantics are correct; only the migration's
     * type and missing default were wrong. Raw SQL used instead of
     * Schema::change() to avoid depending on whether doctrine/dbal is
     * installed, matching the precedent set by
     * 2026_09_19_000003_convert_account_statements_balance_columns_to_decimal.php.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE suppliers MODIFY debit_opening_balance VARCHAR(255) NOT NULL DEFAULT '0'");
        DB::statement("ALTER TABLE suppliers MODIFY opening_credit_balance VARCHAR(255) NOT NULL DEFAULT '0'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE suppliers MODIFY opening_credit_balance TEXT NOT NULL");
        DB::statement("ALTER TABLE suppliers MODIFY debit_opening_balance TEXT NOT NULL");
    }
};
