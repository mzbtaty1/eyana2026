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
        Schema::create('bank_statements', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('bank_id')->constrained('banks')->restrictOnDelete();
            $table->foreignId('bond_id')->nullable()->constrained('bonds')->nullOnDelete();
            $table->string('entry_type', 20); // opening | bond | reversal | adjustment
            $table->date('transaction_date');
            $table->string('description', 500);
            $table->string('reference', 64)->nullable();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->decimal('commission', 12, 2)->default(0);
            $table->decimal('net_effect', 14, 2);
            $table->decimal('running_balance', 14, 2);
            $table->foreignId('reversal_of_entry_id')->nullable()
                ->constrained('bank_statements')->nullOnDelete();
            $table->boolean('is_voided')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['bank_id', 'transaction_date', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_statements');
    }
};
