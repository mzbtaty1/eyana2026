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
        Schema::create('ticket_users', function (Blueprint $table) {
            $table->id();
            $table->text("ticket_system_id");
            $table->text("client_name");
            $table->text("client_net_pice");
            $table->text("client_bought_price");
            $table->text("client_booking_id");
            $table->text("client_ticket_id");
            $table->text("client_passport_id");
   
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_users');
    }
};
