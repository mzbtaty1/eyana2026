<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Marketing_prices schema drift catch-up. `places_available` exists on
     * live Dev but is missing from the tracked create_marketing_prices_table
     * migration. The application (MarketingPrice model and
     * Frontend\MarketingController's create flow, plus the
     * marketing_prices.all2 listing view) reads and writes this column at
     * runtime, so a fresh database build would be missing a column the app
     * requires. Recreates it at its live Dev type/default, after
     * `added_by` to match live column order.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('marketing_prices', 'places_available')) {
            Schema::table('marketing_prices', function (Blueprint $table) {
                $table->integer('places_available')->default(0)->after('added_by');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('marketing_prices', 'places_available')) {
            Schema::table('marketing_prices', function (Blueprint $table) {
                $table->dropColumn('places_available');
            });
        }
    }
};
