<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Users schema drift catch-up. These 2 columns exist on live Dev but
     * were never captured by a tracked migration. The application (User
     * model, Frontend\UserController, and the account_type authorization
     * gate used throughout InvoicesController, IndexController,
     * SharedInvoices, ProfitsController, and the main nav layouts) reads
     * and writes them at runtime, so a fresh database build would be
     * missing columns the app requires. Recreates each at its live Dev
     * type/nullability/default, in live column order.
     *
     * Both columns are NOT NULL with no default on live Dev. This is safe
     * to reproduce as-is: on Dev the hasColumn guards below skip both
     * columns entirely since they already exist (18 populated rows are
     * never touched), and on a fresh database this migration runs while
     * `users` is still empty, so NOT NULL columns with no default have no
     * existing rows to violate.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'account_type')) {
            Schema::table('users', function (Blueprint $table) {
                $table->integer('account_type')->after('status');
            });
        }

        if (!Schema::hasColumn('users', 'commission')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('commission')->after('account_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'commission')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('commission');
            });
        }

        if (Schema::hasColumn('users', 'account_type')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('account_type');
            });
        }
    }
};
