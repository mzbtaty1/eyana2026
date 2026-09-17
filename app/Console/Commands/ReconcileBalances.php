<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileBalances extends Command
{
    protected $signature = 'accounting:reconcile {--limit=50 : Max mismatch rows to show per section}';

    protected $description = 'Read-only report comparing stored balances against the account_statements ledger. Reports drift only -- never modifies data.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $this->info('P1 Accounting Reconciliation Report (read-only, no data modified)');
        $this->newLine();

        $this->reconcileStorages($limit);
        $this->newLine();
        $this->reconcileInvoicePayments($limit);
        $this->newLine();
        $this->reportNonNumericValues($limit);
        $this->newLine();
        $this->warn('Note: Bank.bank_balance has no corresponding account_statements ledger entries anywhere in the codebase (bond bank movements are not logged per-bank), so it cannot be reconciled by this command.');

        return 0;
    }

    protected function reconcileStorages(int $limit): void
    {
        $this->line('== Storage balance reconciliation ==');

        $storages = DB::table('storages')->get();
        $mismatches = [];

        foreach ($storages as $storage) {
            $computed = DB::table('account_statements')
                ->where('is_storage', 1)
                ->where('supp_client_id', $storage->id)
                ->sum(DB::raw('CAST(cumulative_balance AS DECIMAL(14,2))'));

            $stored = (float) $storage->balance;
            $diff = round($stored - (float) $computed, 2);

            if (abs($diff) > 0.01) {
                $mismatches[] = [
                    $storage->id,
                    $storage->name,
                    number_format($stored, 2),
                    number_format((float) $computed, 2),
                    number_format($diff, 2),
                ];
            }
        }

        if (empty($mismatches)) {
            $this->info('No mismatches found across ' . $storages->count() . ' storage(s).');
            return;
        }

        $this->error(count($mismatches) . ' storage(s) with a mismatch between stored balance and ledger sum:');
        $this->table(['Storage ID', 'Name', 'Stored balance', 'Ledger sum (cumulative_balance)', 'Diff'], array_slice($mismatches, 0, $limit));
    }

    protected function reconcileInvoicePayments(int $limit): void
    {
        $this->line('== Invoice payment reconciliation (invoice_money_pay vs ledger) ==');

        $invoices = DB::table('invoices')->where('invoice_money_pay', '>', 0)->get();
        $mismatches = [];

        foreach ($invoices as $invoice) {
            $computed = DB::table('account_statements')
                ->where('es_id', $invoice->es_id)
                ->where('transaction_type', 4)
                ->where('is_storage', 1)
                ->sum(DB::raw('CAST(debit_balance AS DECIMAL(14,2))'));

            $stored = (float) $invoice->invoice_money_pay;
            $diff = round($stored - (float) $computed, 2);

            if (abs($diff) > 0.01) {
                $mismatches[] = [
                    $invoice->id,
                    $invoice->es_id,
                    number_format($stored, 2),
                    number_format((float) $computed, 2),
                    number_format($diff, 2),
                ];
            }
        }

        if (empty($mismatches)) {
            $this->info('No mismatches found across ' . $invoices->count() . ' paid invoice(s).');
            return;
        }

        $this->error(count($mismatches) . ' invoice(s) with a mismatch between invoice_money_pay and ledger sum:');
        $this->table(['Invoice ID', 'es_id', 'invoice_money_pay', 'Ledger sum (payments)', 'Diff'], array_slice($mismatches, 0, $limit));
    }

    protected function reportNonNumericValues(int $limit): void
    {
        $this->line('== Non-numeric monetary values ==');

        $checks = [
            ['account_statements', 'debit_balance'],
            ['account_statements', 'credit_balance'],
            ['account_statements', 'cumulative_balance'],
            ['storages', 'balance'],
            ['banks', 'bank_balance'],
        ];

        $found = false;

        foreach ($checks as [$table, $column]) {
            $bad = [];

            DB::table($table)->select('id', $column)->orderBy('id')
                ->chunk(1000, function ($rows) use ($column, &$bad, $limit) {
                    foreach ($rows as $row) {
                        $value = $row->$column;
                        if ($value !== null && !is_numeric($value)) {
                            if (count($bad) < $limit) {
                                $bad[] = [$row->id, $value];
                            }
                        }
                    }
                });

            if (!empty($bad)) {
                $found = true;
                $this->error("{$table}.{$column}: non-numeric value(s) found (showing up to {$limit}):");
                $this->table(['id', $column], $bad);
            }
        }

        if (!$found) {
            $this->info('No non-numeric monetary values found.');
        }
    }
}
