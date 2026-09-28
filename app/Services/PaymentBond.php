<?php

namespace App\Services;

use App\Models\{AccountStatement, Bank, BankStatement, Bond, Invoice, Storage, StorageStatement, Supplier};
use Illuminate\Support\Facades\Auth;

/**
 * A payment voucher («سند دفع», type 1): money out of a treasury (and, for money_way 2,
 * a bank / e-wallet) to an account (suppliers row -- a supplier, customer or expense account).
 * Extracted unchanged from the voucher screen (BondsController::save) so the commission
 * payouts (CommissionPayouts) book exactly the same entries:
 *
 *   treasury balance -= amount + commission (bank fee); bank balance too for money_way 2
 *   bonds row FLY-BD<id>
 *   account_statements: treasury row (credit amount + fee) and account row (debit amount)
 *   storage_statements: one debit entry; bank_statements: one debit entry for money_way 2
 *
 * The caller MUST run it inside a DB transaction (storage -> bank rows are locked here, after
 * anything the caller locked first, e.g. a linked invoice).
 */
class PaymentBond
{
    /**
     * $p: storage_id, sub_id, supp_id, amount, commission (bank fee), money_way, bank_id,
     * collector_info, info, date, file_path, txt_suffix (appended to the statement text).
     * $linkInvoice: a Counter Customer invoice this voucher settles (is_invoice / invoice_id).
     */
    public static function create(array $p, ?Invoice $linkInvoice = null): Bond
    {
        $storage_id = $p['storage_id'];
        $sub_id = $p['sub_id'];
        $supp_id = $p['supp_id'];
        $amount = $p['amount'];
        $commission = $p['commission'];
        $money_way = $p['money_way'];
        $bank_id = $p['bank_id'];
        $date = $p['date'];
        $total = (float) $amount + $commission;
        $suffix = $p['txt_suffix'] ?? '';

        $storage_info = Storage::where('id',$storage_id)->lockForUpdate()->first();
        abort_if(!$storage_info, 404);

        Storage::where('id',$storage_id)->update([
            "balance" => $storage_info->balance - $total,
        ]);

        $bank_info = null;
        if($money_way == 2){
            $bank_info = Bank::where('id',$bank_id)->lockForUpdate()->first();

            Bank::where('id',$bank_id)->update([
            "bank_balance" => $bank_info->bank_balance - $total,
        ]);
        }

       $createBond = Bond::create([
           "file_path" => $p['file_path'],
           "type" => 1,
           "system_id" => \Str::random(8),
           "sub_id" => $sub_id,
           "from_account" => $storage_id,
           "from_type" => "storage",
           "to_account" => $supp_id,
           "to_type" => "supplier",
           "amount" => $amount,
           "commission" => $commission,
           "info" => $p['info'],
           "money_way" => $money_way,
           "bank_id" => $bank_id,
           "collector_info" => $p['collector_info'],
           "crt_date" => $date,
           "created_by" => Auth::user()->id,
        ]);

        Bond::select('*')
            ->where('id', $createBond->id)
            ->update([
                "es_id" => "FLY-BD" . $createBond->id,
            ]);

        // linked to the invoice -> part of its payment history (not deletable / editable)
        if ($linkInvoice) {
            Bond::where('id', $createBond->id)->update(["is_invoice" => 1, "invoice_id" => $linkInvoice->id]);
        }

        $supplier = Supplier::where('id',$supp_id)->first();
        abort_if(!$supplier, 404);

        AccountStatement::create([
            "supp_client_id" => $storage_id,
            "trans_storage" => 1,
            "is_storage" => 1,
            "invoice_type" => 9,
            "sub_id" => $sub_id,
            "es_id" => "FLY-BD" . $createBond->id,
            "invoice_date" => $date,
            "debit_balance" => 0,
            "credit_balance" => $total,
            "ledger_net_effect" => -$total,
            "transaction_txt" => "سند دفع من خزينة $storage_info->name لصالح $supplier->name" . $suffix,
            "transaction_type" => 2,
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
        ]);

        AccountStatement::create([
            "supp_client_id" => $supp_id,
            "trans_storage" => 1,
            "invoice_type" => 9,
            "sub_id" => $sub_id,
            "es_id" => "FLY-BD" . $createBond->id,
            "invoice_date" => $date,
            "debit_balance" => $amount,
            "credit_balance" => 0,
            "ledger_net_effect" => $amount,
            "transaction_txt" => "سند دفع من خزينة $storage_info->name لصالح $supplier->name" . $suffix,
            "transaction_type" => 2,
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
        ]);

        // P3 storage ledger: one debit entry per payment bond -- every
        // payment bond touches Storage regardless of money_way, unlike the
        // bank ledger which only applies when money_way==2. Debit = amount
        // + commission, matching exactly what was just subtracted from
        // storage balance above. (reference: the in-memory voucher's es_id, as before)
        StorageStatement::record([
            'storage_id' => (int) $storage_id,
            'bond_id' => $createBond->id,
            'entry_type' => 'bond',
            'transaction_date' => $date,
            'description' => "سند دفع رقم FLY-BD{$createBond->id} من خزينة {$storage_info->name} لصالح $supplier->name",
            'reference' => $createBond->es_id,
            'debit' => $total,
            'credit' => 0,
            'commission' => $commission,
            'created_by' => Auth::user()->id,
        ]);

        // P2 bank ledger: one debit entry per payment bond that actually
        // moved money out of a bank/e-wallet. Debit = amount + commission,
        // matching exactly what was just subtracted from bank_balance above.
        if ($money_way == 2) {
            BankStatement::record([
                'bank_id' => (int) $bank_id,
                'bond_id' => $createBond->id,
                'entry_type' => 'bond',
                'transaction_date' => $date,
                'description' => "سند دفع رقم FLY-BD{$createBond->id} من بنك {$bank_info->bank_name} لصالح $supplier->name",
                'reference' => $createBond->es_id,
                'debit' => $total,
                'credit' => 0,
                'commission' => $commission,
                'created_by' => Auth::user()->id,
            ]);
        }

        return Bond::findOrFail($createBond->id);
    }
}
