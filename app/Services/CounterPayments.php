<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Counter Customer ("عميل كونتر") invoice payments.
 *
 * Counter customers are the accounts listed in config('eyana.counter_customer_ids').
 * Only their invoices carry a payment status and can take payments (screen
 * "سداد الفاتورة", InvoicesController::pay_part_save). Payments reuse the
 * existing infrastructure: a receipt Bond per payment, the customer / treasury
 * ledger rows, the treasury statement and -- for bank payments -- the bank
 * balance and bank statement, exactly like a bank receipt voucher.
 *
 *   total     = the invoice's current sale total (sum of its passengers)
 *   paid      = invoices.invoice_money_pay (sum of its payments)
 *   refunded  = client refunds («مسترد للعميل») of refund invoices made from it
 *   paid_out  = refund payouts to the client linked to the invoice (payment vouchers
 *               «سند دفع» with invoice_id, created from the invoice's payment screen)
 *   remaining = total - paid - refunded + paid_out   (negative = «مستحق للعميل»)
 */
class CounterPayments
{
    public static function counterIds(): array
    {
        return array_map('intval', (array) config('eyana.counter_customer_ids', []));
    }

    public static function isCounterClient($clientId): bool
    {
        return in_array((int) $clientId, self::counterIds(), true);
    }

    /** Payments are taken on counter-customer sales / re-issues (not on refund invoices). */
    public static function isPayable($invoice): bool
    {
        return self::isCounterClient($invoice->invoice_beneficiaries)
            && !str_starts_with((string) $invoice->es_id, 'FLY-RD');
    }

    /**
     * Payment summary of many invoices at once (a fixed number of queries,
     * whatever the number of invoices). Only counter-customer invoices are
     * summarised; others are omitted. Returns id => summary.
     */
    public static function summaries(Collection $invoices): array
    {
        $invoices = $invoices->filter(fn ($i) => self::isPayable($i))->keyBy('id');
        if ($invoices->isEmpty()) {
            return [];
        }
        $ids = $invoices->keys()->map(fn ($id) => (int) $id)->all();

        $totals = DB::table('ticket_users')->whereIn('ticket_system_id', $invoices->pluck('ticket_system_id')->filter()->unique()->values())
            ->groupBy('ticket_system_id')->selectRaw('ticket_system_id, SUM(client_bought_price) t')->pluck('t', 'ticket_system_id');

        $bySource = self::refundSources($ids);
        $refunded = array_fill_keys($ids, 0.0);
        if ($bySource) {
            $refundInvoices = DB::table('invoices')->whereIn('es_id', array_keys($bySource))->get(['es_id', 'invoice_beneficiaries'])->keyBy('es_id');
            $rows = DB::table('account_statements')->whereIn('es_id', array_keys($bySource))->where('transaction_type', 1)
                ->where('is_storage', '!=', 1)->get(['es_id', 'supp_client_id', 'debit_balance', 'credit_balance']);
            foreach ($rows as $r) {
                $client = $refundInvoices[$r->es_id]->invoice_beneficiaries ?? null;
                if ((string) $r->supp_client_id === (string) $client) {
                    $refunded[$bySource[$r->es_id]] += (float) $r->credit_balance - (float) $r->debit_balance;
                }
            }
        }

        $paidOut = DB::table('bonds')->where('type', 1)->where('is_invoice', 1)->whereIn('invoice_id', $ids)
            ->groupBy('invoice_id')->selectRaw('invoice_id, SUM(amount) s')->pluck('s', 'invoice_id');

        $out = [];
        foreach ($invoices as $id => $inv) {
            $total = round((float) ($totals[$inv->ticket_system_id] ?? 0), 2);
            $paid = round((float) ($inv->invoice_money_pay ?? 0), 2);
            $ref = round($refunded[(int) $id] ?? 0, 2);
            $out_ = round((float) ($paidOut[$id] ?? 0), 2);
            $remaining = round($total - $paid - $ref + $out_, 2);
            if ($remaining < -0.005) {
                $status = 'due_to_client'; $label = 'مستحق للعميل';
            } elseif ($remaining <= 0.005 && $out_ > 0.005) {
                // a refund due to the client has been paid back to them
                $status = 'settled'; $label = 'تمت التسوية مع العميل';
            } elseif ($remaining <= 0.005) {
                $status = $paid > 0 ? 'paid' : 'nothing_due';
                $label = $paid > 0 ? 'تم السداد' : 'لا يوجد مستحق';
            } elseif ($paid <= 0.005) {
                $status = 'unpaid'; $label = 'لم يتم السداد';
            } else {
                $status = 'partial'; $label = 'سداد جزئي';
            }
            $out[$id] = ['total' => $total, 'paid' => $paid, 'refunded' => $ref, 'paid_out' => $out_, 'remaining' => $remaining,
                         'due_to_client' => $remaining < -0.005 ? -$remaining : 0.0, 'status' => $status, 'label' => $label];
        }
        return $out;
    }

    /**
     * Refund invoices made from the given invoices: es_id => source invoice id.
     * New refunds record their source in the refund marker; older refunds used
     * the numbering "FLY-RD<source id>".
     */
    public static function refundSources(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $bySource = [];
        foreach (DB::table('account_statements')->where('transaction_type', 1)
                     ->whereRaw("JSON_VALUE(description, '$.kind') = 'refund'")
                     ->whereIn(DB::raw("JSON_VALUE(description, '$.source_invoice')"), array_map('strval', $ids))
                     ->distinct()->get(['es_id', DB::raw("JSON_VALUE(description, '$.source_invoice') AS src")]) as $r) {
            $bySource[$r->es_id] = (int) $r->src;
        }
        foreach (DB::table('invoices')->whereIn('es_id', array_map(fn ($id) => 'FLY-RD' . $id, $ids))->get(['id', 'es_id']) as $r) {
            $src = (int) substr($r->es_id, 6);
            if ($src !== (int) $r->id && !isset($bySource[$r->es_id])) {
                $bySource[$r->es_id] = $src;
            }
        }
        return $bySource;
    }

    public static function summary($invoice): ?array
    {
        return self::summaries(collect([$invoice]))[$invoice->id] ?? null;
    }
}
