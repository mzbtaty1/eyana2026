<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The people of a booking (family / group members) for the passenger and rooming lists.
     */
    public function up(): void
    {
        if (!Schema::hasTable('tourism_booking_passengers')) {
            Schema::create('tourism_booking_passengers', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->foreignId('booking_id')->constrained('tourism_bookings')->cascadeOnDelete();
                $table->string('name', 255);
                $table->string('type', 10)->default('adult');   // adult | child
                $table->unsignedTinyInteger('age')->nullable();
                $table->string('id_number', 50)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('room_ref', 50)->nullable();
                $table->string('notes', 500)->nullable();
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tourism_booking_passengers');
    }
};
