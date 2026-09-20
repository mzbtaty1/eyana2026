<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Type/default correction, separate from missing-column drift.
     * create_storages_table declares `balance` as
     * $table->text("balance")->default(0), but live Dev has it as
     * varchar(255) NOT NULL DEFAULT '0'. Raw SQL used instead of
     * Schema::change() to avoid depending on whether doctrine/dbal is
     * installed, matching the precedent set by
     * 2026_09_19_000003_convert_account_statements_balance_columns_to_decimal.php.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE storages MODIFY balance VARCHAR(255) NOT NULL DEFAULT '0'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE storages MODIFY balance TEXT NOT NULL");
    }
};
