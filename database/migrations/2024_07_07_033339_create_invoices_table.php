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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->text("invoice_date");
            $table->text("invoice_group_id")->nullable();
            $table->text("invoice_beneficiaries")->nullable();
            $table->text("invoice_section");
            $table->text("invoice_comments");
            $table->text("invoice_currency");
            $table->text("invoice_draft");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
