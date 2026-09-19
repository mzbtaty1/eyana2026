<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * P9 fresh-reproducibility fix: banks.bank_balance was added to Dev
     * outside any migration (schema drift). No tracked migration depends
     * on it, but the live application (BankController, BondsController,
     * ReconcileBalances) does, so a fresh database would be missing a
     * column the app requires at runtime. Recreates it at its live Dev
     * type/default/position.
     */
    public function up(): void
    {
        if (Schema::hasColumn('banks', 'bank_balance')) {
            return;
        }

        Schema::table('banks', function (Blueprint $table) {
            $table->string('bank_balance', 255)->default('0')->after('bank_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('banks', 'bank_balance')) {
            return;
        }

        Schema::table('banks', function (Blueprint $table) {
            $table->dropColumn('bank_balance');
        });
    }
};
