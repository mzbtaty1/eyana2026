<?php

namespace App\Services;

use App\Models\{AccountStatement, Bank, BankStatement, Bond, Storage, StorageStatement, Supplier};
use Illuminate\Support\Facades\Auth;

/**
 * A receipt voucher («سند قبض», type 2): money from an account (suppliers row) into a
 * treasury (and, for money_way 2, a bank / e-wallet). Extracted unchanged from the voucher
 * screen (BondsController::save) so the internal tourism customer receipts
 * (TourismPayments) book exactly the same entries:
 *
 *   treasury balance += amount; bank balance too for money_way 2 (when the bank exists)
 *   bonds row FLY-BD<id>
 *   account_statements: treasury row (debit amount) and account row (credit amount)
 *   storage_statements: one credit entry; bank_statements: one credit entry for money_way 2
 *   (receipt vouchers never carry a bank fee)
 *
 * The caller MUST run it inside a DB transaction (storage -> bank rows are locked here, after
 * anything the caller locked first, e.g. a booking).
 */
class ReceiptBond
{
    /**
     * $p: storage_id, sub_id, supp_id, amount, money_way, bank_id, collector_info, info, date,
     * file_path, txt_suffix (optional, appended to the statement text; '' on the voucher screen).
     */
    public static function create(array $p): Bond
    {
        $storage_id = $p['storage_id'];
        $sub_id = $p['sub_id'];
        $supp_id = $p['supp_id'];
        $amount = $p['amount'];
        $money_way = $p['money_way'];
        $bank_id = $p['bank_id'];
        $date = $p['date'];
        $suffix = $p['txt_suffix'] ?? '';

        $storage_info = Storage::where('id',$storage_id)->lockForUpdate()->first();
        abort_if(!$storage_info, 404);

        Storage::where('id',$storage_id)->update([
            "balance" => $storage_info->balance + $amount,
        ]);

        $bank = null;
        if ($money_way == 2) {
            $bank = Bank::where('id', $bank_id)->lockForUpdate()->first();

            if ($bank) { // التأكد من أن البنك موجود
                $bank->increment('bank_balance', $amount);
            }
        }

        $createBond = Bond::create([
           "file_path" => $p['file_path'],
           "type" => 2,
           "system_id" => \Str::random(8),
            "sub_id" => $sub_id,
           "from_account" => $supp_id,
           "from_type" => "supplier",
           "to_account" => $storage_id,
           "to_type" => "storage",
           "amount" => $amount,
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

        $supplier = Supplier::where('id',$supp_id)->first();
        abort_if(!$supplier, 404);

        AccountStatement::create([
            "supp_client_id" => $storage_id,
            "is_storage" => 1,
            "trans_storage" => 1,
            "invoice_type" => 10,
            "sub_id" => $sub_id,
            "es_id" => "FLY-BD" . $createBond->id,
            "invoice_date" => $date,
            "debit_balance" => $amount,
            "credit_balance" => 0,
            "ledger_net_effect" => $amount,
            "transaction_txt" => "سند قبض من حساب $supplier->name لصالح خزينة $storage_info->name" . $suffix,
            "transaction_type" => 2,
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
        ]);

        AccountStatement::create([
            "supp_client_id" => $supp_id,
            "trans_storage" => 1,
            "invoice_type" => 10,
            "sub_id" => $sub_id,
            "es_id" => "FLY-BD" . $createBond->id,
            "invoice_date" => $date,
            "debit_balance" => 0,
            "credit_balance" => $amount,
            "ledger_net_effect" => -$amount,
            "transaction_txt" => "سند قبض من حساب $supplier->name لصالح خزينة $storage_info->name" . $suffix,
            "transaction_type" => 2,
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
        ]);

        // P3 storage ledger: one credit entry per receipt bond -- every
        // receipt bond touches Storage regardless of money_way. Receipt
        // bonds never carry a commission, so credit = amount.
        // (reference: the in-memory voucher's es_id, as before)
        StorageStatement::record([
            'storage_id' => (int) $storage_id,
            'bond_id' => $createBond->id,
            'entry_type' => 'bond',
            'transaction_date' => $date,
            'description' => "سند قبض رقم FLY-BD{$createBond->id} لصالح خزينة {$storage_info->name} من حساب $supplier->name",
            'reference' => $createBond->es_id,
            'debit' => 0,
            'credit' => $amount,
            'commission' => 0,
            'created_by' => Auth::user()->id,
        ]);

        // P2 bank ledger: one credit entry per receipt bond that actually
        // moved money into a bank/e-wallet. Receipt bonds never carry a
        // commission (unchanged from existing behavior), so credit = amount.
        if ($money_way == 2 && $bank) {
            BankStatement::record([
                'bank_id' => (int) $bank_id,
                'bond_id' => $createBond->id,
                'entry_type' => 'bond',
                'transaction_date' => $date,
                'description' => "سند قبض رقم FLY-BD{$createBond->id} لصالح بنك {$bank->bank_name} من حساب $supplier->name",
                'reference' => $createBond->es_id,
                'debit' => 0,
                'credit' => $amount,
                'commission' => 0,
                'created_by' => Auth::user()->id,
            ]);
        }

        return Bond::findOrFail($createBond->id);
    }
}
