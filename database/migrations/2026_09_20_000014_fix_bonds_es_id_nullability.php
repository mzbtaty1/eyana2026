<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Nullability correction, separate from missing-column drift.
     * create_bonds_table declares `es_id` as NOT NULL (no ->nullable()),
     * but live Dev has it as nullable. Confirmed by code evidence: all
     * three Bond::create() call sites (Frontend\InvoicesController.php,
     * Frontend\MoneyArea\BondsController.php x2) omit `es_id` from the
     * initial create array entirely -- it is always backfilled afterward
     * via a separate Bond::where(...)->update(["es_id" => "FLY-BD" . $id])
     * call using the row's own auto-incremented id. The column must be
     * nullable (or at least omittable) at creation time for this pattern
     * to work. Raw SQL used to avoid depending on doctrine/dbal for a
     * MODIFY-only change.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE bonds MODIFY es_id TEXT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE bonds MODIFY es_id TEXT NOT NULL");
    }
};
