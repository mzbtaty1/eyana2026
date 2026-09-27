<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Visa types can be disabled instead of deleted once used (Visa invoicing).
     * Adds visas.status TINYINT NOT NULL DEFAULT 1 (1 = active, 0 = disabled):
     * existing rows become active; no other table or row is touched.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('visas', 'status')) {
            Schema::table('visas', function (Blueprint $table) {
                $table->tinyInteger('status')->default(1)->after('visa_ext_price');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('visas', 'status')) {
            Schema::table('visas', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
