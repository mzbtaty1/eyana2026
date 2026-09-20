<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Nullability correction, separate from the missing-column drift
     * handled by 2026_09_20_000008_add_remaining_drift_columns_to_ticket_users_table.php.
     * create_ticket_users_table declares `client_passport_id` as NOT NULL
     * (no ->nullable()), but live Dev has it as nullable. Raw SQL used to
     * avoid depending on doctrine/dbal for a MODIFY-only change.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE ticket_users MODIFY client_passport_id TEXT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE ticket_users MODIFY client_passport_id TEXT NOT NULL");
    }
};
