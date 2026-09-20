<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Schema-standardization, approved as a design decision (not a
     * missing-column or drift fix). Live Dev has `transaction_balances`
     * as an outlier: id is int(11) signed (not the bigint unsigned every
     * other table uses) and the table/its text columns are
     * utf8mb3_general_ci (not utf8mb4_unicode_ci). Investigation
     * confirmed this table has 0 rows, no foreign keys in either
     * direction, and no live code that reads or writes it (the
     * TransactionBalance model is imported in InvoicesController.php and
     * SharedInvoices.php but never actually called) -- so there is
     * nothing to preserve by reproducing the legacy int(11)/utf8mb3
     * shape, and standardizing to match every other table carries no
     * compatibility risk. created_at/updated_at are left untouched.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE transaction_balances CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        DB::statement("ALTER TABLE transaction_balances MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE transaction_balances MODIFY id INT(11) NOT NULL AUTO_INCREMENT");
        DB::statement("ALTER TABLE transaction_balances CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci");
    }
};
