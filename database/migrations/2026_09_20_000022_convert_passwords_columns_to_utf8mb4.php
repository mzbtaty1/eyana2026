<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Charset correction for the existing live `passwords` table,
     * approved as a design decision. `url`, `user`, and `pass` are
     * currently latin1_swedish_ci with no per-column override -- but the
     * form that populates them (resources/views/passwords/create.blade.php)
     * is Arabic-labeled free text with no ASCII-only validation, so
     * latin1 cannot safely store content a user could legitimately enter.
     * Investigation confirmed the table has 0 rows and no foreign keys,
     * so this conversion is lossless. Only these 3 columns are modified
     * via an explicit per-column CHARACTER SET, matching the same
     * override pattern already used for alerts.alert_txt -- the table's
     * own engine (MyISAM) and default charset (latin1) are left
     * untouched, along with id/created_at/updated_at, per the approved
     * scope. The separately-committed create_passwords_table.php migration
     * is not modified; it already produces utf8mb4 on a fresh install via
     * the connection default, so this migration exists solely to correct
     * the pre-existing live table.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE passwords MODIFY url TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
        DB::statement("ALTER TABLE passwords MODIFY user TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
        DB::statement("ALTER TABLE passwords MODIFY pass TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE passwords MODIFY pass TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL");
        DB::statement("ALTER TABLE passwords MODIFY user TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL");
        DB::statement("ALTER TABLE passwords MODIFY url TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL");
    }
};
