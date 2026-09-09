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
        Schema::create('marketing_prices', function (Blueprint $table) {
            $table->id();
            $table->text("title");
            $table->text("travel_date");
            $table->text("time_departure");
            $table->text("total_price");
            $table->text("cost_price");
            $table->text("booking_id");
            $table->text("screen_id");
            $table->text("added_by");
            $table->text("hold_finish_time");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_prices');
    }
};
