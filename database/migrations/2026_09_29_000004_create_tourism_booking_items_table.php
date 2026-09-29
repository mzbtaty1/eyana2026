<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The services of a booking, each with its own supplier (an existing account). Totals are
     * calculated on the server (hotel / village: rooms x nights x unit price per room-night;
     * other services: quantity x unit). A cancelled service stays (status cancelled) with the
     * penalty kept: cancel_cost (still owed to the supplier) and cancel_fee (still charged
     * to the customer). source_program_item_id only traces a copy from a program.
     */
    public function up(): void
    {
        if (!Schema::hasTable('tourism_booking_items')) {
            Schema::create('tourism_booking_items', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->foreignId('booking_id')->constrained('tourism_bookings')->cascadeOnDelete();
                $table->unsignedBigInteger('source_program_item_id')->nullable();
                $table->string('service_type', 20);
                $table->string('description', 500);
                $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->unsignedSmallInteger('nights')->nullable();
                $table->unsignedSmallInteger('rooms')->nullable();
                $table->string('room_type', 100)->nullable();
                $table->unsignedSmallInteger('adults')->nullable();
                $table->unsignedSmallInteger('children')->nullable();
                $table->decimal('quantity', 10, 2)->default(1);
                $table->decimal('unit_cost', 14, 2)->default(0);
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->decimal('total_cost', 14, 2)->default(0);
                $table->decimal('total_sale', 14, 2)->default(0);
                $table->string('status', 10)->default('active');   // active | cancelled
                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->decimal('cancel_cost', 14, 2)->default(0);
                $table->decimal('cancel_fee', 14, 2)->default(0);
                $table->text('notes')->nullable();
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamps();
                $table->index(['service_type', 'start_date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tourism_booking_items');
    }
};
