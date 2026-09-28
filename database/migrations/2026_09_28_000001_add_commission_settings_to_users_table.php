<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Employee commission settings (Employees step C): how each employee's commission is
     * configured. Two new users columns only -- users.commission (the fixed %) and every
     * existing row are left as they are; existing users get fixed / NULL from the defaults.
     *
     *   commission_method: 'fixed' (users.commission %) or 'tiered' (an assigned tier table)
     *   commission_tier_table_id: the assigned commission_tier_tables row (NULL when fixed);
     *       restrict on delete -- a table assigned to an employee is never deleted, only
     *       deactivated (users and commission_tier_tables are both InnoDB)
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'commission_method')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('commission_method', 10)->default('fixed')->after('commission');
            });
        }

        if (!Schema::hasColumn('users', 'commission_tier_table_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('commission_tier_table_id')->nullable()->after('commission_method')
                    ->constrained('commission_tier_tables')->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'commission_tier_table_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('commission_tier_table_id');
            });
        }
        if (Schema::hasColumn('users', 'commission_method')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('commission_method');
            });
        }
    }
};
