<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ticket_users schema drift catch-up. These 3 columns exist on live
     * Dev but were never captured by the tracked create_ticket_users_table
     * migration. The application (TicketUser model, InvoiceController,
     * Frontend\InvoicesController, Frontend\SharedInvoices) reads and
     * writes these columns at runtime, so a fresh database build would be
     * missing columns the app requires. Recreates each at its live Dev
     * type/nullability, in live column order.
     *
     * This migration only adds the 3 missing columns. The separate
     * nullability mismatch on the already-tracked client_passport_id
     * column is intentionally handled by its own dedicated migration, not
     * here.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('ticket_users', 'client_type')) {
            Schema::table('ticket_users', function (Blueprint $table) {
                $table->text('client_type')->after('client_name');
            });
        }

        if (!Schema::hasColumn('ticket_users', 'client_phone')) {
            Schema::table('ticket_users', function (Blueprint $table) {
                $table->text('client_phone')->nullable()->after('client_passport_id');
            });
        }

        if (!Schema::hasColumn('ticket_users', 'crt_at')) {
            Schema::table('ticket_users', function (Blueprint $table) {
                $table->text('crt_at')->after('client_phone');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('ticket_users', 'crt_at')) {
            Schema::table('ticket_users', function (Blueprint $table) {
                $table->dropColumn('crt_at');
            });
        }

        if (Schema::hasColumn('ticket_users', 'client_phone')) {
            Schema::table('ticket_users', function (Blueprint $table) {
                $table->dropColumn('client_phone');
            });
        }

        if (Schema::hasColumn('ticket_users', 'client_type')) {
            Schema::table('ticket_users', function (Blueprint $table) {
                $table->dropColumn('client_type');
            });
        }
    }
};
