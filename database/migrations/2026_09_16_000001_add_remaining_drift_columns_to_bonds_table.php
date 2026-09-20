<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Batch E: bonds schema drift catch-up. These 12 columns exist on live
     * Dev but were never captured by a tracked migration. The application
     * (BondsController and related views/reports) reads and writes them at
     * runtime, so a fresh database build would be missing columns the app
     * requires. Recreates each at its live Dev type/nullability/default,
     * in live column order.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('bonds', 'system_id')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->text('system_id')->after('es_id');
            });
        }

        if (!Schema::hasColumn('bonds', 'sub_id')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->string('sub_id')->nullable()->after('system_id');
            });
        }

        if (!Schema::hasColumn('bonds', 'from_type')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->text('from_type')->after('from_account');
            });
        }

        if (!Schema::hasColumn('bonds', 'to_type')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->text('to_type')->after('to_account');
            });
        }

        if (!Schema::hasColumn('bonds', 'money_way')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->integer('money_way')->after('info');
            });
        }

        if (!Schema::hasColumn('bonds', 'collector_info')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->string('collector_info')->nullable()->after('commission');
            });
        }

        if (!Schema::hasColumn('bonds', 'crt_date')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->text('crt_date')->after('collector_info');
            });
        }

        if (!Schema::hasColumn('bonds', 'file_path')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->text('file_path')->nullable()->after('crt_date');
            });
        }

        if (!Schema::hasColumn('bonds', 'bond_status')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->integer('bond_status')->default(0)->after('file_path');
            });
        }

        if (!Schema::hasColumn('bonds', 'is_invoice')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->string('is_invoice')->nullable()->default('0')->after('bond_status');
            });
        }

        if (!Schema::hasColumn('bonds', 'invoice_id')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->string('invoice_id')->nullable()->default('0')->after('is_invoice');
            });
        }

        if (!Schema::hasColumn('bonds', 'created_by')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->text('created_by')->nullable()->after('invoice_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('bonds', 'created_by')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->dropColumn('created_by');
            });
        }

        if (Schema::hasColumn('bonds', 'invoice_id')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->dropColumn('invoice_id');
            });
        }

        if (Schema::hasColumn('bonds', 'is_invoice')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->dropColumn('is_invoice');
            });
        }

        if (Schema::hasColumn('bonds', 'bond_status')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->dropColumn('bond_status');
            });
        }

        if (Schema::hasColumn('bonds', 'file_path')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->dropColumn('file_path');
            });
        }

        if (Schema::hasColumn('bonds', 'crt_date')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->dropColumn('crt_date');
            });
        }

        if (Schema::hasColumn('bonds', 'collector_info')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->dropColumn('collector_info');
            });
        }

        if (Schema::hasColumn('bonds', 'money_way')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->dropColumn('money_way');
            });
        }

        if (Schema::hasColumn('bonds', 'to_type')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->dropColumn('to_type');
            });
        }

        if (Schema::hasColumn('bonds', 'from_type')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->dropColumn('from_type');
            });
        }

        if (Schema::hasColumn('bonds', 'sub_id')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->dropColumn('sub_id');
            });
        }

        if (Schema::hasColumn('bonds', 'system_id')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->dropColumn('system_id');
            });
        }
    }
};
