<?php

namespace App\Services;

use App\Models\AccountStatement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Detailed invoice report («التقرير التفصيلي للفواتير», /invoices/full-report).
 *
 * Read-only: one row per ticket (passenger), built from the existing invoice data
 * with the same rules as the main invoices list (InvoicesController::invoicesListData):
 *
 *   sale / re-issue (FLY-A, FLY-RS): purchase = client_net_pice, sale = client_bought_price
 *       (the passengers' current amounts), profit = sale - purchase.
 *       A re-issue is its own full sale, as the ledger books it.
 *   refund (FLY-RD): «مرتجع لنا من المورد» / «مسترد للعميل» from the refund's own ledger
 *       rows (so an edit of the refund is included), split per passenger with
 *       AccountStatement::passengerAmounts(); profit = supplier return - client refund.
 *       A refund is never counted as a sale.
 *
 * Commission (as the employee report, invoices/employee_log_view):
 *   normal invoice: profit x the creator's commission %
 *   shared invoice: profit x the stored rate of each employee (invoice_account_1_comm /
 *       invoice_account_2_comm): for one employee (employee filter, or an employee's own
 *       view) that employee's rate only, otherwise both rates.
 *   Refund rows use the same formula on the refund's profit (existing behaviour).
 *
 * Dates: date_from / date_to filter the invoice date; travel_from / travel_to the invoice's
 * travel date (invoice_travel_date -- one per invoice, shared by all its passengers).
 *
 * Access (as before): account_type 2 sees every invoice; an employee sees the normal
 * invoices they created and the shared invoices they take part in (either account)
 * or created.
 *
 * The whole filtered set is built with a fixed number of queries (no per-invoice
 * lookups); search, sort, paging, totals, groups, Excel and print all use these
 * same rows, so they always agree.
 */
class InvoiceFullReport
{
    const SECTIONS = [1 => 'فواتير الطيران', 2 => 'فواتير تأشيرات', 3 => 'فواتير سياحه داخليه', 4 => 'فواتير سياحه خارجيه',
        5 => 'فواتير سياحه دينيه', 6 => 'فواتير تأمينات السفر', 7 => 'فواتير تحاليل السفر', 8 => 'فواتير نقل سياحى'];

    const OPS = ['sale' => 'بيع', 'reissue' => 'إعادة إصدار', 'refund' => 'مرتجع', 'edited' => 'فواتير بها تعديل'];

    const TYPES = ['normal' => 'عادية', 'shared' => 'مشتركة', 'counter' => 'عميل كونتر'];

    const GROUPS = ['employee' => 'الموظف', 'airline' => 'شركة الطيران', 'customer' => 'العميل',
        'supplier' => 'المورد', 'day' => 'اليوم', 'op' => 'نوع العملية'];

    /** Sortable / searchable row keys (DataTables column data names). */
    const SORTABLE = ['es_id', 'op_label', 'type_label', 'invoice_date', 'travel_date', 'passenger', 'pnr', 'ticket',
        'route', 'airline', 'customer', 'supplier', 'employee', 'purchase', 'sale', 'supplier_return',
        'client_refund', 'profit', 'commission'];

    /** Normalised filters. */
    public array $f;

    protected $user;

    /** Employee the commission is computed for (null = all employees). */
    protected ?int $ctx;

    protected ?Collection $rows = null;

    protected array $users = [];

    public function __construct(array $input, $user)
    {
        $this->user = $user;
        $date = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : null;
        $int = fn ($v) => ctype_digit((string) $v) && (int) $v > 0 ? (int) $v : null;
        $this->f = [
            'date_from' => $date($input['date_from'] ?? null),
            'date_to' => $date($input['date_to'] ?? null),
            'travel_from' => $date($input['travel_from'] ?? null),
            'travel_to' => $date($input['travel_to'] ?? null),
            'customer_id' => $int($input['customer_id'] ?? null),
            'supplier_id' => $int($input['supplier_id'] ?? null),
            'employee_id' => $this->isAdmin() ? $int($input['employee_id'] ?? null) : null,
            'airline' => trim((string) ($input['airline'] ?? '')) ?: null,
            'section' => isset(self::SECTIONS[(int) ($input['section'] ?? 0)]) ? (int) $input['section'] : null,
            'op' => isset(self::OPS[$input['op'] ?? '']) ? $input['op'] : null,
            'type' => isset(self::TYPES[$input['type'] ?? '']) ? $input['type'] : null,
            'q' => trim((string) ($input['q'] ?? '')) ?: null,
            'search' => trim((string) ($input['search'] ?? '')) ?: null,
        ];
        $this->ctx = $this->isAdmin() ? $this->f['employee_id'] : (int) $user->id;
    }

    public function isAdmin(): bool
    {
        return (int) $this->user->account_type === 2;
    }

    /** The filters as query parameters (for links to Excel / print). */
    public function query(): array
    {
        return array_filter($this->f, fn ($v) => $v !== null);
    }

    // ------------------------------------------------------------------ rows

    /** All report rows for the current filters (and search). */
    public function rows(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $q = DB::table('invoices as i');
        $shared = fn ($w) => $w->where('i.invoice_shared', 1);
        $normal = fn ($w) => $w->whereRaw('COALESCE(i.invoice_shared, 0) <> 1');

        if (!$this->isAdmin()) {
            $me = (int) $this->user->id;
            $q->where(fn ($w) => $w
                ->where(fn ($a) => $normal($a)->where('i.invoice_create_by', $me))
                ->orWhere(fn ($b) => $shared($b)->where(fn ($c) => $c->where('i.invoice_account_1', $me)
                    ->orWhere('i.invoice_account_2', $me)->orWhere('i.invoice_create_by', $me))));
        } elseif ($this->f['employee_id']) {
            $emp = $this->f['employee_id'];
            $q->where(fn ($w) => $w
                ->where(fn ($a) => $normal($a)->where('i.invoice_create_by', $emp))
                ->orWhere(fn ($b) => $shared($b)->where(fn ($c) => $c->where('i.invoice_account_1', $emp)->orWhere('i.invoice_account_2', $emp))));
        }

        // same date rule as the invoices list: the invoice date
        if ($this->f['date_from']) {
            $q->whereDate('i.invoice_date', '>=', $this->f['date_from']);
        }
        if ($this->f['date_to']) {
            $q->whereDate('i.invoice_date', '<=', $this->f['date_to']);
        }
        // travel date: one per invoice (invoices.invoice_travel_date), shared by all its passengers
        if ($this->f['travel_from']) {
            $q->whereDate('i.invoice_travel_date', '>=', $this->f['travel_from']);
        }
        if ($this->f['travel_to']) {
            $q->whereDate('i.invoice_travel_date', '<=', $this->f['travel_to']);
        }
        if ($this->f['customer_id']) {
            $q->where('i.invoice_beneficiaries', $this->f['customer_id']);
        }
        if ($this->f['airline']) {
            $q->where('i.invoice_airline', $this->f['airline']);
        }
        if ($this->f['section']) {
            $q->where('i.invoice_section', $this->f['section']);
        }
        $counterIds = CounterPayments::counterIds();
        if ($this->f['type'] === 'shared') {
            $shared($q);
        } elseif ($this->f['type'] === 'counter') {
            $q->whereIn('i.invoice_beneficiaries', $counterIds ?: [0]);
        } elseif ($this->f['type'] === 'normal') {
            $normal($q);
            if ($counterIds) {
                $q->whereNotIn('i.invoice_beneficiaries', $counterIds);
            }
        }
        if ($this->f['supplier_id']) {
            // ticket_system_id is an unindexed text column: resolve the supplier's tickets once
            $q->whereIn('i.ticket_system_id', DB::table('ticket_vendors')->where('vendor_id', $this->f['supplier_id'])
                ->distinct()->pluck('ticket_system_id')->all() ?: ['']);
        }

        $invoices = $q->orderBy('i.invoice_date', 'desc')->orderBy('i.id', 'desc')
            ->get(['i.id', 'i.es_id', 'i.ticket_system_id', 'i.invoice_date', 'i.created_at', 'i.invoice_travel_date',
                'i.invoice_airline', 'i.from_location', 'i.to_location', 'i.invoice_beneficiaries', 'i.invoice_section',
                'i.invoice_shared', 'i.invoice_account_1', 'i.invoice_account_1_comm', 'i.invoice_account_2',
                'i.invoice_account_2_comm', 'i.invoice_create_by']);

        // batched lookups: one query each, whatever the number of invoices
        $systems = $invoices->pluck('ticket_system_id')->filter()->unique()->values()->all();
        $esIds = $invoices->pluck('es_id')->filter()->unique()->values()->all();
        $refundEsIds = array_values(array_filter($esIds, fn ($e) => str_starts_with((string) $e, 'FLY-RD')));

        $passengers = $systems ? DB::table('ticket_users')->whereIn('ticket_system_id', $systems)->orderBy('id')
            ->get(['id', 'ticket_system_id', 'client_name', 'client_booking_id', 'client_ticket_id', 'client_net_pice', 'client_bought_price'])
            ->groupBy('ticket_system_id') : collect();
        $vendors = $systems ? DB::table('ticket_vendors as tv')->leftJoin('suppliers as s', 's.id', '=', 'tv.vendor_id')
            ->whereIn('tv.ticket_system_id', $systems)->orderBy('tv.id')
            ->get(['tv.ticket_system_id', 'tv.vendor_id', 's.name'])->groupBy('ticket_system_id') : collect();
        $customers = DB::table('suppliers')->whereIn('id', $invoices->pluck('invoice_beneficiaries')->filter()->unique()->values())
            ->pluck('name', 'id');
        $this->users = DB::table('users')->get(['id', 'name', 'commission'])->keyBy('id')->all();
        $refundRows = $refundEsIds ? AccountStatement::whereIn('es_id', $refundEsIds)->where('transaction_type', 1)
            ->orderBy('id')->get(['es_id', 'supp_client_id', 'transaction_type', 'debit_balance', 'credit_balance'])->groupBy('es_id') : collect();
        $markedRows = $esIds ? AccountStatement::whereIn('es_id', $esIds)->where('transaction_type', 1)
            ->where('description', 'like', '{%')->orderBy('id')->get(['es_id', 'description', 'crt_date'])->groupBy('es_id') : collect();

        $rows = [];
        foreach ($invoices as $inv) {
            $pax = $passengers->get($inv->ticket_system_id, collect())->values();
            $vend = $vendors->get($inv->ticket_system_id, collect());
            $vendorId = optional($vend->first())->vendor_id;
            $isRefund = str_starts_with((string) $inv->es_id, 'FLY-RD');
            $isShared = (int) $inv->invoice_shared === 1;
            $isCounter = CounterPayments::isCounterClient($inv->invoice_beneficiaries);

            // operation kind: explicit data only (number prefix, row markers), as the invoices list
            $markers = $markedRows->get($inv->es_id, collect())->map(fn ($r) => ['m' => InvoicePassengerLedger::marker($r), 'date' => (string) $r->crt_date])
                ->filter(fn ($x) => $x['m'] !== null);
            $refundMarker = ($markers->first(fn ($x) => ($x['m']['kind'] ?? '') === 'refund') ?? [])['m'] ?? null;
            $edits = $markers->filter(fn ($x) => ($x['m']['kind'] ?? '') === 'edit');
            if ($isRefund) {
                $op = 'refund';
                $opLabel = !$refundMarker ? 'مرتجع'
                    : (($refundMarker['mode'] ?? '') === 'single' ? 'مرتجع تذكرة' : 'مرتجع كامل للفاتورة');
            } elseif (str_starts_with((string) $inv->es_id, 'FLY-RS') || $markers->contains(fn ($x) => ($x['m']['kind'] ?? '') === 'reissue')) {
                $op = 'reissue';
                $opLabel = 'إعادة إصدار';
            } else {
                $op = 'sale';
                $opLabel = 'بيع';
            }

            // refund amounts per account from the refund's ledger rows (includes its edits)
            $supRet = $cliRef = [];
            if ($isRefund) {
                $t = InvoicePassengerLedger::refundTotalsFromRows($refundRows->get($inv->es_id, collect()), $vendorId, $inv->invoice_beneficiaries);
                $supRet = AccountStatement::passengerAmounts($pax, 'client_bought_price', $t['supplier']);
                $cliRef = AccountStatement::passengerAmounts($pax, 'client_net_pice', $t['client']);
            }

            $a1 = (int) $inv->invoice_account_1;
            $a2 = (int) $inv->invoice_account_2;
            $r1 = (float) $inv->invoice_account_1_comm;
            $r2 = (float) $inv->invoice_account_2_comm;
            $creator = (int) $inv->invoice_create_by;
            if ($isShared) {
                $rate = $this->ctx === null ? $r1 + $r2 : ($this->ctx === $a1 ? $r1 : ($this->ctx === $a2 ? $r2 : 0.0));
                $employee = $this->userName($a1) . ' / ' . $this->userName($a2);
                $rateLabel = $this->ctx === null ? self::num($r1) . '% + ' . self::num($r2) . '%' : self::num($rate) . '%';
            } else {
                $rate = $this->userRate($creator);
                $employee = $this->userName($creator);
                $rateLabel = self::num($rate) . '%';
            }

            $base = [
                'id' => (int) $inv->id,
                'es_id' => (string) $inv->es_id,
                'op' => $op,
                'op_label' => $opLabel,
                'edits' => $edits->count(),
                'last_edit' => $edits->isEmpty() ? null : $edits->last()['date'],
                'type' => $isShared ? 'shared' : ($isCounter ? 'counter' : 'normal'),
                'type_label' => $isShared ? 'مشتركة' . ($isCounter ? ' - كونتر' : '') : ($isCounter ? 'عميل كونتر' : 'عادية'),
                'invoice_date' => (string) ($inv->invoice_date ?: substr((string) $inv->created_at, 0, 10)),
                'travel_date' => (string) $inv->invoice_travel_date,
                'route' => trim((string) $inv->from_location) !== '' || trim((string) $inv->to_location) !== ''
                    ? trim($inv->from_location . ' - ' . $inv->to_location) : '',
                'airline' => trim((string) $inv->invoice_airline),
                'section' => self::SECTIONS[(int) $inv->invoice_section] ?? '',
                'customer_id' => (int) $inv->invoice_beneficiaries,
                'customer' => (string) ($customers[$inv->invoice_beneficiaries] ?? ''),
                'supplier_id' => $vendorId === null ? null : (int) $vendorId,
                'supplier' => $vend->pluck('name')->filter()->implode(' / '),
                'employee' => $employee,
                'shared' => $isShared ? [[$a1, $r1], [$a2, $r2]] : null,
                'creator' => $creator,
                'rate' => $rate,
                'rate_label' => $rateLabel,
            ];

            if ($pax->isEmpty()) {
                $pax = collect([(object) ['client_name' => '', 'client_booking_id' => '', 'client_ticket_id' => '', 'client_net_pice' => 0, 'client_bought_price' => 0]]);
            }
            foreach ($pax as $i => $p) {
                if ($isRefund) {
                    $purchase = $sale = 0.0;
                    $sr = (float) ($supRet[$i] ?? 0);
                    $cr = (float) ($cliRef[$i] ?? 0);
                    $profit = $sr - $cr;
                } else {
                    $purchase = (float) $p->client_net_pice;
                    $sale = (float) $p->client_bought_price;
                    $sr = $cr = 0.0;
                    $profit = $sale - $purchase;
                }
                $rows[] = $base + [
                    'passenger' => (string) $p->client_name,
                    'pnr' => (string) $p->client_booking_id,
                    'ticket' => (string) $p->client_ticket_id,
                    'purchase' => $purchase,
                    'sale' => $sale,
                    'supplier_return' => $sr,
                    'client_refund' => $cr,
                    'profit' => $profit,
                    'commission' => $profit * $rate / 100,
                ];
            }
        }

        $rows = collect($rows);
        if ($this->f['op']) {
            $rows = $this->f['op'] === 'edited' ? $rows->filter(fn ($r) => $r['edits'] > 0) : $rows->where('op', $this->f['op']);
        }
        if ($this->f['q']) {
            $needle = $this->f['q'];
            $rows = $rows->filter(fn ($r) => mb_stripos($r['es_id'], $needle) !== false
                || mb_stripos($r['pnr'], $needle) !== false || mb_stripos($r['ticket'], $needle) !== false);
        }
        if ($this->f['search']) {
            $needle = $this->f['search'];
            $rows = $rows->filter(function ($r) use ($needle) {
                foreach (['es_id', 'op_label', 'type_label', 'invoice_date', 'travel_date', 'passenger', 'pnr', 'ticket',
                          'route', 'airline', 'customer', 'supplier', 'employee'] as $k) {
                    if (mb_stripos((string) $r[$k], $needle) !== false) {
                        return true;
                    }
                }
                return false;
            });
        }

        return $this->rows = $rows->values();
    }

    /** Rows sorted by a report column (default: newest invoice first). */
    public function sorted(?string $key = null, string $dir = 'desc'): Collection
    {
        $rows = $this->rows();
        if (!in_array($key, self::SORTABLE, true)) {
            return $rows;                                   // already newest first
        }
        $numeric = in_array($key, ['purchase', 'sale', 'supplier_return', 'client_refund', 'profit', 'commission'], true);
        $sorted = $rows->sort(function ($a, $b) use ($key, $numeric) {
            $c = $numeric ? $a[$key] <=> $b[$key] : strcmp((string) $a[$key], (string) $b[$key]);
            return $c !== 0 ? $c : $b['id'] <=> $a['id'];
        });
        return ($dir === 'asc' ? $sorted : $sorted->reverse())->values();
    }

    // ------------------------------------------------------------------ totals

    /** Totals of a set of rows. */
    public static function totals($rows): array
    {
        $rows = collect($rows);
        $sales = $rows->where('op', '!=', 'refund');
        $refunds = $rows->where('op', 'refund');
        $t = [
            'invoices' => $rows->pluck('id')->unique()->count(),
            'tickets' => $sales->count(),
            'refund_tickets' => $refunds->count(),
            'sale' => $sales->sum('sale'),
            'purchase' => $sales->sum('purchase'),
            'gross_profit' => $sales->sum('profit'),
            'supplier_return' => $refunds->sum('supplier_return'),
            'client_refund' => $refunds->sum('client_refund'),
            'refund_net' => $refunds->sum('profit'),
            'commission' => $rows->sum('commission'),
        ];
        $t['net_profit'] = $t['gross_profit'] + $t['refund_net'];
        return array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $t);
    }

    public function summary(): array
    {
        return self::totals($this->rows());
    }

    /**
     * Totals grouped by employee / airline / customer / supplier / day / operation type.
     * By employee, a shared invoice counts under each of its two employees (its sale and
     * profit under both, the commission at each one's own rate), so the employee rows
     * can add up to more than the overall totals.
     */
    public function groups(string $by): array
    {
        $groups = [];
        $add = function ($key, $label, array $row) use (&$groups) {
            $groups[$key]['label'] = $label;
            $groups[$key]['rows'][] = $row;
        };
        foreach ($this->rows() as $r) {
            switch ($by) {
                case 'employee':
                    if ($r['shared']) {
                        foreach ($r['shared'] as [$uid, $rate]) {
                            if ($this->ctx !== null && $uid !== $this->ctx) {
                                continue;
                            }
                            $add('u' . $uid, $this->userName($uid), ['commission' => $r['profit'] * $rate / 100, 'shared_part' => 1] + $r);
                        }
                    } else {
                        $add('u' . $r['creator'], $this->userName($r['creator']), $r);
                    }
                    break;
                case 'airline':
                    $add('a' . $r['airline'], $r['airline'] !== '' ? $r['airline'] : '—', $r);
                    break;
                case 'customer':
                    $add('c' . $r['customer_id'], $r['customer'] !== '' ? $r['customer'] : '—', $r);
                    break;
                case 'supplier':
                    $add('s' . $r['supplier_id'], $r['supplier'] !== '' ? $r['supplier'] : '—', $r);
                    break;
                case 'day':
                    $add('d' . $r['invoice_date'], $r['invoice_date'], $r);
                    break;
                default:
                    $add('o' . $r['op'], self::OPS[$r['op']], $r);
            }
        }
        $out = [];
        foreach ($groups as $g) {
            $out[] = ['label' => $g['label'], 'shared_tickets' => collect($g['rows'])->sum(fn ($r) => $r['shared_part'] ?? 0)]
                + self::totals($g['rows']);
        }
        usort($out, $by === 'day' ? fn ($a, $b) => strcmp($b['label'], $a['label']) : fn ($a, $b) => $b['net_profit'] <=> $a['net_profit']);
        return $out;
    }

    // ------------------------------------------------------------------ labels

    /** Readable active filters: label => value (names resolved). */
    public function filterLabels(): array
    {
        $f = $this->f;
        $out = [];
        if ($f['date_from'] || $f['date_to']) {
            $out['الفترة (تاريخ الفاتورة)'] = ($f['date_from'] ?: '—') . ' : ' . ($f['date_to'] ?: '—');
        }
        if ($f['travel_from'] || $f['travel_to']) {
            $out['تاريخ السفر'] = $f['travel_from'] && $f['travel_from'] === $f['travel_to']
                ? $f['travel_from'] . ($f['travel_from'] === date('Y-m-d') ? ' (سفر اليوم)' : '')
                : ($f['travel_from'] ?: '—') . ' : ' . ($f['travel_to'] ?: '—');
        }
        if ($f['customer_id']) {
            $out['العميل'] = (string) DB::table('suppliers')->where('id', $f['customer_id'])->value('name');
        }
        if ($f['supplier_id']) {
            $out['المورد'] = (string) DB::table('suppliers')->where('id', $f['supplier_id'])->value('name');
        }
        if (!$this->isAdmin()) {
            $out['الموظف'] = (string) $this->user->name;
        } elseif ($f['employee_id']) {
            $out['الموظف'] = (string) DB::table('users')->where('id', $f['employee_id'])->value('name');
        }
        if ($f['airline']) {
            $out['شركة الطيران'] = $f['airline'];
        }
        if ($f['section']) {
            $out['القسم'] = self::SECTIONS[$f['section']];
        }
        if ($f['op']) {
            $out['نوع العملية'] = self::OPS[$f['op']];
        }
        if ($f['type']) {
            $out['نوع الفاتورة'] = self::TYPES[$f['type']];
        }
        if ($f['q']) {
            $out['رقم الفاتورة / PNR / التذكرة'] = $f['q'];
        }
        if ($f['search']) {
            $out['بحث'] = $f['search'];
        }
        return $out;
    }

    protected function userName($id): string
    {
        return isset($this->users[$id]) ? (string) $this->users[$id]->name : ($id ? '#' . $id : '—');
    }

    /** An employee's commission % as the existing reports read it ((int) of the stored text, e.g. "10%"). */
    protected function userRate($id): float
    {
        return isset($this->users[$id]) ? (float) (int) $this->users[$id]->commission : 0.0;
    }

    public static function num($v): string
    {
        $v = round((float) $v, 2);
        return $v == (int) $v ? (string) (int) $v : number_format($v, 2, '.', '');
    }

    public static function money($v): string
    {
        return number_format(round((float) $v, 2), 2, '.', ',');
    }
}
