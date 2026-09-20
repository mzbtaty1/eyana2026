<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Nullability correction, separate from missing-column drift.
     * create_invoices_table declares `invoice_comments` as NOT NULL (no
     * ->nullable()), but live Dev has it as nullable. Confirmed by code
     * evidence: the create/edit forms (resources/views/invoices/
     * create.blade.php:134, edit_invoice.blade.php:177) render this as an
     * optional text input with no `required` attribute, and every
     * Invoice::create() call site sources it directly from
     * $request->invoice_comments, which is legitimately absent/empty for
     * an optional field. Raw SQL used to avoid depending on doctrine/dbal
     * for a MODIFY-only change.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE invoices MODIFY invoice_comments TEXT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE invoices MODIFY invoice_comments TEXT NOT NULL");
    }
};
