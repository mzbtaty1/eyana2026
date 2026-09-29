<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Internal tourism: a transport service's route (from / to), on booking items and on
     * program items (a program's transport carries its route into the bookings made from it).
     * Two nullable columns per table; nothing existing is changed or back-filled.
     */
    public function up(): void
    {
        foreach (['tourism_booking_items' => 'room_type', 'tourism_program_items' => 'room_type'] as $table => $after) {
            if (!Schema::hasColumn($table, 'route_from')) {
                Schema::table($table, function (Blueprint $t) use ($after) {
                    $t->string('route_from', 255)->nullable()->after($after);
                    $t->string('route_to', 255)->nullable()->after('route_from');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['tourism_booking_items', 'tourism_program_items'] as $table) {
            if (Schema::hasColumn($table, 'route_from')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn(['route_from', 'route_to']);
                });
            }
        }
    }
};
