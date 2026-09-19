<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * تشغيل الترحيل لإضافة فهارس لتحسين الأداء
     *
     * P8 schema-drift fix: this migration originally referenced
     * invoices.client_name, a column that does not exist on this table
     * (the real customer-link column is invoice_beneficiaries) -- running
     * this migration as originally written would fail outright. That
     * index has been dropped rather than guessing a replacement column.
     * The remaining indexes are now guarded against the 8 indexes that
     * already exist live on `invoices` under different, undocumented
     * names (idx_es_id, idx_invoice_status, idx_created_at,
     * idx_created_status, etc.), added outside any migration at some
     * point before this engagement, so this migration is now safe to run
     * without erroring or creating true duplicates.
     */
    public function up()
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!$this->leadingIndexExists('invoices', 'es_id')) {
                $table->index('es_id');
            }

            if (!$this->leadingIndexExists('invoices', 'invoice_status')) {
                $table->index('invoice_status');
            }

            if (!$this->leadingIndexExists('invoices', 'created_at')) {
                $table->index('created_at');
            }

            if (!$this->compositeIndexExists('invoices', ['invoice_status', 'created_at'])) {
                $table->index(['invoice_status', 'created_at']);
            }
        });
    }

    /**
     * التراجع عن الترحيل
     *
     * Guarded to only drop indexes this migration itself could have
     * created (by Laravel's default naming convention) -- never touches
     * the pre-existing, differently-named indexes already on the table.
     */
    public function down()
    {
        Schema::table('invoices', function (Blueprint $table) {
            foreach ([
                'invoices_es_id_index',
                'invoices_invoice_status_index',
                'invoices_created_at_index',
                'invoices_invoice_status_created_at_index',
            ] as $indexName) {
                if ($this->indexExistsByName('invoices', $indexName)) {
                    $table->dropIndex($indexName);
                }
            }
        });
    }

    private function leadingIndexExists(string $table, string $column): bool
    {
        return count(DB::select(
            "SHOW INDEX FROM {$table} WHERE Column_name = ? AND Seq_in_index = 1",
            [$column]
        )) > 0;
    }

    private function compositeIndexExists(string $table, array $columns): bool
    {
        $rows = DB::select("SHOW INDEX FROM {$table}");
        $byIndex = [];
        foreach ($rows as $row) {
            $byIndex[$row->Key_name][$row->Seq_in_index] = $row->Column_name;
        }
        foreach ($byIndex as $cols) {
            ksort($cols);
            if (array_values($cols) === $columns) {
                return true;
            }
        }
        return false;
    }

    private function indexExistsByName(string $table, string $indexName): bool
    {
        return count(DB::select(
            "SHOW INDEX FROM {$table} WHERE Key_name = ?",
            [$indexName]
        )) > 0;
    }
};
