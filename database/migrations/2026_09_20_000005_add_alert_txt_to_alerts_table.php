<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Alerts schema drift catch-up. `alert_txt` exists on live Dev but the
     * tracked create_alerts_table migration only creates `id` + timestamps.
     * The application (Alert model, Frontend\AlertsController's create/list/
     * delete flows, and the main dashboard's management-notification
     * banner in resources/views/index.blade.php) reads and writes this
     * column at runtime, so a fresh database build would be missing a
     * column the app requires. Recreates it at its live Dev type, after
     * `id` to match live column order.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('alerts', 'alert_txt')) {
            Schema::table('alerts', function (Blueprint $table) {
                $table->text('alert_txt')->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('alerts', 'alert_txt')) {
            Schema::table('alerts', function (Blueprint $table) {
                $table->dropColumn('alert_txt');
            });
        }
    }
};
