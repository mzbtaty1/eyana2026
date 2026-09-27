<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Commission tier tables (Employees step B): reusable tables of profit ranges and
     * commission rates, managed by the admin («شرائح العمولات»). Two new tables only;
     * no existing table or row is touched. InnoDB (the server default is MyISAM) so the
     * tiers keep a foreign key to their table and writes can be transactional.
     *
     *   commission_tier_tables: name, status (1 = active, 0 = inactive)
     *   commission_tiers: table_id, from_amount, to_amount (NULL = no upper limit),
     *       rate (%), sort_order -- amounts decimal(14,2) like the ledger amounts
     */
    public function up(): void
    {
        if (!Schema::hasTable('commission_tier_tables')) {
            Schema::create('commission_tier_tables', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->string('name');
                $table->tinyInteger('status')->default(1);
                $table->timestamps();
                $table->unique('name');
            });
        }

        if (!Schema::hasTable('commission_tiers')) {
            Schema::create('commission_tiers', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->foreignId('table_id')->constrained('commission_tier_tables')->cascadeOnDelete();
                $table->decimal('from_amount', 14, 2);
                $table->decimal('to_amount', 14, 2)->nullable();
                $table->decimal('rate', 5, 2);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['table_id', 'sort_order']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_tiers');
        Schema::dropIfExists('commission_tier_tables');
    }
};
