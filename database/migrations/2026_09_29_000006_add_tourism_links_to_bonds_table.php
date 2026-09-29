<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Links a voucher to an internal tourism booking (customer receipt / refund, supplier
     * payment) and optionally to one of its services. Two nullable columns only: every
     * existing voucher stays NULL, nothing is back-filled. A linked voucher is never edited
     * or deleted from /bonds; it is reversed with BondReversal::offset().
     */
    public function up(): void
    {
        if (!Schema::hasColumn('bonds', 'tourism_booking_id')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->foreignId('tourism_booking_id')->nullable()->after('invoice_id')
                    ->constrained('tourism_bookings')->restrictOnDelete();
            });
        }
        if (!Schema::hasColumn('bonds', 'tourism_booking_item_id')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->foreignId('tourism_booking_item_id')->nullable()->after('tourism_booking_id')
                    ->constrained('tourism_booking_items')->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('bonds', 'tourism_booking_item_id')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->dropConstrainedForeignId('tourism_booking_item_id');
            });
        }
        if (Schema::hasColumn('bonds', 'tourism_booking_id')) {
            Schema::table('bonds', function (Blueprint $table) {
                $table->dropConstrainedForeignId('tourism_booking_id');
            });
        }
    }
};
