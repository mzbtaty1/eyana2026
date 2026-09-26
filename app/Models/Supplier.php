<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
"name",
"phone_1",
"phone_2",
"type",
"passport_id",
"passport_expiration_date", 
"email",
"address",
"debit_opening_balance",
"opening_credit_balance",
"status",
"acc_type",
"limit_balance",
"in_index",
"in_stat",
    ];

    /**
     * Why this account must not be deleted: one Arabic reason per kind of
     * financial / invoice / voucher activity it has; empty when deletion is safe.
     *
     * Blocks on: its own ledger rows (account statement; storage rows share
     * supp_client_id with storage ids, so is_storage rows are not its own),
     * except a zero opening-balance row; invoices where it is the customer;
     * invoices where it is the supplier (ticket_vendors linked to an invoice);
     * payment / receipt vouchers to or from it.
     */
    public function deletionBlockers(): array
    {
        $id = (int) $this->id;
        $out = [];

        $ledger = AccountStatement::where('supp_client_id', $id)
            ->where('is_storage', '!=', 1)
            ->where(function ($q) {
                $q->where('es_id', '!=', 'FLY-OPEN-BALANCE')
                    ->orWhere('debit_balance', '!=', 0)
                    ->orWhere('credit_balance', '!=', 0);
            })
            ->count();
        if ($ledger) {
            $out[] = "$ledger حركة في كشف الحساب";
        }

        $asCustomer = Invoice::where('invoice_beneficiaries', $id)->count();
        if ($asCustomer) {
            $out[] = "$asCustomer فاتورة كعميل";
        }

        $asVendor = TicketVendor::where('vendor_id', $id)
            ->whereIn('ticket_system_id', Invoice::select('ticket_system_id'))
            ->count();
        if ($asVendor) {
            $out[] = "$asVendor فاتورة كمورد";
        }

        $bonds = Bond::where(function ($q) use ($id) {
            $q->where(fn ($b) => $b->where('from_type', 'supplier')->where('from_account', $id))
                ->orWhere(fn ($b) => $b->where('to_type', 'supplier')->where('to_account', $id));
        })->count();
        if ($bonds) {
            $out[] = "$bonds سند قبض/دفع";
        }

        return $out;
    }
}
