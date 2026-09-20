<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Type/default correction, separate from missing-column drift.
     * create_account_statements_table declares `transaction_type` as
     * $table->text("transaction_type"), but live Dev has it as
     * int(11) NOT NULL DEFAULT 1. Confirmed by code evidence: every write
     * site across the codebase (28 of 30 occurrences) uses a hardcoded
     * integer literal (1, 2, 3, or 4); the remaining 2 sites
     * (Frontend\AccountsController.php, Frontend\MoneyArea\
     * StoragesController.php) pass through $request->transaction_type,
     * sourced from <select> filter dropdowns with option value="1"/"2"
     * (resources/views/accounts_statement/all.blade.php,
     * moneyarea/storages/all_get.blade.php). No non-integer value is
     * used anywhere. Raw SQL used instead of Schema::change() to avoid
     * depending on whether doctrine/dbal is installed, matching the
     * precedent set by
     * 2026_09_19_000003_convert_account_statements_balance_columns_to_decimal.php.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE account_statements MODIFY transaction_type INT(11) NOT NULL DEFAULT 1");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE account_statements MODIFY transaction_type TEXT NOT NULL");
    }
};
