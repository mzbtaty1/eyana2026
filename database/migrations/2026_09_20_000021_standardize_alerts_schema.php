<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Schema-standardization, approved as a design decision (not a
     * missing-column or drift fix). Live Dev has `alerts` as an outlier:
     * id is bigint(20) signed (not the bigint unsigned every other table
     * uses), engine is MyISAM, and the table's default charset is
     * latin1_swedish_ci. Investigation confirmed alerts has no foreign
     * keys in either direction, and no code in
     * Frontend\AlertsController.php depends on the current engine's
     * transaction/FK/full-text behavior or on id's signedness -- it is
     * only ever used as an opaque identifier. `alert_txt` already carries
     * its own utf8mb4_unicode_ci override independent of the table
     * default, so this migration leaves it untouched.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE alerts ENGINE=InnoDB");
        DB::statement("ALTER TABLE alerts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        DB::statement("ALTER TABLE alerts MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE alerts MODIFY id BIGINT NOT NULL AUTO_INCREMENT");
        DB::statement("ALTER TABLE alerts CONVERT TO CHARACTER SET latin1 COLLATE latin1_swedish_ci");
        // CONVERT TO CHARACTER SET above also collapses alert_txt's
        // charset; restore its original utf8mb4 override so the rollback
        // exactly matches the live state this migration started from.
        DB::statement("ALTER TABLE alerts MODIFY alert_txt TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
        DB::statement("ALTER TABLE alerts ENGINE=MyISAM");
    }
};
