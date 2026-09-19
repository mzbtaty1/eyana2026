<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bonds', function (Blueprint $table) {
            $table->index('bank_id', 'bonds_bank_id_index');
            $table->rawIndex('es_id(191)', 'bonds_es_id_index');
        });

        Schema::table('account_statements', function (Blueprint $table) {
            $table->rawIndex('es_id(191)', 'account_statements_es_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bonds', function (Blueprint $table) {
            $table->dropIndex('bonds_bank_id_index');
            $table->dropIndex('bonds_es_id_index');
        });

        Schema::table('account_statements', function (Blueprint $table) {
            $table->dropIndex('account_statements_es_id_index');
        });
    }
};
