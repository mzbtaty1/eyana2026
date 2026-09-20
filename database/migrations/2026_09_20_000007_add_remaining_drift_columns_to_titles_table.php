<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Titles schema drift catch-up. These 3 columns exist on live Dev but
     * the tracked create_titles_table migration only creates `id` +
     * `title` + timestamps. The application (Title model,
     * Frontend\MarketingController's title create/edit flows and
     * usage-count increment/decrement logic, and the
     * marketing_prices/titles listing views) reads and writes these
     * columns at runtime, so a fresh database build would be missing
     * columns the app requires. Recreates each at its live Dev
     * type/default, in live column order.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('titles', 'png_icon')) {
            Schema::table('titles', function (Blueprint $table) {
                $table->text('png_icon')->after('title');
            });
        }

        if (!Schema::hasColumn('titles', 'bk_color')) {
            Schema::table('titles', function (Blueprint $table) {
                $table->text('bk_color')->after('png_icon');
            });
        }

        if (!Schema::hasColumn('titles', 'usage_count')) {
            Schema::table('titles', function (Blueprint $table) {
                $table->integer('usage_count')->default(0)->after('bk_color');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('titles', 'usage_count')) {
            Schema::table('titles', function (Blueprint $table) {
                $table->dropColumn('usage_count');
            });
        }

        if (Schema::hasColumn('titles', 'bk_color')) {
            Schema::table('titles', function (Blueprint $table) {
                $table->dropColumn('bk_color');
            });
        }

        if (Schema::hasColumn('titles', 'png_icon')) {
            Schema::table('titles', function (Blueprint $table) {
                $table->dropColumn('png_icon');
            });
        }
    }
};
