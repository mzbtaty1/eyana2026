<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Full-table schema drift catch-up. The `visas` table exists on live
     * Dev (backing the fully-wired VisaController CRUD routes) but was
     * never captured by any migration. Recreates it at its live Dev
     * column set. Live Dev uses MyISAM (evidence it was created outside
     * Laravel); this migration deliberately uses InnoDB instead, per
     * explicit approval, for consistency with every other table in this
     * schema.
     */
    public function up(): void
    {
        if (!Schema::hasTable('visas')) {
            Schema::create('visas', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->text('visa_name');
                $table->text('visa_price');
                $table->text('visa_ext_price');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visas');
    }
};
