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
        if (Schema::hasTable('bonds')) {
            return;
        }

        Schema::create('bonds', function (Blueprint $table) {
            $table->id();
            $table->text("type"); 
            /*
            TYPE : 
            -1- : دفع
            -2- : قبض
            */
            $table->text("es_id"); 
            $table->text("from_account"); 
            $table->text("to_account"); 
            $table->text("amount"); 
            $table->text("info")->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonds');
    }
};
