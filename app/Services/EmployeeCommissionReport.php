<?php

namespace App\Services;

use App\Models\{CommissionTierTable, User};
use App\Support\Permissions;
use Illuminate\Support\Facades\Gate;

/**
 * Employee commission report («تقرير عمولات الموظفين», Employees step D): each employee's
 * profit for a period and the commission on it, from their commission settings (step C).
 * Read-only -- nothing is stored, paid or closed here.
 *
 * Profit: the rows of InvoiceFullReport for the period (invoice date), so its financial rules
 * apply unchanged: a sale / re-issue row brings its sale and cost, a refund row its refund net
 * (supplier return - client refund, from the refund's own ledger rows); a re-issue or refund is
 * its own operation, on its own date, recorded for the employee who made it.
 *   Total profit = sales - cost + refund net
 *
 * Who a row counts for: a normal invoice -- its employee (invoice_create_by), 100%. A shared
 * invoice (also its re-issues / refunds, which keep the two accounts) is split between its two
 * accounts by their stored rates read as relative weights: 7/3 -> 70% / 30%, 5/5 and 50/50 ->
 * 50% / 50%, both zero -> 50% / 50%; one employee on both accounts -> 100%. Sales, cost and
 * profit are all split by the same share. Admins are included like anyone who sells.
 *
 * Commission, on the employee's TOTAL profit for the period (never per invoice):
 *   fixed:  profit x users.commission % (the stored text, e.g. "10%" = 10, "12.5" = 12.5)
 *   tiered: profit x the rate of ONE tier of the assigned table, chosen by the total profit
 *           (CommissionTierTable::tierFor) -- also when the table was deactivated since
 *   profit <= 0 -> no commission.
 *   Net company profit = total profit - commission.
 *
 * Queries: one InvoiceFullReport (a fixed number, whatever the number of invoices) plus the
 * employees and their tier tables (one query each) -- never per invoice or per employee.
 */
class EmployeeCommissionReport
{
    public ?int $employeeId;

    protected ?array $results = null;

    /**
     * $viewer without reports.all (an employee) always gets their own report: the requested
     * employee is ignored.
     */
    public function __construct(protected User $viewer, public string $from, public string $to, ?int $employeeId = null)
    {
        $this->employeeId = Gate::forUser($viewer)->allows(Permissions::REPORTS_ALL) ? $employeeId : (int) $viewer->id;
    }

    /** One result per employee (the selected one, or every employee with operations in the period). */
    public function results(): array
    {
        if ($this->results !== null) {
            return $this->results;
        }

        $report = new InvoiceFullReport(['date_from' => $this->from, 'date_to' => $this->to, 'employee_id' => $this->employeeId], $this->viewer);
        $sums = [];
        foreach ($report->rows() as $row) {
            foreach (self::shares($row) as [$uid, $share]) {
                if ($this->employeeId !== null && $uid !== $this->employeeId) {
                    continue;
                }
                $s = &$sums[$uid];
                $s ??= ['sales' => 0.0, 'cost' => 0.0, 'refund_net' => 0.0, 'invoices' => []];
                if ($row['op'] === 'refund') {
                    $s['refund_net'] += $row['profit'] * $share;
                } else {
                    $s['sales'] += $row['sale'] * $share;
                    $s['cost'] += $row['purchase'] * $share;
                }
                $s['invoices'][$row['id']] = true;
                unset($s);
            }
        }
        if ($this->employeeId !== null) {
            $sums += [$this->employeeId => ['sales' => 0.0, 'cost' => 0.0, 'refund_net' => 0.0, 'invoices' => []]];
        }

        $users = User::whereIn('id', array_keys($sums))->get()->keyBy('id');
        $tableIds = $users->filter->usesTieredCommission()->pluck('commission_tier_table_id')->filter()->unique()->values();
        $tables = $tableIds->isEmpty() ? collect() : CommissionTierTable::with('tiers')->whereIn('id', $tableIds)->get()->keyBy('id');

        $out = [];
        foreach ($sums as $uid => $s) {
            $user = $users->get($uid);
            $sales = round($s['sales'], 2);
            $cost = round($s['cost'], 2);
            $refundNet = round($s['refund_net'], 2);
            $profit = round($s['sales'] - $s['cost'] + $s['refund_net'], 2);
            $commission = self::commission($user, $profit, $user ? $tables->get($user->commission_tier_table_id) : null);
            $out[] = [
                'employee_id' => $uid,
                'employee' => $user ? (string) $user->name : ($uid ? '#' . $uid : '—'),
                'invoices' => count($s['invoices']),
                'sales' => $sales,
                'cost' => $cost,
                'refund_net' => $refundNet,
                'profit' => $profit,
            ] + $commission + [
                'net_company_profit' => round($profit - $commission['commission'], 2),
            ];
        }
        usort($out, fn ($a, $b) => $b['profit'] <=> $a['profit'] ?: strcmp($a['employee'], $b['employee']));

        return $this->results = $out;
    }

    /**
     * Each employee's effective commission rate for the period, for reports that show the
     * commission per ticket (InvoiceFullReportWithCommission): employee id => rate % (the
     * fixed % or the tier's rate; 0 when the period profit is 0 or less) and its label.
     * rate x the employee's share of every row adds up to their period commission.
     */
    public function effectiveRates(): array
    {
        $out = [];
        foreach ($this->results() as $r) {
            $rate = $r['profit'] > 0 ? (float) $r['rate'] : 0.0;
            $label = self::percent($rate) . '%';
            if ($r['method'] === User::COMMISSION_TIERED) {
                $label = 'شرائح' . ($r['tier_table'] !== null ? ' «' . $r['tier_table'] . '»' : '') . ' ' . $label;
            }
            $out[$r['employee_id']] = ['rate' => $rate, 'label' => $r['profit'] > 0 ? $label : $label . ' (لا ربح في الفترة)'];
        }
        return $out;
    }

    /** A rate / share as text, decimals kept: 10 -> "10", 12.5 -> "12.5", 33.333 -> "33.33". */
    public static function percent(float $v): string
    {
        return rtrim(rtrim(number_format(round($v, 2), 2, '.', ''), '0'), '.');
    }

    /** Totals of the results (every employee together). */
    public function totals(): array
    {
        $t = [];
        foreach (['sales', 'cost', 'refund_net', 'profit', 'commission', 'net_company_profit'] as $k) {
            $t[$k] = round(array_sum(array_column($this->results(), $k)), 2);
        }
        return $t;
    }

    /**
     * [[user id, share 0..1], ...] of one InvoiceFullReport row: a normal invoice's employee
     * 100%; a shared invoice's two accounts by their rates as relative weights (both zero -> half
     * each; the same employee on both -> 100%).
     */
    public static function shares(array $row): array
    {
        if (!$row['shared']) {
            return [[(int) $row['creator'], 1.0]];
        }
        [[$a1, $r1], [$a2, $r2]] = $row['shared'];
        if ($a1 === $a2) {
            return [[$a1, 1.0]];
        }
        $r1 = max(0.0, (float) $r1);
        $r2 = max(0.0, (float) $r2);
        $sum = $r1 + $r2;
        return $sum > 0 ? [[$a1, $r1 / $sum], [$a2, $r2 / $sum]] : [[$a1, 0.5], [$a2, 0.5]];
    }

    /**
     * The commission on an employee's total period profit, from their settings (step C):
     * method, tier table and applied tier (tiered), rate % and amount. Profit <= 0: none.
     */
    public static function commission(?User $user, float $profit, ?CommissionTierTable $table): array
    {
        $tiered = $user && $user->usesTieredCommission();
        $tier = $tiered && $table ? $table->tierFor($profit) : null;
        if ($tiered) {
            $rate = $tier ? (float) $tier->rate : 0.0;
        } else {
            $stored = $user ? User::normalizeCommission($user->commission) : '';
            $rate = is_numeric($stored) ? (float) $stored : 0.0;
        }

        return [
            'method' => $tiered ? User::COMMISSION_TIERED : User::COMMISSION_FIXED,
            'method_label' => User::COMMISSION_METHODS[$tiered ? User::COMMISSION_TIERED : User::COMMISSION_FIXED],
            'tier_table' => $tiered && $table ? $table->name : null,
            'tier_table_active' => $tiered && $table ? $table->isActive() : null,
            'tier' => $tier ? ['from' => (float) $tier->from_amount, 'to' => $tier->to_amount === null ? null : (float) $tier->to_amount,
                'rate' => (float) $tier->rate, 'n' => (int) $tier->sort_order] : null,
            'rate' => $rate,
            'commission' => $profit > 0 ? round($profit * $rate / 100, 2) : 0.0,
        ];
    }
}
