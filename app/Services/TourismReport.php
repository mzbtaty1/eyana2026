<?php

namespace App\Services;

use App\Models\{AccountStatement, Supplier, TourismBooking, TourismBookingItem, TourismProgram, User};
use App\Support\Permissions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Internal tourism report («تقرير السياحة الداخلية»): read-only.
 *
 * One row per booking. Sales / cost / profit are the booking's ledger movements (TRB rows)
 * dated in date_from..date_to (all dates when empty) -- the same dates the commission uses;
 * receivable / payable are the booking's current balances (TourismPayments::summaries).
 * Filters: date_from / date_to (movement dates), travel_from / travel_to (the booking's start
 * date), status, program_id, customer_id, supplier_id (a service's supplier), employee_id (the
 * owner), service_type (a service's type). An employee without tourism.view_all sees their own
 * bookings only.
 *
 * Groups (group_by): booking-level -- program, employee, customer, status; service-level (the
 * rows' per-service lines) -- service_type, supplier (a service's sale counts for its current
 * supplier, its cost for the supplier the cost was booked on).
 */
class TourismReport
{
    const GROUPS = ['program' => 'البرنامج', 'service_type' => 'نوع الخدمة', 'supplier' => 'المورد',
        'employee' => 'الموظف', 'customer' => 'العميل', 'status' => 'الحالة'];

    public array $f;
    protected ?Collection $bookings = null;
    protected ?array $rows = null;
    protected array $lines = [];

    public function __construct(array $input, protected User $viewer)
    {
        $date = fn ($k) => isset($input[$k]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input[$k]) ? $input[$k] : null;
        $int = fn ($k) => isset($input[$k]) && ctype_digit((string) $input[$k]) ? (int) $input[$k] : null;
        $this->f = [
            'date_from' => $date('date_from'), 'date_to' => $date('date_to'),
            'travel_from' => $date('travel_from'), 'travel_to' => $date('travel_to'),
            'status' => isset(TourismBooking::STATUSES[$input['status'] ?? '']) ? $input['status'] : null,
            'program_id' => $int('program_id'), 'customer_id' => $int('customer_id'),
            'supplier_id' => $int('supplier_id'), 'employee_id' => $int('employee_id'),
            'service_type' => isset(TourismBookingItem::TYPES[$input['service_type'] ?? '']) ? $input['service_type'] : null,
            'group_by' => isset(self::GROUPS[$input['group_by'] ?? '']) ? $input['group_by'] : 'program',
        ];
        if (!Gate::forUser($viewer)->allows(Permissions::REPORTS_ALL) && !Gate::forUser($viewer)->allows(Permissions::TOURISM_VIEW_ALL)) {
            $this->f['employee_id'] = (int) $viewer->id;
        }
    }

    public function bookings(): Collection
    {
        if ($this->bookings !== null) {
            return $this->bookings;
        }
        $f = $this->f;
        $q = TourismBooking::visibleTo($this->viewer)->with(['customer:id,name', 'program:id,name', 'owner:id,name', 'items']);
        foreach (['status' => 'status', 'program_id' => 'program_id', 'customer_id' => 'customer_id', 'employee_id' => 'created_by'] as $k => $col) {
            if ($f[$k] !== null) {
                $q->where($col, $f[$k]);
            }
        }
        if ($f['travel_from']) {
            $q->where('start_date', '>=', $f['travel_from']);
        }
        if ($f['travel_to']) {
            $q->where('start_date', '<=', $f['travel_to']);
        }
        if ($f['supplier_id'] !== null) {
            $q->whereHas('items', fn ($i) => $i->where('supplier_id', $f['supplier_id']));
        }
        if ($f['service_type'] !== null) {
            $q->whereHas('items', fn ($i) => $i->where('service_type', $f['service_type']));
        }
        if ($f['date_from'] || $f['date_to']) {
            // bookings with a movement in the period
            $q->whereIn('booking_no', AccountStatement::query()->select('es_id')->where('es_id', 'like', 'TRB-%')
                ->where('transaction_type', 1)
                ->when($f['date_from'], fn ($a) => $a->where('crt_date', '>=', $f['date_from']))
                ->when($f['date_to'], fn ($a) => $a->where('crt_date', '<=', $f['date_to'])));
        }
        return $this->bookings = $q->orderByDesc('id')->get();
    }

    /** One row per booking (see the class comment). */
    public function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }
        $bookings = $this->bookings();
        $summaries = TourismPayments::summaries($bookings);
        $ledger = $bookings->isEmpty() ? collect() : AccountStatement::whereIn('es_id', $bookings->pluck('booking_no'))
            ->where('transaction_type', 1)
            ->when($this->f['date_from'], fn ($a) => $a->where('crt_date', '>=', $this->f['date_from']))
            ->when($this->f['date_to'], fn ($a) => $a->where('crt_date', '<=', $this->f['date_to']))
            ->orderBy('id')->get()->groupBy('es_id');

        $this->rows = [];
        foreach ($bookings as $b) {
            $rows = $ledger->get($b->booking_no, collect());
            $t = TourismLedger::totals($b, $rows);
            foreach ($rows as $r) {
                $m = InvoicePassengerLedger::marker($r);
                foreach ($m['lines'] ?? [] as $l) {
                    $this->lines[] = ['booking' => $b, 'item_id' => (int) $l['item_id'], 'role' => $m['role'] ?? null,
                        'account' => (int) $r->supp_client_id, 'amount' => (float) ($l['debit'] ?? 0) - (float) ($l['credit'] ?? 0)];
                }
            }
            $s = $summaries[$b->id];
            $this->rows[] = [
                'booking' => $b,
                'booking_no' => $b->booking_no,
                'start_date' => $b->start_date?->format('Y-m-d'),
                'customer' => (string) ($b->customer->name ?? ''),
                'program' => (string) ($b->program->name ?? 'حجز خاص'),
                'employee' => (string) ($b->owner->name ?? ''),
                'status' => $b->statusLabel(),
                'people' => $b->people(),
                'sale' => $t['sale'],
                'cost' => $t['cost'],
                'profit' => $t['profit'],
                'customer_paid' => $s['customer_paid'],
                'receivable' => $s['customer_remaining'],
                'payable' => $s['supplier_remaining'],
            ];
        }
        return $this->rows;
    }

    public function totals(): array
    {
        $rows = $this->rows();
        $t = ['bookings' => count($rows)];
        foreach (['sale', 'cost', 'profit', 'customer_paid', 'receivable', 'payable'] as $k) {
            $t[$k] = round(array_sum(array_column($rows, $k)), 2);
        }
        return $t;
    }

    /** The rows grouped by f['group_by']: [key => ['label', 'bookings', 'sale', 'cost', 'profit']]. */
    public function groups(): array
    {
        $this->rows();
        $by = $this->f['group_by'];
        $g = [];
        $add = function (string $key, string $label, int $bookingId, float $sale, float $cost) use (&$g) {
            $g[$key] ??= ['label' => $label, 'bookings' => [], 'sale' => 0.0, 'cost' => 0.0];
            $g[$key]['bookings'][$bookingId] = true;
            $g[$key]['sale'] += $sale;
            $g[$key]['cost'] += $cost;
        };

        if (in_array($by, ['service_type', 'supplier'], true)) {
            $items = TourismBookingItem::whereIn('id', array_unique(array_column($this->lines, 'item_id')) ?: [0])->get()->keyBy('id');
            $names = Supplier::whereIn('id', array_unique(array_merge(array_column($this->lines, 'account'), $items->pluck('supplier_id')->all())) ?: [0])->pluck('name', 'id');
            foreach ($this->lines as $l) {
                $item = $items->get($l['item_id']);
                $sale = $l['role'] === 'customer' ? $l['amount'] : 0.0;
                $cost = $l['role'] === 'customer' ? 0.0 : -$l['amount'];
                if ($by === 'service_type') {
                    $type = $item->service_type ?? 'other';
                    $add($type, TourismBookingItem::typeLabel($type), $l['booking']->id, $sale, $cost);
                } else {
                    $sid = $l['role'] === 'customer' ? (int) ($item->supplier_id ?? 0) : $l['account'];
                    $add('s' . $sid, (string) ($names[$sid] ?? '#' . $sid), $l['booking']->id, $sale, $cost);
                }
            }
        } else {
            foreach ($this->rows as $r) {
                $b = $r['booking'];
                [$key, $label] = match ($by) {
                    'employee' => ['u' . $b->created_by, $r['employee']],
                    'customer' => ['c' . $b->customer_id, $r['customer']],
                    'status' => [$b->status, $r['status']],
                    default => ['p' . (int) $b->program_id, $r['program']],
                };
                $add($key, $label, $b->id, $r['sale'], $r['cost']);
            }
        }

        foreach ($g as &$row) {
            $row['bookings'] = count($row['bookings']);
            $row['sale'] = round($row['sale'], 2);
            $row['cost'] = round($row['cost'], 2);
            $row['profit'] = round($row['sale'] - $row['cost'], 2);
        }
        unset($row);
        uasort($g, fn ($a, $b) => $b['profit'] <=> $a['profit']);
        return $g;
    }

    /** Filter chips for the print header: label => value. */
    public function filterLabels(): array
    {
        $f = $this->f;
        $out = [];
        if ($f['date_from'] || $f['date_to']) $out['تاريخ الحركة'] = ($f['date_from'] ?: '—') . ' : ' . ($f['date_to'] ?: '—');
        if ($f['travel_from'] || $f['travel_to']) $out['تاريخ الرحلة'] = ($f['travel_from'] ?: '—') . ' : ' . ($f['travel_to'] ?: '—');
        if ($f['status']) $out['الحالة'] = TourismBooking::STATUSES[$f['status']];
        if ($f['program_id']) $out['البرنامج'] = (string) TourismProgram::whereKey($f['program_id'])->value('name');
        if ($f['customer_id']) $out['العميل'] = (string) Supplier::whereKey($f['customer_id'])->value('name');
        if ($f['supplier_id']) $out['المورد'] = (string) Supplier::whereKey($f['supplier_id'])->value('name');
        if ($f['employee_id']) $out['الموظف'] = (string) User::whereKey($f['employee_id'])->value('name');
        if ($f['service_type']) $out['نوع الخدمة'] = TourismBookingItem::typeLabel($f['service_type']);
        return $out;
    }

    /** Excel sheet rows (header, bookings, total) -- InvoiceFullReportSheet. */
    public function table(): array
    {
        $out = [['رقم الحجز', 'تاريخ الرحلة', 'العميل', 'البرنامج', 'الموظف', 'الحالة', 'الأفراد', 'المبيعات', 'التكلفة', 'الربح', 'المدفوع من العميل', 'المتبقي على العميل', 'المتبقي للموردين']];
        foreach ($this->rows() as $r) {
            $out[] = [$r['booking_no'], $r['start_date'] ?? '', $r['customer'], $r['program'], $r['employee'], $r['status'], $r['people'],
                $r['sale'], $r['cost'], $r['profit'], $r['customer_paid'], $r['receivable'], $r['payable']];
        }
        $t = $this->totals();
        $out[] = ['الإجمالي', '', '', '', '', '', $t['bookings'] . ' حجز', $t['sale'], $t['cost'], $t['profit'], $t['customer_paid'], $t['receivable'], $t['payable']];
        return $out;
    }

    /**
     * Operation list of one service type (hotel: rooming list, transport: transport list):
     * the active services of confirmed bookings visible to $viewer, with their booking and
     * people. Filters: date_from / date_to (the service's start date), supplier_id, program_id.
     */
    public static function operations(string $type, array $input, User $viewer): Collection
    {
        $date = fn ($k) => isset($input[$k]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input[$k]) ? $input[$k] : null;
        $int = fn ($k) => isset($input[$k]) && ctype_digit((string) $input[$k]) ? (int) $input[$k] : null;
        $visible = TourismBooking::visibleTo($viewer)->where('status', TourismBooking::CONFIRMED)
            ->when($int('program_id'), fn ($q, $v) => $q->where('program_id', $v))->select('id');

        return TourismBookingItem::with(['supplier:id,name', 'booking.customer:id,name', 'booking.passengers', 'booking.program:id,name'])
            ->where('service_type', $type)->where('status', TourismBookingItem::ACTIVE)
            ->whereIn('booking_id', $visible)
            ->when($date('date_from'), fn ($q, $v) => $q->where('start_date', '>=', $v))
            ->when($date('date_to'), fn ($q, $v) => $q->where('start_date', '<=', $v))
            ->when($int('supplier_id'), fn ($q, $v) => $q->where('supplier_id', $v))
            ->orderBy('start_date')->orderBy('supplier_id')->orderBy('booking_id')->get();
    }
}
