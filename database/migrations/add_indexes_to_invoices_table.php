<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل الترحيل لإضافة فهارس لتحسين الأداء
     */
    public function up()
    {
        Schema::table('invoices', function (Blueprint $table) {
            // فهرس للبحث السريع في رقم الفاتورة
            $table->index('es_id');
            
            // فهرس للبحث في اسم العميل
            $table->index('client_name');
            
            // فهرس لحالة الفاتورة
            $table->index('invoice_status');
            
            // فهرس للتاريخ
            $table->index('created_at');
            
            // فهرس مركب للبحث والفلترة
            $table->index(['invoice_status', 'created_at']);
        });
    }

    /**
     * التراجع عن الترحيل
     */
    public function down()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['es_id']);
            $table->dropIndex(['client_name']);
            $table->dropIndex(['invoice_status']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['invoice_status', 'created_at']);
        });
    }
};