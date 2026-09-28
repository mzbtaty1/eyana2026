<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * «التقرير التفصيلي للفواتير» with the employee commission rules (Employees step D): the rows,
 * filters and every financial amount are InvoiceFullReport's, unchanged; only the commission
 * (and its rate label) is replaced.
 *
 * The commission belongs to an employee's TOTAL profit for a period (fixed %, or one tier of
 * their tier table), so it needs the report's period: without both dates no commission is shown.
 * For the period, EmployeeCommissionReport runs once and gives each employee's effective rate
 * (the fixed % or the tier's rate; 0 when their period profit is 0 or less). A row's commission
 * is then its profit x each employee's share of it (normal: the invoice's employee 100%; shared:
 * the two accounts by their participation weights, 7/3 -> 70% / 30%) x that employee's rate.
 * With only the period selected, the rows add up to the employee commission report's total;
 * with more filters, the rows shown carry their part of each employee's period commission.
 */
class InvoiceFullReportWithCommission extends InvoiceFullReport
{
    const NO_PERIOD = 'برجاء تحديد الفترة لعرض العمولة';

    /** employee id => ['rate' => %, 'label' => text] for the period. */
    protected ?array $rates = null;

    protected ?Collection $commissionRows = null;

    public function commissionAvailable(): bool
    {
        return $this->f['date_from'] !== null && $this->f['date_to'] !== null;
    }

    /** What the commission shown means for the current filters (or why none is shown). */
    public function commissionNote(): string
    {
        if (!$this->commissionAvailable()) {
            return self::NO_PERIOD;
        }
        $period = $this->f['date_from'] . ' : ' . $this->f['date_to'];
        $others = array_diff_key(array_filter($this->f, fn ($v) => $v !== null), array_flip(['date_from', 'date_to']));
        return $others
            ? "العمولة محسوبة على إجمالي ربح كل موظف في الفترة ($period) حسب إعدادات عمولته، والصفوف المعروضة بعد التصفية تمثل الجزء الخاص بها من عمولة الفترة."
            : "العمولة محسوبة على إجمالي ربح كل موظف في الفترة ($period) حسب إعدادات عمولته (نسبة ثابتة أو شريحة).";
    }

    protected function rates(): array
    {
        return $this->rates ??= (new EmployeeCommissionReport($this->user, $this->f['date_from'], $this->f['date_to'],
            $this->isAdmin() ? $this->f['employee_id'] : null))->effectiveRates();
    }

    /** [[employee id, share], ...] of a row -- only the viewed employee's part when one is selected. */
    protected function parts(array $r): array
    {
        $parts = EmployeeCommissionReport::shares($r);
        return $this->ctx === null ? $parts : array_values(array_filter($parts, fn ($p) => $p[0] === $this->ctx));
    }

    /** The commission of one employee's part of a row (null without a period). */
    protected function partCommission(array $r, int $uid, float $share): ?float
    {
        return $this->commissionAvailable() ? $r['profit'] * $share * ($this->rates()[$uid]['rate'] ?? 0.0) / 100 : null;
    }

    public function rows(): Collection
    {
        if ($this->commissionRows !== null) {
            return $this->commissionRows;
        }
        $available = $this->commissionAvailable();
        $rates = $available ? $this->rates() : [];

        $rows = parent::rows()->map(function (array $r) use ($available, $rates) {
            if (!$available) {
                return array_merge($r, ['commission' => null, 'rate' => null, 'rate_label' => 'حدد الفترة']);
            }
            $parts = $this->parts($r);
            $commission = 0.0;
            $labels = [];
            foreach ($parts as [$uid, $share]) {
                $commission += $this->partCommission($r, $uid, $share);
                $label = $rates[$uid]['label'] ?? '0%';
                $labels[] = count($parts) > 1 || $share < 1 ? EmployeeCommissionReport::percent($share * 100) . '% × ' . $label : $label;
            }
            $rate = array_sum(array_map(fn ($p) => $p[1] * ($rates[$p[0]]['rate'] ?? 0.0), $parts));
            return array_merge($r, ['commission' => $commission, 'rate' => $rate, 'rate_label' => $labels ? implode(' + ', $labels) : '—']);
        });

        return $this->rows = $this->commissionRows = $rows;
    }

    public function summary(): array
    {
        $t = parent::summary();
        if (!$this->commissionAvailable()) {
            $t['commission'] = null;
        }
        return $t;
    }

    /**
     * As InvoiceFullReport::groups(); by employee, a shared invoice still shows under each of its
     * employees with its whole sale and profit (as before), and each carries the commission of
     * their own share only -- so an employee's row is their period commission.
     */
    public function groups(string $by): array
    {
        if ($by !== 'employee') {
            $out = parent::groups($by);
        } else {
            $groups = [];
            $add = function ($uid, array $r) use (&$groups) {
                $groups['u' . $uid]['label'] = $this->userName($uid);
                $groups['u' . $uid]['rows'][] = $r;
            };
            foreach ($this->rows() as $r) {
                if (!$r['shared']) {
                    $add($r['creator'], $r);
                    continue;
                }
                // each of the two accounts, as InvoiceFullReport lists them (the same employee on
                // both: listed twice as before, with half of their 100% share each)
                $shares = EmployeeCommissionReport::shares($r);
                foreach ($r['shared'] as $i => [$uid]) {
                    if ($this->ctx !== null && $uid !== $this->ctx) {
                        continue;
                    }
                    $share = count($shares) === 1 ? 0.5 : $shares[$i][1];
                    $add($uid, array_merge($r, ['commission' => $this->partCommission($r, $uid, $share), 'shared_part' => 1]));
                }
            }
            $out = [];
            foreach ($groups as $g) {
                $out[] = ['label' => $g['label'], 'shared_tickets' => collect($g['rows'])->sum(fn ($r) => $r['shared_part'] ?? 0)] + self::totals($g['rows']);
            }
            usort($out, fn ($a, $b) => $b['net_profit'] <=> $a['net_profit']);
        }
        if (!$this->commissionAvailable()) {
            $out = array_map(fn ($g) => array_merge($g, ['commission' => null]), $out);
        }
        return $out;
    }
}
