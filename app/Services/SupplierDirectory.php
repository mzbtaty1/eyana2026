<?php

namespace App\Services;

use App\Models\{AccountStatement, Supplier};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rows of the «الموردين و العملاء» list (/suppliers): every account except expense
 * accounts (acc_type 3 -- they have their own page), with what it is and where it
 * stands. A fixed number of grouped queries, whatever the number of accounts.
 *
 *   role           'supplier' -- supplier on at least one invoice (ticket_vendors linked
 *                  to an invoice); 'customer' -- customer (invoice_beneficiaries) on at
 *                  least one invoice; 'both'; null -- no invoices
 *   balance        debit - credit of the account's statement rows (is_storage != 1), all
 *                  time: the account statement's closing balance and the balances report
 *                  figure. > 0 لنا (the account owes us), < 0 علينا (we owe the account)
 *   last_activity  latest crt_date (the statement's accounting date) of those rows,
 *                  ignoring a zero opening-balance row; null when there is none
 *   phones         phone_1 / phone_2 without empty or all-zero placeholders ("0", "0000000")
 */
class SupplierDirectory
{
    public static function rows(): Collection
    {
        $accounts = Supplier::where('acc_type', '!=', 3)->orderBy('id', 'DESC')->get();
        $ids = $accounts->pluck('id')->all();

        $totals = AccountStatement::balancesByAccount($ids);

        $lastActivity = AccountStatement::whereIn('supp_client_id', $ids)
            ->where('is_storage', '!=', 1)
            ->where(function ($q) {
                $q->where('es_id', '!=', 'FLY-OPEN-BALANCE')
                    ->orWhere('debit_balance', '!=', 0)
                    ->orWhere('credit_balance', '!=', 0);
            })
            ->selectRaw('supp_client_id, MAX(crt_date) as last_date')
            ->groupBy('supp_client_id')
            ->pluck('last_date', 'supp_client_id');

        $asSupplier = DB::table('ticket_vendors as tv')
            ->join('invoices as i', 'i.ticket_system_id', '=', 'tv.ticket_system_id')
            ->whereIn('tv.vendor_id', $ids)
            ->selectRaw('tv.vendor_id as account_id, COUNT(DISTINCT i.id) as n')
            ->groupBy('tv.vendor_id')
            ->pluck('n', 'account_id');

        $asCustomer = DB::table('invoices')
            ->whereIn('invoice_beneficiaries', $ids)
            ->selectRaw('invoice_beneficiaries as account_id, COUNT(*) as n')
            ->groupBy('invoice_beneficiaries')
            ->pluck('n', 'account_id');

        return $accounts->map(function ($a) use ($totals, $lastActivity, $asSupplier, $asCustomer) {
            $t = $totals->get($a->id);
            $asSup = (int) ($asSupplier[$a->id] ?? 0);
            $asCus = (int) ($asCustomer[$a->id] ?? 0);
            $balance = round(($t->total_debit ?? 0) - ($t->total_credit ?? 0), 2);

            return (object) [
                'account' => $a,
                'phones' => array_values(array_filter([self::phone($a->phone_1), self::phone($a->phone_2)])),
                'role' => $asSup && $asCus ? 'both' : ($asSup ? 'supplier' : ($asCus ? 'customer' : null)),
                'invoices_as_supplier' => $asSup,
                'invoices_as_customer' => $asCus,
                'balance' => $balance == 0 ? 0.0 : $balance, // no "-0"
                'last_activity' => $lastActivity[$a->id] ?? null,
            ];
        });
    }

    /** Number of expense accounts left out of the list (they are on the expenses page). */
    public static function expenseAccountsCount(): int
    {
        return Supplier::where('acc_type', 3)->count();
    }

    /** A phone worth showing: not empty and not an all-zero placeholder. */
    public static function phone($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' || preg_match('/^0+$/', $value) ? null : $value;
    }
}
