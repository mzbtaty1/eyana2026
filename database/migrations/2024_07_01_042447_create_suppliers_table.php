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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->text("name");
            $table->text("phone_1");
            $table->text("phone_2");
            $table->text("type"); // 1 = individual | 2 = Company
            $table->text("passport_id");
            $table->text("passport_expiration_date");
            $table->text("email");
            $table->text("address");
            $table->text("debit_opening_balance");
            $table->text("opening_credit_balance");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
