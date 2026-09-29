<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The services of a tourism program (hotel / transport / activity / ...), each with an
     * optional default supplier (an existing account, suppliers row), default cost and price.
     * pricing: per_unit (quantity as entered) or per_person (quantity = the booking's people).
     */
    public function up(): void
    {
        if (!Schema::hasTable('tourism_program_items')) {
            Schema::create('tourism_program_items', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->foreignId('program_id')->constrained('tourism_programs')->cascadeOnDelete();
                $table->string('service_type', 20);
                $table->string('description', 500);
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
                $table->unsignedSmallInteger('day_no')->nullable();
                $table->unsignedSmallInteger('nights')->nullable();
                $table->unsignedSmallInteger('rooms')->nullable();
                $table->string('room_type', 100)->nullable();
                $table->string('pricing', 12)->default('per_unit');
                $table->decimal('quantity', 10, 2)->default(1);
                $table->decimal('unit_cost', 14, 2)->default(0);
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->text('notes')->nullable();
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamps();
                $table->index(['program_id', 'sort']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tourism_program_items');
    }
};
