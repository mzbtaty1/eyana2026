<?php

namespace App\Services;

use App\Models\{AccountStatement, Bond, Invoice, Log as AppLog};
use Illuminate\Support\Facades\{Auth, DB};

/**
 * Deleting a Counter Customer invoice (config eyana.counter_customer_ids).
 *
 * Every voucher linked to the invoice (bonds.invoice_id: its payments «سداد» and
 * its refund payouts «رد مبلغ للعميل») is reversed first with BondReversal --
 * treasury and bank through their immutable void + reversal entries -- then the
 * invoice and its account-statement rows (sale, edits and payment rows, all under
 * the invoice number) are removed as for any invoice. All in ONE transaction: a
 * failure anywhere rolls everything back.
 *
 * Deletion is refused, with nothing changed, when a linked movement can't be
 * reversed exactly:
 *  - a refund invoice made from this invoice still exists,
 *  - the amount paid on the invoice doesn't match its payment vouchers,
 *  - a voucher has no active treasury / bank entry to reverse (e.g. older than
 *    those ledgers) or its treasury / bank no longer exists.
 *
 * Any other (non-counter) invoice is never deleted while vouchers are still
 * linked to it, or once an amount was paid on it (nonCounterBlocker): nothing
 * reverses them there.
 */
class CounterInvoiceDeletion
{
    public static function applies($invoice): bool
    {
        return CounterPayments::isCounterClient($invoice->invoice_beneficiaries);
    }

    /** Vouchers linked to the invoice, oldest first. */
    public static function linkedBonds($invoice)
    {
        return Bond::where('invoice_id', (int) $invoice->id)->orderBy('id')->get();
    }

    /**
     * Non-counter invoices: why the invoice can't be deleted because vouchers are
     * still linked to it (Arabic), or null. Deleting it would leave them orphaned.
     */
    public static function linkedVouchersBlocker($invoice): ?string
    {
        $bonds = self::linkedBonds($invoice);
        if ($bonds->isEmpty()) {
            return null;
        }
        $list = $bonds->map(fn ($b) => ($b->es_id ?: 'رقم ' . $b->id) . ' (' . number_format((float) $b->amount, 2) . ')')->implode('، ');
        return "لا يمكن حذف الفاتورة {$invoice->es_id} لأن عليها سندات مرتبطة بها: $list. برجاء عكس / تصحيح هذه السندات أولاً.";
    }

    /**
     * Non-counter invoices: why deletion is refused (Arabic), or null -- vouchers
     * still linked to it, or an amount already paid on it (the P1.2 rule of the
     * JSON delete endpoint, InvoiceController::deleteInvoice).
     */
    public static function nonCounterBlocker($invoice): ?string
    {
        if ($blocker = self::linkedVouchersBlocker($invoice)) {
            return $blocker;
        }
        if ((float) ($invoice->invoice_money_pay ?? 0) > 0) {
            return 'لا يمكن حذف فاتورة تم سداد مبلغ عليها. برجاء عمل مرتجع / استرجاع أولاً.';
        }
        return null;
    }

    /** Why the invoice can't be deleted now (Arabic), or null. */
    public static function blocker($invoice, $bonds): ?string
    {
        $es = $invoice->es_id;

        $refunds = array_keys(CounterPayments::refundSources([(int) $invoice->id]));
        if ($refunds) {
            return "لا يمكن حذف الفاتورة $es لأن عليها مرتجع (" . implode('، ', $refunds) . "). برجاء حذف أو تصحيح المرتجع أولاً ثم حذف الفاتورة.";
        }

        $paid = round((float) $invoice->invoice_money_pay, 2);
        $receipts = round((float) $bonds->where('type', 2)->sum('amount'), 2);
        if (abs($paid - $receipts) > 0.005) {
            return "لا يمكن حذف الفاتورة $es لأن المسدد عليها (" . number_format($paid, 2) . ") لا يطابق سندات السداد المرتبطة بها (" . number_format($receipts, 2) . "). برجاء تصحيح السداد أولاً.";
        }

        foreach ($bonds as $bond) {
            if ($problem = BondReversal::problem($bond)) {
                return "لا يمكن حذف الفاتورة $es لأن عليها حركات مالية مرتبطة لا يمكن عكسها تلقائياً: $problem. برجاء عكس / تصحيح الحركة أولاً.";
            }
        }
        return null;
    }

    /**
     * Returns null on success, or the Arabic reason the invoice was not deleted
     * (nothing changed). Exceptions roll the whole transaction back and propagate.
     * $withTickets: also delete the invoice's passengers / vendor rows (as the
     * JSON delete endpoint does for every invoice).
     */
    public static function delete(int $invoiceId, bool $withTickets = false): ?string
    {
        return DB::transaction(function () use ($invoiceId, $withTickets) {
            // lock order: invoice -> vouchers -> storage -> bank (as payments / payouts)
            $invoice = Invoice::where('id', $invoiceId)->lockForUpdate()->first();
            if (!$invoice) {
                return 'الفاتورة غير موجودة';
            }
            $bonds = Bond::where('invoice_id', (int) $invoice->id)->orderBy('id')->lockForUpdate()->get();

            if ($error = self::blocker($invoice, $bonds)) {
                return $error;
            }

            foreach ($bonds as $bond) {
                $what = (int) $bond->type === 2 ? 'سداد' : 'رد مبلغ للعميل';
                BondReversal::reverse($bond, "عكس سند $what " . ($bond->es_id ?: 'رقم ' . $bond->id) . " بسبب حذف فاتورة {$invoice->es_id}");
            }

            if ($withTickets && $invoice->ticket_system_id) {
                DB::table('ticket_users')->where('ticket_system_id', $invoice->ticket_system_id)->delete();
                DB::table('ticket_vendors')->where('ticket_system_id', $invoice->ticket_system_id)->delete();
            }
            Invoice::where('id', $invoice->id)->delete();
            AccountStatement::where('es_id', $invoice->es_id)->delete();

            AppLog::create([
                "log_txt" => "تم حذف فاتورة عميل كونتر {$invoice->es_id}" . ($bonds->isNotEmpty() ? " مع عكس " . $bonds->count() . " سند مرتبط" : ""),
                "log_ip" => request()->ip(),
                "log_by" => Auth::user()->id,
                "log_date" => date('Y-m-d'),
            ]);
            return null;
        });
    }
}
