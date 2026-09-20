<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * account_statements schema drift catch-up. These 11 columns exist on
     * live Dev but were never captured by a tracked migration. The
     * application (AccountStatement model, InvoiceController,
     * Frontend\InvoicesController, Frontend\SharedInvoices,
     * Frontend\MoneyArea\BondsController, Frontend\SuppliersController,
     * Frontend\AccountsController) reads and writes them at runtime, so a
     * fresh database build would be missing columns the app requires.
     * Recreates each at its live Dev type/nullability/default, in live
     * column order. transaction_type's TEXT-vs-INT type mismatch is a
     * separate, already-tracked column and is intentionally left untouched
     * here for a future type-conversion migration.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('account_statements', 'trans_storage')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->integer('trans_storage')->default(0)->after('invoice_type');
            });
        }

        if (!Schema::hasColumn('account_statements', 'is_storage')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->integer('is_storage')->default(0)->after('trans_storage');
            });
        }

        if (!Schema::hasColumn('account_statements', 'is_supp_account')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->integer('is_supp_account')->default(0)->after('is_storage');
            });
        }

        if (!Schema::hasColumn('account_statements', 'sub_id')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->string('sub_id')->default('0')->after('is_supp_account');
            });
        }

        if (!Schema::hasColumn('account_statements', 'balance_on_transaction')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->string('balance_on_transaction')->default('0')->after('ledger_net_effect');
            });
        }

        if (!Schema::hasColumn('account_statements', 'transaction_txt')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->text('transaction_txt')->after('balance_on_transaction');
            });
        }

        if (!Schema::hasColumn('account_statements', 'description')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->text('description')->nullable()->after('transaction_type');
            });
        }

        if (!Schema::hasColumn('account_statements', 'cumulative_balance')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->string('cumulative_balance')->default('0')->after('description');
            });
        }

        if (!Schema::hasColumn('account_statements', 'crt_date')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->text('crt_date')->nullable()->after('cumulative_balance');
            });
        }

        if (!Schema::hasColumn('account_statements', 'added_by')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->text('added_by')->nullable()->after('crt_date');
            });
        }

        if (!Schema::hasColumn('account_statements', 'transaction_approved')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->integer('transaction_approved')->default(0)->after('added_by');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('account_statements', 'transaction_approved')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->dropColumn('transaction_approved');
            });
        }

        if (Schema::hasColumn('account_statements', 'added_by')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->dropColumn('added_by');
            });
        }

        if (Schema::hasColumn('account_statements', 'crt_date')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->dropColumn('crt_date');
            });
        }

        if (Schema::hasColumn('account_statements', 'cumulative_balance')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->dropColumn('cumulative_balance');
            });
        }

        if (Schema::hasColumn('account_statements', 'description')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }

        if (Schema::hasColumn('account_statements', 'transaction_txt')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->dropColumn('transaction_txt');
            });
        }

        if (Schema::hasColumn('account_statements', 'balance_on_transaction')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->dropColumn('balance_on_transaction');
            });
        }

        if (Schema::hasColumn('account_statements', 'sub_id')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->dropColumn('sub_id');
            });
        }

        if (Schema::hasColumn('account_statements', 'is_supp_account')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->dropColumn('is_supp_account');
            });
        }

        if (Schema::hasColumn('account_statements', 'is_storage')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->dropColumn('is_storage');
            });
        }

        if (Schema::hasColumn('account_statements', 'trans_storage')) {
            Schema::table('account_statements', function (Blueprint $table) {
                $table->dropColumn('trans_storage');
            });
        }
    }
};
