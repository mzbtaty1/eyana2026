<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // P7: bonds.bank_id was VARCHAR(255) with no FK. Verified via a
        // full-table audit that all 4,787 bonds (not just money_way=2) have
        // a bank_id that is either NULL or a clean positive integer
        // matching a real banks.id -- zero orphans, zero invalid values --
        // so this is a lossless type change with no pre-cleanup needed.
        DB::statement("ALTER TABLE bonds MODIFY bank_id BIGINT UNSIGNED NULL DEFAULT NULL");

        // Reuses the existing bonds_bank_id_index (added in the earlier
        // index migration) to satisfy the FK's indexing requirement.
        // RESTRICT matches the same choice already made for
        // bank_statements.bank_id and storage_statements.storage_id: a
        // bank must not be deletable while any bond (even an inert
        // cash/collector one) still references it.
        Schema::table('bonds', function (Blueprint $table) {
            $table->foreign('bank_id', 'bonds_bank_id_foreign')
                ->references('id')->on('banks')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bonds', function (Blueprint $table) {
            $table->dropForeign('bonds_bank_id_foreign');
        });
        DB::statement("ALTER TABLE bonds MODIFY bank_id VARCHAR(255) NULL DEFAULT NULL");
    }
};
