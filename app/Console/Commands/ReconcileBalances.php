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
        $this->reconcileBanks($limit);
        $this->newLine();
        $this->reportNonNumericValues($limit);

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
                ->sum(DB::raw('CAST(ledger_net_effect AS DECIMAL(14,2))'));

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
        $this->table(['Storage ID', 'Name', 'Stored balance', 'Ledger sum (ledger_net_effect)', 'Diff'], array_slice($mismatches, 0, $limit));
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

    protected function reconcileBanks(int $limit): void
    {
        $this->line('== Bank balance reconciliation (bank_statements ledger) ==');

        $banks = DB::table('banks')->get();
        $mismatches = [];
        $noLedgerYet = [];

        foreach ($banks as $bank) {
            $computed = DB::table('bank_statements')
                ->where('bank_id', $bank->id)
                ->orderByDesc('id')
                ->value('running_balance');

            if ($computed === null) {
                // No ledger entries yet for this bank -- expected until its
                // opening-balance cutover entry has been recorded (P2 bank
                // ledger design, step 12: cutover is a separate, explicit,
                // not-yet-performed step). Not a mismatch, just not
                // reconcilable yet.
                $noLedgerYet[] = [$bank->id, $bank->bank_name, number_format((float) $bank->bank_balance, 2)];
                continue;
            }

            $stored = (float) $bank->bank_balance;
            $diff = round($stored - (float) $computed, 2);

            if (abs($diff) > 0.01) {
                $mismatches[] = [
                    $bank->id,
                    $bank->bank_name,
                    number_format($stored, 2),
                    number_format((float) $computed, 2),
                    number_format($diff, 2),
                ];
            }
        }

        if (!empty($noLedgerYet)) {
            $this->warn(count($noLedgerYet) . ' bank(s) have no bank_statements entries yet (pending opening-balance cutover) and were skipped:');
            $this->table(['Bank ID', 'Name', 'Current bank_balance'], array_slice($noLedgerYet, 0, $limit));
        }

        if (empty($mismatches)) {
            $this->info('No mismatches found among banks with ledger entries.');
            return;
        }

        $this->error(count($mismatches) . ' bank(s) with a mismatch between stored balance and ledger running balance:');
        $this->table(['Bank ID', 'Name', 'Stored bank_balance', 'Ledger running_balance', 'Diff'], array_slice($mismatches, 0, $limit));
    }

    protected function reportNonNumericValues(int $limit): void
    {
        $this->line('== Non-numeric monetary values ==');

        $checks = [
            ['account_statements', 'debit_balance'],
            ['account_statements', 'credit_balance'],
            ['account_statements', 'ledger_net_effect'],
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
