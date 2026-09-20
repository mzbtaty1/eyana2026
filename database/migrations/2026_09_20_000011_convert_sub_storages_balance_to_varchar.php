<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Type/default correction, separate from missing-column drift.
     * create_sub_storages_table declares `balance` as
     * $table->text("balance")->default(0), but live Dev has it as
     * varchar(255) NOT NULL DEFAULT '0'. Same shape as the storages.balance
     * correction and the existing
     * 2026_09_19_000003_convert_account_statements_balance_columns_to_decimal.php
     * precedent. Raw SQL used to avoid depending on doctrine/dbal.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE sub_storages MODIFY balance VARCHAR(255) NOT NULL DEFAULT '0'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE sub_storages MODIFY balance TEXT NOT NULL");
    }
};
