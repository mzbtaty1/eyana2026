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
        Schema::create('sub_storages', function (Blueprint $table) {
            $table->id();
            $table->text("main_storage");
            $table->text("name");
            $table->text("type"); // 1 = Cash - 2 = Bank
            $table->text("bank_id")->nullable();
            $table->text("bank_number")->nullable();
            $table->text("balance")->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_storages');
    }
};
