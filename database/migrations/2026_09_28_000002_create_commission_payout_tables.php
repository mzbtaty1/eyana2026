<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Employee commission payouts (Employees step E). Two new tables only; no existing table or
     * row is touched and nothing is back-filled (payouts start 2026-09-01). InnoDB, so the
     * foreign keys hold and the payout writes are transactional.
     *
     *   commission_periods: one employee + period (from / to, unique), the Step D commission
     *       and its calculation snapshot taken when the period was opened, status open / closed
     *       (closed only when fully paid)
     *   commission_payouts: the payments of a period (several = partial), each through its own
     *       payment voucher (bonds row); a reversal keeps the row and marks it reversed
     */
    public function up(): void
    {
        if (!Schema::hasTable('commission_periods')) {
            Schema::create('commission_periods', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->foreignId('employee_id')->constrained('users')->restrictOnDelete();
                $table->date('period_from');
                $table->date('period_to');
                $table->decimal('commission_amount', 14, 2);
                $table->json('calculation');
                $table->string('status', 10)->default('open');
                $table->timestamp('closed_at')->nullable();
                $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
                $table->unique(['employee_id', 'period_from', 'period_to']);   // also the employee's periods index
            });
        }

        if (!Schema::hasTable('commission_payouts')) {
            Schema::create('commission_payouts', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->foreignId('period_id')->constrained('commission_periods')->restrictOnDelete();
                $table->decimal('amount', 14, 2);
                $table->date('paid_date');
                $table->foreignId('bond_id')->constrained('bonds')->restrictOnDelete();
                $table->foreignId('storage_id')->constrained('storages')->restrictOnDelete();
                $table->tinyInteger('money_way');
                $table->foreignId('bank_id')->nullable()->constrained('banks')->restrictOnDelete();
                $table->string('reference', 255)->nullable();
                $table->string('request_token', 64)->unique();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('reversed_at')->nullable();
                $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->string('reversal_bond_note', 255)->nullable();
                $table->timestamps();
                $table->index(['period_id', 'reversed_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_payouts');
        Schema::dropIfExists('commission_periods');
    }
};
