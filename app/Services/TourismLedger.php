<?php

namespace App\Services;

use App\Models\{AccountStatement, TourismBooking, TourismBookingItem};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, DB};
use RuntimeException;

/**
 * The accounting of internal tourism bookings on the existing account statement
 * (account_statements), with the same rules as the invoices: transaction_type 1, invoice_type 3
 * («فواتير سياحة داخلية»), es_id = the booking number (TRB-00001). No invoice row exists.
 *
 *   customer  debit  = its services' selling amounts (a cancelled service: its cancel_fee)
 *   supplier  credit = the cost of ITS services       (a cancelled service: its cancel_cost)
 *
 * Append-only: rows are never changed or deleted. post() compares what each account should
 * carry now for each service (targets) with what its rows already carry (posted) and appends
 * one row per account with the differences, dated today (confirmation, then every adjustment
 * or cancellation on its own date). A supplier change therefore reverses the old supplier and
 * credits the new one.
 *
 * Row marker (description, JSON; read by InvoicePassengerLedger::rowKind / kindLabel /
 * statementLines so the statements show one line per service):
 *   {"kind": "tourism", "op": "confirm"|"adjust"|"cancel", "booking_id", "role": "customer"|"supplier",
 *    "lines": [{"item_id", "name", "debit", "credit"}, ...]}   -- lines add up to the row.
 *
 * The caller runs post() inside a DB transaction with the booking row locked.
 */
class TourismLedger
{
    const INVOICE_TYPE = 3;

    const OPS = ['confirm' => 'حجز سياحة داخلية', 'adjust' => 'تعديل حجز سياحة', 'cancel' => 'إلغاء حجز/خدمة سياحة'];

    /** What each "account|item|role" should carry now (debit +, credit -); nothing for a draft. */
    public static function targets(TourismBooking $booking): array
    {
        $t = [];
        if ($booking->isDraft()) {
            return $t;
        }
        foreach ($booking->items as $item) {
            $t[$booking->customer_id . '|' . $item->id . '|customer'] = round($item->bookedSale(), 2);
            $t[$item->supplier_id . '|' . $item->id . '|supplier'] = -round($item->bookedCost(), 2);
        }
        return $t;
    }

    /** The booking's ledger rows (oldest first). */
    public static function rows(TourismBooking $booking): Collection
    {
        return AccountStatement::where('es_id', $booking->booking_no)->where('transaction_type', 1)->orderBy('id')->get();
    }

    /** What each "account|item|role" carries now, from the rows' lines (checked against the rows). */
    public static function posted(TourismBooking $booking, ?Collection $rows = null): array
    {
        $p = [];
        foreach ($rows ?? self::rows($booking) as $row) {
            $m = InvoicePassengerLedger::marker($row);
            if (($m['kind'] ?? null) !== 'tourism' || !isset($m['role'], $m['lines'])) {
                throw new RuntimeException("قيد غير متوقع على الحجز {$booking->booking_no} (#{$row->id})");
            }
            $sum = 0.0;
            foreach ($m['lines'] as $l) {
                $v = (float) ($l['debit'] ?? 0) - (float) ($l['credit'] ?? 0);
                $key = $row->supp_client_id . '|' . $l['item_id'] . '|' . $m['role'];
                $p[$key] = round(($p[$key] ?? 0) + $v, 2);
                $sum += $v;
            }
            if (abs($sum - ((float) $row->debit_balance - (float) $row->credit_balance)) >= 0.005) {
                throw new RuntimeException("تفاصيل القيد #{$row->id} لا تساوي مبلغه ({$booking->booking_no})");
            }
        }
        return $p;
    }

    /**
     * Append the rows that bring the ledger to the booking's current state; returns them
     * (none when nothing changed). $op: confirm | adjust | cancel.
     */
    public static function post(TourismBooking $booking, string $op): array
    {
        abort_unless(isset(self::OPS[$op]), 422);
        $booking->load('items');
        $target = self::targets($booking);
        $posted = self::posted($booking);

        $lines = [];
        foreach (array_unique(array_merge(array_keys($target), array_keys($posted))) as $key) {
            $diff = round(($target[$key] ?? 0) - ($posted[$key] ?? 0), 2);
            if (abs($diff) < 0.005) {
                continue;
            }
            [$account, $itemId, $role] = explode('|', $key);
            $item = $booking->items->firstWhere('id', (int) $itemId);
            $name = $item ? $item->title() : 'خدمة #' . $itemId;
            if ($item && !$item->isActive()) {
                $name = 'إلغاء ' . $name;
            }
            $lines[$account . '|' . $role][] = [
                'item_id' => (int) $itemId,
                'name' => $name,
                'debit' => $diff > 0 ? $diff : null,
                'credit' => $diff < 0 ? -$diff : null,
            ];
        }

        $today = now()->format('Y-m-d');
        $created = [];
        foreach ($lines as $k => $ls) {
            [$account, $role] = explode('|', $k);
            $net = round(array_sum(array_map(fn ($l) => ($l['debit'] ?? 0) - ($l['credit'] ?? 0), $ls)), 2);
            $created[] = AccountStatement::create([
                'supp_client_id' => $account,
                'invoice_type' => self::INVOICE_TYPE,
                'es_id' => $booking->booking_no,
                'invoice_date' => $today,
                'debit_balance' => $net > 0 ? $net : 0,
                'credit_balance' => $net < 0 ? -$net : 0,
                'ledger_net_effect' => $net,
                'transaction_txt' => self::OPS[$op] . ' ' . $booking->booking_no,
                'transaction_type' => 1,
                'added_by' => Auth::id(),
                'crt_date' => $today,
                'description' => json_encode(['kind' => 'tourism', 'op' => $op, 'booking_id' => (int) $booking->id,
                    'role' => $role, 'lines' => $ls], JSON_UNESCAPED_UNICODE),
            ]);
        }
        return $created;
    }

    /**
     * The booking's figures from its ledger rows: sale (customer net debit), cost (suppliers'
     * net credit), profit, and each supplier's payable (account id => amount).
     */
    public static function totals(TourismBooking $booking, ?Collection $rows = null): array
    {
        $sale = 0.0;
        $cost = 0.0;
        $payable = [];
        foreach ($rows ?? self::rows($booking) as $row) {
            $net = (float) $row->debit_balance - (float) $row->credit_balance;
            if ((InvoicePassengerLedger::marker($row)['role'] ?? null) === 'customer') {
                $sale += $net;
            } else {
                $cost -= $net;
                $payable[(int) $row->supp_client_id] = round(($payable[(int) $row->supp_client_id] ?? 0) - $net, 2);
            }
        }
        return ['sale' => round($sale, 2), 'cost' => round($cost, 2), 'profit' => round($sale - $cost, 2), 'payable' => $payable];
    }

    /**
     * Each owner's tourism sale / cost from the ledger rows dated from..to (the commission
     * period rule: every movement counts on its own date), for the Step D commission engine:
     * employee id => ['sale', 'cost', 'bookings' => [booking id => true]]. $employeeId limits it.
     */
    public static function employeeTotals(string $from, string $to, ?int $employeeId = null): array
    {
        $q = DB::table('account_statements as a')
            ->join('tourism_bookings as b', 'b.booking_no', '=', 'a.es_id')
            ->where('a.transaction_type', 1)
            ->where('a.es_id', 'like', 'TRB-%')
            ->where('a.crt_date', '>=', $from)->where('a.crt_date', '<=', $to);
        if ($employeeId !== null) {
            $q->where('b.created_by', $employeeId);
        }
        $out = [];
        foreach ($q->get(['b.id as booking_id', 'b.created_by', 'a.debit_balance', 'a.credit_balance', 'a.description']) as $r) {
            $role = json_decode((string) $r->description, true)['role'] ?? null;
            $net = (float) $r->debit_balance - (float) $r->credit_balance;
            $s = &$out[(int) $r->created_by];
            $s ??= ['sale' => 0.0, 'cost' => 0.0, 'bookings' => []];
            if ($role === 'customer') {
                $s['sale'] += $net;
            } else {
                $s['cost'] -= $net;
            }
            $s['bookings'][(int) $r->booking_id] = true;
            unset($s);
        }
        return $out;
    }
}
