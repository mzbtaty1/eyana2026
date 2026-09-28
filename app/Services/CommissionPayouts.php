<?php

namespace App\Services;

use App\Models\{Bond, CommissionPayout, CommissionPeriod, User};
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\{Auth, DB};

/**
 * Employee commission payouts («صرف عمولات الموظفين», Employees step E).
 *
 * The commission is ALWAYS the Step D result (EmployeeCommissionReport) for the employee and
 * the period, recalculated on the server for the preview, when a period is opened and inside
 * every payment -- an amount from the browser is never trusted.
 *
 *   open    a period (employee + from / to, from 2026-09-01 on) stores the Step D commission
 *           and its calculation snapshot; the same employee's periods never overlap
 *   refresh a period nothing was paid for yet may take the current Step D figures again
 *   pay     only once the period has ended (To Date before today);
 *           a payment voucher (PaymentBond) from a treasury (and bank) to expense account #81,
 *           amount 0 < amount <= remaining (approved commission - active payments); several
 *           payments = partial; the period closes when nothing remains. Refused while the
 *           current Step D commission differs from the approved one (shown to the admin; no
 *           automatic adjustment, extra payment or refund)
 *   reverse offsets the payout's voucher (BondReversal::offset -- nothing deleted), marks the
 *           payout reversed and reopens the period
 *
 * Every change runs in one transaction: the employee row (open) or the period row (pay /
 * reverse) is locked first, then the treasury and bank (PaymentBond / BondReversal), so
 * concurrent attempts are serialized; request_token (unique) stops a resubmitted payment.
 * Errors are returned as Arabic messages (null = done).
 */
class CommissionPayouts
{
    const START_DATE = '2026-09-01';

    /** Expense account «رواتب وسلف وعمولة موظفين» that commission payouts are paid to. */
    const EXPENSE_ACCOUNT = 81;

    /** The Step D result of one employee and period (the single source of the commission). */
    public static function calculate(User $viewer, int $employeeId, string $from, string $to): array
    {
        $r = (new EmployeeCommissionReport($viewer, $from, $to, $employeeId))->results();
        return $r[0];
    }

    /** The snapshot stored with a period. */
    public static function snapshot(array $r, string $from, string $to): array
    {
        return [
            'employee_id' => $r['employee_id'], 'employee' => $r['employee'], 'from' => $from, 'to' => $to,
            'sales' => $r['sales'], 'cost' => $r['cost'], 'refund_net' => $r['refund_net'], 'profit' => $r['profit'],
            'method' => $r['method'], 'method_label' => $r['method_label'], 'tier_table' => $r['tier_table'],
            'tier' => $r['tier'], 'rate' => $r['rate'], 'commission' => $r['commission'],
            'calculated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /** Another period of the employee overlapping from..to (not the exact same one). */
    public static function overlapping(int $employeeId, string $from, string $to): ?CommissionPeriod
    {
        return CommissionPeriod::where('employee_id', $employeeId)
            ->where('period_from', '<=', $to)->where('period_to', '>=', $from)
            ->where(fn ($q) => $q->where('period_from', '!=', $from)->orWhere('period_to', '!=', $to))
            ->first();
    }

    /** A period can be paid only once it has ended: its To Date is before today. */
    public static function completed(CommissionPeriod $period): bool
    {
        return $period->period_to->format('Y-m-d') < now()->toDateString();
    }

    public static function differs(float $current, $approved): bool
    {
        return abs(round($current, 2) - round((float) $approved, 2)) >= 0.005;
    }

    /** Open (or return the existing identical) period. [?CommissionPeriod, ?error] */
    public static function open(User $employee, string $from, string $to): array
    {
        if ($from < self::START_DATE) {
            return [null, 'صرف العمولات من خلال النظام يبدأ من ' . self::START_DATE . '، ولا يمكن فتح فترة قبل ذلك'];
        }
        return DB::transaction(function () use ($employee, $from, $to) {
            User::whereKey($employee->id)->lockForUpdate()->first();     // serializes this employee's period creation
            $existing = CommissionPeriod::where('employee_id', $employee->id)->where('period_from', $from)->where('period_to', $to)->first();
            if ($existing) {
                return [$existing, null];
            }
            if ($other = self::overlapping($employee->id, $from, $to)) {
                return [null, 'توجد فترة أخرى لنفس الموظف متداخلة مع هذه الفترة (' . $other->label() . ')'];
            }
            $r = self::calculate(Auth::user(), $employee->id, $from, $to);
            if ($r['commission'] <= 0) {
                return [null, 'لا توجد عمولة مستحقة للموظف في هذه الفترة'];
            }
            $period = CommissionPeriod::create([
                'employee_id' => $employee->id, 'period_from' => $from, 'period_to' => $to,
                'commission_amount' => $r['commission'], 'calculation' => self::snapshot($r, $from, $to),
                'status' => CommissionPeriod::OPEN, 'created_by' => Auth::id(),
            ]);
            return [$period, null];
        });
    }

    /** Take the current Step D figures for a period nothing was paid for yet. */
    public static function refresh(int $periodId): ?string
    {
        return DB::transaction(function () use ($periodId) {
            $period = CommissionPeriod::whereKey($periodId)->lockForUpdate()->firstOrFail();
            if ($period->activePayouts()->exists()) {
                return 'لا يمكن تحديث عمولة فترة صرف منها مبلغ';
            }
            [$from, $to] = [$period->period_from->format('Y-m-d'), $period->period_to->format('Y-m-d')];
            $r = self::calculate(Auth::user(), $period->employee_id, $from, $to);
            if ($r['commission'] <= 0) {
                return 'لا توجد عمولة مستحقة للموظف في هذه الفترة';
            }
            $period->update(['commission_amount' => $r['commission'], 'calculation' => self::snapshot($r, $from, $to),
                'status' => CommissionPeriod::OPEN, 'closed_at' => null, 'closed_by' => null]);
            return null;
        });
    }

    /**
     * Pay $p['amount'] of a period: storage_id, money_way (1 cash / 2 bank), bank_id,
     * paid_date, reference, request_token. [?CommissionPayout, ?error]
     */
    public static function pay(int $periodId, array $p): array
    {
        try {
            return DB::transaction(function () use ($periodId, $p) {
                $period = CommissionPeriod::whereKey($periodId)->lockForUpdate()->first();
                if (!$period) {
                    return [null, 'الفترة غير موجودة'];
                }
                if (CommissionPayout::where('request_token', $p['request_token'])->exists()) {
                    return [null, 'تم تسجيل هذه العملية من قبل (طلب مكرر)'];
                }
                if ($period->isClosed()) {
                    return [null, 'هذه الفترة مغلقة (تم صرف العمولة بالكامل)'];
                }
                if (!self::completed($period)) {
                    return [null, 'لا يمكن صرف عمولة فترة لم تنته بعد: الصرف متاح بعد ' . $period->period_to->format('Y-m-d')];
                }
                [$from, $to] = [$period->period_from->format('Y-m-d'), $period->period_to->format('Y-m-d')];
                $current = self::calculate(Auth::user(), $period->employee_id, $from, $to)['commission'];
                if (self::differs($current, $period->commission_amount)) {
                    return [null, 'العمولة الحالية (' . InvoiceFullReport::money($current) . ') تختلف عن العمولة المعتمدة للفترة ('
                        . InvoiceFullReport::money($period->commission_amount) . '). راجع الفرق قبل الصرف.'];
                }
                $amount = round((float) $p['amount'], 2);
                $remaining = $period->remaining();
                if ($amount <= 0) {
                    return [null, 'برجاء إدخال مبلغ صحيح أكبر من صفر'];
                }
                if ($amount > $remaining + 0.005) {
                    return [null, 'المبلغ أكبر من المتبقي للفترة (' . InvoiceFullReport::money($remaining) . ')'];
                }

                $employee = User::find($period->employee_id);
                $what = 'عمولة الموظف ' . $employee->name . ' عن الفترة ' . $period->label();
                $bond = PaymentBond::create([
                    'storage_id' => (int) $p['storage_id'],
                    'sub_id' => '0',
                    'supp_id' => self::EXPENSE_ACCOUNT,
                    'amount' => number_format($amount, 2, '.', ''),
                    'commission' => 0.0,
                    'money_way' => (int) $p['money_way'],
                    'bank_id' => (int) $p['money_way'] === 2 ? (int) $p['bank_id'] : null,
                    'collector_info' => null,
                    'info' => 'صرف ' . $what . (($p['reference'] ?? '') !== '' ? ' - ' . $p['reference'] : ''),
                    'date' => $p['paid_date'],
                    'file_path' => '',
                    'txt_suffix' => ' - ' . $what,
                ]);
                $payout = CommissionPayout::create([
                    'period_id' => $period->id, 'amount' => $amount, 'paid_date' => $p['paid_date'], 'bond_id' => $bond->id,
                    'storage_id' => (int) $p['storage_id'], 'money_way' => (int) $p['money_way'],
                    'bank_id' => (int) $p['money_way'] === 2 ? (int) $p['bank_id'] : null,
                    'reference' => ($p['reference'] ?? '') !== '' ? $p['reference'] : null,
                    'request_token' => $p['request_token'], 'created_by' => Auth::id(),
                ]);
                if ($period->fresh()->remaining() <= 0.005) {
                    $period->update(['status' => CommissionPeriod::CLOSED, 'closed_at' => now(), 'closed_by' => Auth::id()]);
                }
                return [$payout, null];
            });
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {           // request_token unique: a concurrent resubmission
                return [null, 'تم تسجيل هذه العملية من قبل (طلب مكرر)'];
            }
            throw $e;
        }
    }

    /** Reverse a payout: offset its voucher, mark it reversed, reopen the period. */
    public static function reverse(int $payoutId): ?string
    {
        return DB::transaction(function () use ($payoutId) {
            $periodId = CommissionPayout::whereKey($payoutId)->value('period_id');
            if (!$periodId) {
                return 'عملية الصرف غير موجودة';
            }
            $period = CommissionPeriod::whereKey($periodId)->lockForUpdate()->first();
            $payout = CommissionPayout::whereKey($payoutId)->lockForUpdate()->first();
            if ($payout->isReversed()) {
                return 'تم عكس عملية الصرف هذه من قبل';
            }
            $bond = Bond::whereKey($payout->bond_id)->lockForUpdate()->first();
            $problem = $bond ? BondReversal::problem($bond) : 'سند الصرف غير موجود';
            if ($problem) {
                return $problem;
            }
            $note = 'عكس صرف عمولة الموظف عن الفترة ' . $period->label() . ' - سند ' . $bond->es_id;
            BondReversal::offset($bond, $note);
            $payout->update(['reversed_at' => now(), 'reversed_by' => Auth::id(), 'reversal_bond_note' => $note]);
            $period->update(['status' => CommissionPeriod::OPEN, 'closed_at' => null, 'closed_by' => null]);
            return null;
        });
    }
}
