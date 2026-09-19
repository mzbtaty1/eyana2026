<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * P9 fresh-reproducibility fix: invoices.es_id and invoices.invoice_status
     * were added to Dev outside any migration (schema drift), but
     * add_indexes_to_invoices_table indexes both. On a truly fresh database
     * neither column would exist yet. Recreates them at their live Dev types.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'es_id')) {
                $table->text('es_id')->nullable();
            }

            if (!Schema::hasColumn('invoices', 'invoice_status')) {
                $table->integer('invoice_status')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'es_id')) {
                $table->dropColumn('es_id');
            }

            if (Schema::hasColumn('invoices', 'invoice_status')) {
                $table->dropColumn('invoice_status');
            }
        });
    }
};
