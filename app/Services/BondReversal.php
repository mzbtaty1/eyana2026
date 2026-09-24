<?php

namespace App\Services;

use App\Models\{AccountStatement, Bank, BankStatement, Bond, Storage, StorageStatement};
use Illuminate\Support\Facades\Auth;

/**
 * Reverse a voucher («سند»): restore the treasury (and, for bank vouchers, the bank)
 * balance, void the voucher's active treasury / bank ledger entry and append its
 * exact reversal (StorageStatement / BankStatement::reverseActiveEntryForBond --
 * those immutable ledgers are never deleted from), then remove the voucher and its
 * own account-statement rows.
 *
 * Used by the voucher delete screen (BondsController::delete) and by the deletion
 * of Counter Customer invoices (CounterInvoiceDeletion).
 *
 * The caller MUST run it inside a DB transaction; storage and bank rows are locked
 * here in the canonical order storage -> bank.
 */
class BondReversal
{
    /**
     * Why this voucher can't be reversed exactly (Arabic), or null.
     * reverse() itself is lenient (as the voucher screen always was); callers that
     * must not leave anything half-reversed check this first.
     */
    public static function problem(Bond $bond): ?string
    {
        $label = $bond->es_id ?: 'رقم ' . $bond->id;
        $storageId = (int) $bond->type === 1 ? $bond->from_account : $bond->to_account;

        if (!Storage::where('id', $storageId)->exists()) {
            return "الخزنة الخاصة بالسند $label غير موجودة";
        }
        if (!StorageStatement::where('bond_id', $bond->id)->where('entry_type', 'bond')->where('is_voided', false)->exists()) {
            return "لا يوجد قيد خزنة نشط للسند $label (سند سابق لدفتر الخزنة أو تم عكسه من قبل)";
        }
        if ((int) $bond->money_way === 2) {
            if (!$bond->bank_id || !Bank::where('id', $bond->bank_id)->exists()) {
                return "البنك الخاص بالسند $label غير موجود";
            }
            if (!BankStatement::where('bond_id', $bond->id)->where('entry_type', 'bond')->where('is_voided', false)->exists()) {
                return "لا يوجد قيد بنك نشط للسند $label (سند سابق لدفتر البنك أو تم عكسه من قبل)";
            }
        }
        return null;
    }

    /** $bond must already be locked (lockForUpdate) by the caller's transaction. */
    public static function reverse(Bond $bond, string $description): void
    {
        $id = (int) $bond->id;

        if ($bond->type == 1) {
            $storage_id = $bond->from_account;

            $amount = $bond->amount;
            $commission = $bond->commission ?? 0;
            $total = (float) $amount + (float) $commission;

            $storage_info = Storage::where('id', $storage_id)->lockForUpdate()->first();
            abort_if(!$storage_info, 404);

            Storage::where('id', $storage_id)->update([
                "balance" => $storage_info->balance + $total,
            ]);

            // P3 storage ledger: void the bond's active storage entry and
            // append its exact reversal, never delete the original. Runs
            // unconditionally -- every bond touches Storage regardless of
            // money_way, unlike the bank ledger reversal below.
            StorageStatement::reverseActiveEntryForBond($id, $description, Auth::user()->id);

            if ($bond->money_way == 2 && $bond->bank_id) {
                $bank_info = Bank::where('id', $bond->bank_id)->lockForUpdate()->first();
                if ($bank_info) {
                    Bank::where('id', $bond->bank_id)->update([
                        "bank_balance" => $bank_info->bank_balance + $total,
                    ]);

                    // P2 bank ledger: void the bond's active ledger entry and
                    // append its exact reversal, never delete the original.
                    BankStatement::reverseActiveEntryForBond($id, $description, Auth::user()->id);
                }
            }
        } else {
            $storage_id = $bond->to_account;

            $amount = $bond->amount;

            $storage_info = Storage::where('id', $storage_id)->lockForUpdate()->first();
            abort_if(!$storage_info, 404);

            Storage::where('id', $storage_id)->update([
                "balance" => $storage_info->balance - $amount,
            ]);

            // P3 storage ledger: void the bond's active storage entry and
            // append its exact reversal, never delete the original.
            StorageStatement::reverseActiveEntryForBond($id, $description, Auth::user()->id);

            // P2 bug fix: receipt bonds (type==2) with money_way==2 credited
            // banks.bank_balance by $amount at creation, so reverse exactly
            // $amount -- receipt bonds never carry a commission.
            if ($bond->money_way == 2 && $bond->bank_id) {
                $bank_info = Bank::where('id', $bond->bank_id)->lockForUpdate()->first();
                if ($bank_info) {
                    Bank::where('id', $bond->bank_id)->update([
                        "bank_balance" => $bank_info->bank_balance - $amount,
                    ]);

                    BankStatement::reverseActiveEntryForBond($id, $description, Auth::user()->id);
                }
            }
        }

        Bond::where('id', $id)->delete();
        // A voucher's own statement rows carry its number. Invoice-payment receipts have
        // no number (their rows carry the invoice number and go with the invoice), and
        // an empty number must never be used as a filter.
        if ((string) $bond->es_id !== '') {
            AccountStatement::where('es_id', $bond->es_id)->delete();
        }
    }
}
