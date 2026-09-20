<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Suppliers schema drift catch-up. These 6 columns exist on live Dev
     * but were never captured by a tracked migration. The application
     * (Supplier model, Frontend\SuppliersController, Frontend\AccountsController,
     * Frontend\IndexController) reads and writes them at runtime, so a
     * fresh database build would be missing columns the app requires.
     * Recreates each at its live Dev type/nullability/default, in live
     * column order.
     *
     * acc_type is NOT NULL with no default on live Dev. This is safe to
     * reproduce as-is: on Dev the hasColumn guard below skips this column
     * entirely since it already exists (367 populated rows are never
     * touched), and on a fresh database this migration runs while
     * `suppliers` is still empty, so a NOT NULL column with no default
     * has no existing rows to violate.
     *
     * The 7 separate type/nullability/default mismatches on already-tracked
     * columns (phone_2, passport_id, passport_expiration_date, email,
     * address, debit_opening_balance, opening_credit_balance) are
     * intentionally left untouched here, per audit findings -- they are a
     * distinct drift class for a future migration.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('suppliers', 'status')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->integer('status')->default(1)->after('opening_credit_balance');
            });
        }

        if (!Schema::hasColumn('suppliers', 'acc_type')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->integer('acc_type')->after('status');
            });
        }

        if (!Schema::hasColumn('suppliers', 'cumulative_balance')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->string('cumulative_balance')->default('0')->after('acc_type');
            });
        }

        if (!Schema::hasColumn('suppliers', 'limit_balance')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->string('limit_balance')->default('0')->after('cumulative_balance');
            });
        }

        if (!Schema::hasColumn('suppliers', 'in_index')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->integer('in_index')->default(0)->after('limit_balance');
            });
        }

        if (!Schema::hasColumn('suppliers', 'in_stat')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->integer('in_stat')->default(1)->after('in_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('suppliers', 'in_stat')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn('in_stat');
            });
        }

        if (Schema::hasColumn('suppliers', 'in_index')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn('in_index');
            });
        }

        if (Schema::hasColumn('suppliers', 'limit_balance')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn('limit_balance');
            });
        }

        if (Schema::hasColumn('suppliers', 'cumulative_balance')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn('cumulative_balance');
            });
        }

        if (Schema::hasColumn('suppliers', 'acc_type')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn('acc_type');
            });
        }

        if (Schema::hasColumn('suppliers', 'status')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
