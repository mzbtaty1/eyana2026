<?php

namespace App\Services;

use App\Models\{CommissionPeriod, User};
use Illuminate\Support\Collection;

/**
 * Commission periods with their figures (Employees step F): the admin dashboard, its print /
 * Excel, and an employee's statement. Nothing is calculated here:
 *
 *   commission at opening   commission_periods.commission_amount + its snapshot (Step E)
 *   current commission      the Step D engine (EmployeeCommissionReport), run ONCE per distinct
 *                           period (from / to) for every employee in it -- not per row
 *   paid / remaining        the period's active (not reversed) payouts (Step E)
 *   difference              current - commission at opening: shown, never adjusted
 *
 * Filters: employee_id, from / to (periods inside the dates), status (open / closed).
 */
class CommissionDashboard
{
    const STATUSES = ['open' => 'مفتوحة', 'closed' => 'مغلقة'];

    public array $f;

    protected ?Collection $rows = null;

    public function __construct(protected User $viewer, array $input)
    {
        $date = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : null;
        $this->f = [
            'employee_id' => ctype_digit((string) ($input['employee_id'] ?? '')) ? (int) $input['employee_id'] : null,
            'from' => $date($input['from'] ?? null),
            'to' => $date($input['to'] ?? null),
            'status' => isset(self::STATUSES[$input['status'] ?? '']) ? $input['status'] : null,
        ];
    }

    /** The periods (newest first) with their payouts and figures. */
    public function rows(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }
        $periods = CommissionPeriod::with(['employee:id,name', 'payouts.bond:id,es_id', 'payouts.creator:id,name', 'payouts.reverser:id,name'])
            ->withSum(['payouts as active_payouts_sum_amount' => fn ($q) => $q->whereNull('reversed_at')], 'amount')
            ->withMax(['payouts as last_paid_date' => fn ($q) => $q->whereNull('reversed_at')], 'paid_date')
            ->when($this->f['employee_id'], fn ($q, $id) => $q->where('employee_id', $id))
            ->when($this->f['from'], fn ($q, $d) => $q->where('period_from', '>=', $d))
            ->when($this->f['to'], fn ($q, $d) => $q->where('period_to', '<=', $d))
            ->when($this->f['status'], fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('period_from')->orderBy('employee_id')->get();

        // current Step D commission: one engine run per distinct period, for all its employees
        $current = [];
        foreach ($periods->groupBy(fn ($p) => $p->label()) as $group) {
            $p = $group->first();
            $one = $group->pluck('employee_id')->unique()->count() === 1 ? (int) $p->employee_id : null;
            $results = (new EmployeeCommissionReport($this->viewer, $p->period_from->format('Y-m-d'), $p->period_to->format('Y-m-d'), $one))->results();
            foreach ($results as $r) {
                $current[$p->label()][$r['employee_id']] = $r;
            }
        }

        return $this->rows = $periods->map(function (CommissionPeriod $p) use ($current) {
            $now = $current[$p->label()][$p->employee_id] ?? null;
            $currentCommission = $now ? (float) $now['commission'] : 0.0;
            $opening = (float) $p->commission_amount;
            return [
                'period' => $p,
                'employee' => $p->employee->name ?? '#' . $p->employee_id,
                'from' => $p->period_from->format('Y-m-d'),
                'to' => $p->period_to->format('Y-m-d'),
                'profit' => (float) ($p->calculation['profit'] ?? 0),
                'commission' => $opening,
                'current' => $currentCommission,
                'current_profit' => $now ? (float) $now['profit'] : 0.0,
                'difference' => round($currentCommission - $opening, 2),
                'differs' => CommissionPayouts::differs($currentCommission, $opening),
                'paid' => $p->paid(),
                'remaining' => $p->remaining(),
                'status' => $p->status,
                'status_label' => $p->isClosed() ? 'مغلقة' : ($p->paid() > 0 ? 'مفتوحة (صرف جزئي)' : 'مفتوحة'),
                'last_paid_date' => $p->last_paid_date ? substr((string) $p->last_paid_date, 0, 10) : null,
                'reversals' => $p->payouts->filter->isReversed()->count(),
            ];
        });
    }

    public function totals(): array
    {
        $t = [];
        foreach (['commission', 'current', 'difference', 'paid', 'remaining'] as $k) {
            $t[$k] = round($this->rows()->sum($k), 2);
        }
        return $t;
    }

    /** Readable active filters. */
    public function filterLabels(): array
    {
        $out = [];
        if ($this->f['employee_id']) {
            $out['الموظف'] = (string) User::whereKey($this->f['employee_id'])->value('name');
        }
        if ($this->f['from'] || $this->f['to']) {
            $out['الفترات'] = ($this->f['from'] ?: '—') . ' : ' . ($this->f['to'] ?: '—');
        }
        if ($this->f['status']) {
            $out['الحالة'] = self::STATUSES[$this->f['status']];
        }
        return $out;
    }

    /** Header + one row per period + totals (the dashboard's figures), for Excel. */
    public function table(): array
    {
        $out = [['الموظف', 'من', 'إلى', 'الربح وقت فتح الفترة', 'العمولة وقت فتح الفترة', 'العمولة الحالية', 'الفرق', 'المصروف', 'المتبقي', 'الحالة', 'آخر صرف']];
        foreach ($this->rows() as $r) {
            $out[] = [$r['employee'], $r['from'], $r['to'], $r['profit'], $r['commission'], $r['current'], $r['difference'], $r['paid'], $r['remaining'], $r['status_label'], $r['last_paid_date'] ?? ''];
        }
        $t = $this->totals();
        $out[] = ['الإجمالي', '', '', '', $t['commission'], $t['current'], $t['difference'], $t['paid'], $t['remaining'], '', ''];
        return $out;
    }
}
