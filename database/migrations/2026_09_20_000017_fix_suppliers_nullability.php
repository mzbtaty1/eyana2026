<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Nullability correction, separate from missing-column drift and from
     * the suppliers balance-columns type/default correction below.
     * create_suppliers_table declares `phone_2`, `passport_id`,
     * `passport_expiration_date`, `email`, and `address` as NOT NULL (no
     * ->nullable()), but live Dev has all five as nullable. Confirmed by
     * code evidence:
     *  - phone_2/email/address: optional inputs with no `required`
     *    attribute on both create.blade.php and edit.blade.php;
     *    phone_2's placeholder literally reads "رقم الهاتف 2 (غير الزامي)"
     *    ("phone number 2 (not mandatory)").
     *  - passport_id/passport_expiration_date: the form fields exist but
     *    are commented out in both Supplier::create() and the update()
     *    call in Frontend\SuppliersController.php -- the application
     *    never actually writes these columns.
     * Raw SQL used to avoid depending on doctrine/dbal for a MODIFY-only
     * change.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE suppliers MODIFY phone_2 TEXT NULL");
        DB::statement("ALTER TABLE suppliers MODIFY passport_id TEXT NULL");
        DB::statement("ALTER TABLE suppliers MODIFY passport_expiration_date TEXT NULL");
        DB::statement("ALTER TABLE suppliers MODIFY email TEXT NULL");
        DB::statement("ALTER TABLE suppliers MODIFY address TEXT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE suppliers MODIFY address TEXT NOT NULL");
        DB::statement("ALTER TABLE suppliers MODIFY email TEXT NOT NULL");
        DB::statement("ALTER TABLE suppliers MODIFY passport_expiration_date TEXT NOT NULL");
        DB::statement("ALTER TABLE suppliers MODIFY passport_id TEXT NOT NULL");
        DB::statement("ALTER TABLE suppliers MODIFY phone_2 TEXT NOT NULL");
    }
};
