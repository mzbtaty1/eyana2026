<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Invoices schema drift catch-up. These 16 columns exist on live Dev
     * but were never captured by a tracked migration. The application
     * (InvoiceController, Frontend\InvoicesController, Frontend\SharedInvoices,
     * Frontend\ProfitsController, and the ReconcileBalances command) reads and
     * writes them at runtime, so a fresh database build would be missing
     * columns the app requires. Recreates each at its live Dev
     * type/nullability/default, in live column order.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('invoices', 'ticket_system_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->text('ticket_system_id')->after('es_id');
            });
        }

        if (!Schema::hasColumn('invoices', 'invoice_travel_date')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->text('invoice_travel_date')->after('invoice_date');
            });
        }

        if (!Schema::hasColumn('invoices', 'return_date')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->text('return_date')->nullable()->after('invoice_travel_date');
            });
        }

        if (!Schema::hasColumn('invoices', 'invoice_airline')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->text('invoice_airline')->after('return_date');
            });
        }

        if (!Schema::hasColumn('invoices', 'from_location')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->text('from_location')->after('invoice_airline');
            });
        }

        if (!Schema::hasColumn('invoices', 'to_location')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->text('to_location')->after('from_location');
            });
        }

        if (!Schema::hasColumn('invoices', 'invoice_ticket_file')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->text('invoice_ticket_file')->after('invoice_draft');
            });
        }

        if (!Schema::hasColumn('invoices', 'invoice_create_by')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->text('invoice_create_by')->after('invoice_ticket_file');
            });
        }

        if (!Schema::hasColumn('invoices', 'invoice_approved_by')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->text('invoice_approved_by')->nullable()->after('invoice_create_by');
            });
        }

        if (!Schema::hasColumn('invoices', 'invoice_money_pay')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->integer('invoice_money_pay')->default(0)->after('invoice_status');
            });
        }

        if (!Schema::hasColumn('invoices', 'invoice_shared')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->integer('invoice_shared')->default(0)->after('invoice_money_pay');
            });
        }

        if (!Schema::hasColumn('invoices', 'invoice_account_1')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('invoice_account_1')->nullable()->after('invoice_shared');
            });
        }

        if (!Schema::hasColumn('invoices', 'invoice_account_1_comm')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('invoice_account_1_comm')->nullable()->after('invoice_account_1');
            });
        }

        if (!Schema::hasColumn('invoices', 'invoice_account_2')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('invoice_account_2')->nullable()->after('invoice_account_1_comm');
            });
        }

        if (!Schema::hasColumn('invoices', 'invoice_account_2_comm')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('invoice_account_2_comm')->nullable()->after('invoice_account_2');
            });
        }

        if (!Schema::hasColumn('invoices', 'crt_at')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->text('crt_at')->nullable()->after('invoice_account_2_comm');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'crt_at')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('crt_at');
            });
        }

        if (Schema::hasColumn('invoices', 'invoice_account_2_comm')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('invoice_account_2_comm');
            });
        }

        if (Schema::hasColumn('invoices', 'invoice_account_2')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('invoice_account_2');
            });
        }

        if (Schema::hasColumn('invoices', 'invoice_account_1_comm')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('invoice_account_1_comm');
            });
        }

        if (Schema::hasColumn('invoices', 'invoice_account_1')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('invoice_account_1');
            });
        }

        if (Schema::hasColumn('invoices', 'invoice_shared')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('invoice_shared');
            });
        }

        if (Schema::hasColumn('invoices', 'invoice_money_pay')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('invoice_money_pay');
            });
        }

        if (Schema::hasColumn('invoices', 'invoice_approved_by')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('invoice_approved_by');
            });
        }

        if (Schema::hasColumn('invoices', 'invoice_create_by')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('invoice_create_by');
            });
        }

        if (Schema::hasColumn('invoices', 'invoice_ticket_file')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('invoice_ticket_file');
            });
        }

        if (Schema::hasColumn('invoices', 'to_location')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('to_location');
            });
        }

        if (Schema::hasColumn('invoices', 'from_location')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('from_location');
            });
        }

        if (Schema::hasColumn('invoices', 'invoice_airline')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('invoice_airline');
            });
        }

        if (Schema::hasColumn('invoices', 'return_date')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('return_date');
            });
        }

        if (Schema::hasColumn('invoices', 'invoice_travel_date')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('invoice_travel_date');
            });
        }

        if (Schema::hasColumn('invoices', 'ticket_system_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('ticket_system_id');
            });
        }
    }
};
