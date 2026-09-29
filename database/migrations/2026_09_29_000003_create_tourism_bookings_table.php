<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Internal tourism bookings («حجوزات السياحة الداخلية»): the booking document, TRB-00001.
     * The customer is an existing account (suppliers row; the Counter Customer included).
     * created_by is the owner / sales employee (commission) and is never changed by an edit.
     * status: draft (no ledger rows) | confirmed | cancelled | completed.
     * Amounts live on the items; the ledger (account_statements, es_id = booking_no) is the
     * accounting record.
     */
    public function up(): void
    {
        if (!Schema::hasTable('tourism_bookings')) {
            Schema::create('tourism_bookings', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->string('booking_no', 20)->nullable()->unique();
                $table->foreignId('program_id')->nullable()->constrained('tourism_programs')->restrictOnDelete();
                $table->foreignId('customer_id')->constrained('suppliers')->restrictOnDelete();
                $table->string('contact_name', 255)->nullable();
                $table->string('contact_phone', 50)->nullable();
                $table->unsignedSmallInteger('adults')->default(1);
                $table->unsignedSmallInteger('children')->default(0);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('status', 12)->default('draft');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('confirmed_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancel_reason', 500)->nullable();
                $table->timestamps();
                $table->index(['status', 'start_date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tourism_bookings');
    }
};
