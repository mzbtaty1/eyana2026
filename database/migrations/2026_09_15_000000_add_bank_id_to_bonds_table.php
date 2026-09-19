<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * P9 fresh-reproducibility fix: bonds.bank_id was added to Dev
     * outside any migration (schema drift), but add_commission_to_bonds_table
     * references it via ->after('bank_id') and later migrations index/harden
     * it. On a truly fresh database none of those would find the column.
     * Recreates it at its original, pre-hardening type (VARCHAR(255) NULL) --
     * harden_bonds_bank_id already converts it to BIGINT UNSIGNED + FK later
     * in the sequence, so this preserves the real historical type evolution.
     */
    public function up(): void
    {
        if (Schema::hasColumn('bonds', 'bank_id')) {
            return;
        }

        Schema::table('bonds', function (Blueprint $table) {
            $table->string('bank_id', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('bonds', 'bank_id')) {
            return;
        }

        Schema::table('bonds', function (Blueprint $table) {
            $table->dropColumn('bank_id');
        });
    }
};
