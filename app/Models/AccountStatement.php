<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountStatement extends Model
{
    use HasFactory;
    
    
    protected $fillable = [

"supp_client_id",
"trans_storage",
"is_storage",
"is_supp_account",
"sub_id",
"invoice_type",
"es_id",
"invoice_date",
"debit_balance",
"credit_balance",
"ledger_net_effect",
"balance_on_transaction",
"transaction_txt",
"transaction_type",
"added_by",
"crt_date",
"transaction_approved",
        
    ];

    /**
     * Net balance (debit - credit) carried forward from every accounting
     * transaction on this account/report dated before $date_from. Opening
     * balances are themselves booked as ordinary dated AccountStatement rows
     * (es_id "FLY-OPEN-BALANCE"), so this is a plain historical sum -- no
     * separate addition of Supplier::opening_credit_balance/debit_opening_balance,
     * which would double-count that same booked row. Shared by the Account
     * Statement screen, Print Preview and Excel export so all three agree.
     */
    public static function openingBalanceBefore($search_status, $invoice_beneficiaries, $date_from, $transaction_type = null)
    {
        $q = static::where('is_storage', '!=', 1)->where('crt_date', '<', $date_from);

        if ($search_status == 0) {
            $q = $q->where('is_supp_account', '!=', 1);
        } else {
            $q = $q->where('supp_client_id', $invoice_beneficiaries);
        }

        if (isset($transaction_type)) {
            $q = $q->where('transaction_type', $transaction_type);
        }

        $totals = $q->selectRaw('SUM(debit_balance) as d, SUM(credit_balance) as c')->first();

        return ($totals->d ?? 0) - ($totals->c ?? 0);
    }
    
}
