<?php

namespace App\Services;

use App\Models\{AccountStatement, Bond, Supplier, TourismBooking, TourismBookingItem};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Money of an internal tourism booking, through the existing vouchers only (bonds linked with
 * bonds.tourism_booking_id, optionally tourism_booking_item_id):
 *
 *   customer receipt  «سند قبض» from the customer   ReceiptBond
 *   customer refund   «سند دفع» to the customer     PaymentBond
 *   supplier payment  «سند دفع» to a supplier       PaymentBond
 *   reversal          BondReversal::offset -- the voucher and its rows are kept, offsetting
 *                     rows are added (nothing deleted)
 *
 * Every figure comes from the account statement: a voucher counts with the net of its own
 * account rows (so a reversed voucher counts 0), the booking's sale / cost / payables from its
 * TRB rows (TourismLedger::totals).
 *
 *   customer paid      = receipts - refunds
 *   customer remaining = sale - customer paid   (negative: due to the customer)
 *   supplier remaining = payable - paid, per supplier
 *
 * Each action runs in one transaction with the booking locked first (then the treasury / bank,
 * inside the voucher services). Errors are Arabic messages (null = done).
 */
class TourismPayments
{
    const KINDS = ['receipt' => 'سند قبض من العميل', 'refund' => 'رد مبلغ للعميل', 'supplier' => 'سند دفع للمورد'];

    public static function summary(TourismBooking $booking): array
    {
        return self::summaries(collect([$booking]))[$booking->id];
    }

    /** summary() of many bookings: booking id => summary, in a fixed number of queries. */
    public static function summaries(Collection $bookings): array
    {
        if ($bookings->isEmpty()) {
            return [];
        }
        $rows = AccountStatement::whereIn('es_id', $bookings->pluck('booking_no'))->where('transaction_type', 1)
            ->orderBy('id')->get()->groupBy('es_id');
        $bonds = Bond::whereIn('tourism_booking_id', $bookings->pluck('id'))->orderBy('id')->get();

        // each voucher's effective amount: the net of its account rows (receipt credit / payment debit)
        $nets = $bonds->isEmpty() ? collect() : AccountStatement::whereIn('es_id', $bonds->pluck('es_id')->filter()->values())
            ->where('is_storage', '!=', 1)
            ->selectRaw('es_id, supp_client_id, SUM(debit_balance - credit_balance) AS net')
            ->groupBy('es_id', 'supp_client_id')->get()
            ->keyBy(fn ($r) => $r->es_id . '|' . $r->supp_client_id);

        $bondsBy = $bonds->groupBy('tourism_booking_id');
        $out = [];
        foreach ($bookings as $booking) {
            $out[$booking->id] = self::one($booking, TourismLedger::totals($booking, $rows->get($booking->booking_no, collect())),
                $bondsBy->get($booking->id, collect()), $nets);
        }
        $ids = collect($out)->flatMap(fn ($s) => array_keys($s['suppliers']))->unique()->values();
        $names = $ids->isEmpty() ? collect() : Supplier::whereIn('id', $ids)->pluck('name', 'id');
        foreach ($out as &$s) {
            foreach ($s['suppliers'] as $id => &$sup) {
                $sup['name'] = (string) ($names[$id] ?? '#' . $id);
            }
            unset($sup);
        }
        unset($s);
        return $out;
    }

    private static function one(TourismBooking $booking, array $totals, Collection $bonds, Collection $nets): array
    {
        $received = $refunded = 0.0;
        $paid = [];
        $vouchers = [];
        foreach ($bonds as $b) {
            $receipt = (int) $b->type === 2;
            $account = (int) ($receipt ? $b->from_account : $b->to_account);
            $net = (float) ($nets[$b->es_id . '|' . $account]->net ?? 0);
            $effective = round($receipt ? -$net : $net, 2);
            $kind = $receipt ? 'receipt' : ($account === (int) $booking->customer_id ? 'refund' : 'supplier');
            if ($kind === 'receipt') {
                $received += $effective;
            } elseif ($kind === 'refund') {
                $refunded += $effective;
            } else {
                $paid[$account] = round(($paid[$account] ?? 0) + $effective, 2);
            }
            $vouchers[] = (object) ['bond' => $b, 'kind' => $kind, 'label' => self::KINDS[$kind], 'account' => $account,
                'effective' => $effective, 'reversed' => abs($effective) < 0.005 && (float) $b->amount > 0];
        }

        $suppliers = [];
        foreach (array_unique(array_merge(array_keys($totals['payable']), array_keys($paid))) as $id) {
            $payable = round($totals['payable'][$id] ?? 0, 2);
            $p = round($paid[$id] ?? 0, 2);
            $suppliers[$id] = ['id' => $id, 'name' => '', 'payable' => $payable, 'paid' => $p, 'remaining' => round($payable - $p, 2)];
        }

        $customerPaid = round($received - $refunded, 2);
        return [
            'sale' => $totals['sale'],
            'cost' => $totals['cost'],
            'profit' => $totals['profit'],
            'received' => round($received, 2),
            'refunded' => round($refunded, 2),
            'customer_paid' => $customerPaid,
            'customer_remaining' => round($totals['sale'] - $customerPaid, 2),
            'suppliers' => $suppliers,
            'supplier_payable' => round(array_sum(array_column($suppliers, 'payable')), 2),
            'supplier_paid' => round(array_sum(array_column($suppliers, 'paid')), 2),
            'supplier_remaining' => round(array_sum(array_column($suppliers, 'remaining')), 2),
            'vouchers' => $vouchers,
        ];
    }

    /**
     * $p: amount, storage_id, money_way (1 cash / 2 bank), bank_id, date, reference.
     * Receipt from the customer, at most what the customer still owes.
     */
    public static function receive(int $bookingId, array $p): ?string
    {
        return DB::transaction(function () use ($bookingId, $p) {
            $booking = TourismBooking::whereKey($bookingId)->lockForUpdate()->first();
            if ($error = self::check($booking)) {
                return $error;
            }
            $amount = round((float) $p['amount'], 2);
            $due = self::summary($booking)['customer_remaining'];
            if ($amount <= 0 || $amount > $due + 0.005) {
                return 'المبلغ يجب أن يكون أكبر من صفر ولا يزيد عن المتبقي على العميل (' . InvoiceFullReport::money(max(0, $due)) . ')';
            }
            $bond = ReceiptBond::create(self::voucher($booking, (int) $booking->customer_id, $amount, $p, 'سداد العميل'));
            self::link($bond, $booking);
            return null;
        });
    }

    /** Refund to the customer, at most what is due to them (paid more than the booking's sale). */
    public static function refund(int $bookingId, array $p): ?string
    {
        return DB::transaction(function () use ($bookingId, $p) {
            $booking = TourismBooking::whereKey($bookingId)->lockForUpdate()->first();
            if ($error = self::check($booking)) {
                return $error;
            }
            $amount = round((float) $p['amount'], 2);
            $due = -self::summary($booking)['customer_remaining'];
            if ($amount <= 0 || $amount > $due + 0.005) {
                return 'المبلغ يجب أن يكون أكبر من صفر ولا يزيد عن المستحق للعميل (' . InvoiceFullReport::money(max(0, $due)) . ')';
            }
            $bond = PaymentBond::create(self::voucher($booking, (int) $booking->customer_id, $amount, $p, 'رد مبلغ للعميل') + ['commission' => 0.0]);
            self::link($bond, $booking);
            return null;
        });
    }

    /** Payment to one of the booking's suppliers ($p['supplier_id'], optional item_id), at most its remaining. */
    public static function paySupplier(int $bookingId, array $p): ?string
    {
        return DB::transaction(function () use ($bookingId, $p) {
            $booking = TourismBooking::whereKey($bookingId)->lockForUpdate()->first();
            if ($error = self::check($booking)) {
                return $error;
            }
            $supplierId = (int) $p['supplier_id'];
            $row = self::summary($booking)['suppliers'][$supplierId] ?? null;
            if (!$row || $supplierId === (int) $booking->customer_id) {
                return 'هذا المورد ليس له مستحقات على الحجز';
            }
            $item = null;
            if (!empty($p['item_id'])) {
                $item = TourismBookingItem::where('booking_id', $booking->id)->where('supplier_id', $supplierId)->find((int) $p['item_id']);
                if (!$item) {
                    return 'الخدمة المختارة لا تخص هذا المورد في الحجز';
                }
            }
            $amount = round((float) $p['amount'], 2);
            if ($amount <= 0 || $amount > $row['remaining'] + 0.005) {
                return 'المبلغ يجب أن يكون أكبر من صفر ولا يزيد عن المتبقي للمورد (' . InvoiceFullReport::money(max(0, $row['remaining'])) . ')';
            }
            $bond = PaymentBond::create(self::voucher($booking, $supplierId, $amount, $p, 'سداد المورد') + ['commission' => 0.0]);
            self::link($bond, $booking, $item);
            return null;
        });
    }

    /** Reverse one of the booking's vouchers (BondReversal::offset); it must still count. */
    public static function reverse(int $bookingId, int $bondId): ?string
    {
        return DB::transaction(function () use ($bookingId, $bondId) {
            $booking = TourismBooking::whereKey($bookingId)->lockForUpdate()->first();
            $bond = Bond::whereKey($bondId)->where('tourism_booking_id', $bookingId)->lockForUpdate()->first();
            if (!$booking || !$bond) {
                return 'السند غير موجود على هذا الحجز';
            }
            $v = collect(self::summary($booking)['vouchers'])->first(fn ($v) => (int) $v->bond->id === $bondId);
            if (!$v || $v->reversed) {
                return 'تم عكس هذا السند من قبل';
            }
            if ($problem = BondReversal::problem($bond)) {
                return $problem;
            }
            BondReversal::offset($bond, "عكس سند {$bond->es_id} - حجز {$booking->booking_no}");
            return null;
        });
    }

    private static function check(?TourismBooking $booking): ?string
    {
        if (!$booking) {
            return 'الحجز غير موجود';
        }
        return $booking->isDraft() ? 'لا يمكن تسجيل مدفوعات على حجز غير مؤكد' : null;
    }

    private static function voucher(TourismBooking $booking, int $account, float $amount, array $p, string $what): array
    {
        return [
            'storage_id' => (int) $p['storage_id'],
            'sub_id' => '0',
            'supp_id' => $account,
            'amount' => number_format($amount, 2, '.', ''),
            'money_way' => (int) $p['money_way'],
            'bank_id' => (int) $p['money_way'] === 2 ? (int) $p['bank_id'] : null,
            'collector_info' => null,
            'info' => $what . ' - حجز ' . $booking->booking_no . (($p['reference'] ?? '') !== '' ? ' - ' . $p['reference'] : ''),
            'date' => $p['date'],
            'file_path' => '',
            'txt_suffix' => ' - ' . $what . ' حجز ' . $booking->booking_no,
        ];
    }

    private static function link(Bond $bond, TourismBooking $booking, ?TourismBookingItem $item = null): void
    {
        Bond::whereKey($bond->id)->update(['tourism_booking_id' => $booking->id, 'tourism_booking_item_id' => $item?->id]);
    }
}
