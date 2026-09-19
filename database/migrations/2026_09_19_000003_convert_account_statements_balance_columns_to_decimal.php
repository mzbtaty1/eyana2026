<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // P6: debit_balance/credit_balance were VARCHAR(255), allowing
        // unconstrained/non-numeric values (the reason P1's
        // reportNonNumericValues check exists). Verified via full-table
        // audit that all 19,767 existing rows are already clean numeric
        // values with <=2 decimal places, so this is a lossless,
        // zero-cleanup type tightening -- matches the precision already
        // used by ledger_net_effect. Raw SQL used instead of Schema::change()
        // to avoid depending on whether doctrine/dbal is installed.
        DB::statement("ALTER TABLE account_statements MODIFY debit_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00");
        DB::statement("ALTER TABLE account_statements MODIFY credit_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE account_statements MODIFY debit_balance VARCHAR(255) NOT NULL DEFAULT '0'");
        DB::statement("ALTER TABLE account_statements MODIFY credit_balance VARCHAR(255) NOT NULL DEFAULT '0'");
    }
};
