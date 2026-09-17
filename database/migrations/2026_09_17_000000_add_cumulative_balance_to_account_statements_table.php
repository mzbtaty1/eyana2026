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
        Schema::table('account_statements', function (Blueprint $table) {
            $table->decimal('cumulative_balance', 14, 2)->nullable()->after('credit_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_statements', function (Blueprint $table) {
            $table->dropColumn('cumulative_balance');
        });
    }
};