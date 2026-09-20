<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Nullability correction, separate from missing-column drift.
     * create_ticket_vendors_table declares `ticket_system_id`, `vendor_id`,
     * and `price` as NOT NULL (no ->nullable()), but live Dev has all
     * three as nullable. No column is missing here -- only the
     * nullability of already-tracked columns is wrong. Raw SQL used to
     * avoid depending on doctrine/dbal for a MODIFY-only change.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE ticket_vendors MODIFY ticket_system_id TEXT NULL");
        DB::statement("ALTER TABLE ticket_vendors MODIFY vendor_id TEXT NULL");
        DB::statement("ALTER TABLE ticket_vendors MODIFY price TEXT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE ticket_vendors MODIFY price TEXT NOT NULL");
        DB::statement("ALTER TABLE ticket_vendors MODIFY vendor_id TEXT NOT NULL");
        DB::statement("ALTER TABLE ticket_vendors MODIFY ticket_system_id TEXT NOT NULL");
    }
};
