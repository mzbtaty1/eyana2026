<?php

namespace App\Services;

use App\Models\{AccountStatement, Bank, Bond, Collector, Invoice, User};

/**
 * One account's ledger for the account overview: movements by kind («تفصيل الحركات»),
 * the latest movements with the balance after each («آخر الحركات»), and its vouchers
 * («السندات»). The rows are exactly the account statement's (is_storage != 1), in the
 * statement's order (crt_date, then id); every row falls in one kind, so the kinds
 * always add up to the statement's total debit / credit / balance. Kinds from the
 * existing rules only:
 *
 *   ticket rows (transaction_type 1)  InvoicePassengerLedger::rowKind(): sale | reissue |
 *                                     edit | refund (marker, else FLY-RS / FLY-RD prefix)
 *   payment voucher  «سند دفع»        transaction_type 2, invoice_type 9
 *   receipt voucher  «سند قبض»        transaction_type 2, invoice_type 10
 *   invoice payment  «سداد فاتورة»    transaction_type 4 (Counter Customer payments)
 *   opening balance                   es_id FLY-OPEN-BALANCE / transaction_type 3 -- only
 *                                     when booked in the ledger (the account record's
 *                                     opening fields are not used)
 *   other                             anything else (kept so nothing is ever dropped)
 *
 * Debit and credit are kept apart for every kind (a kind is never assumed to be one-sided).
 */
class AccountMovements
{
    /** How many latest movements / vouchers the overview shows. */
    const LATEST = 20;

    /** Display order, label and a short explanation of each kind. */
    const KINDS = [
        'sale' => ['فواتير', 'مدين: بيع للحساب كعميل · دائن: شراء منه كمورد'],
        'reissue' => ['إعادة إصدار', 'مدين: للحساب كعميل · دائن: للحساب كمورد'],
        'edit' => ['تعديلات فواتير', 'فروق تعديل فواتير قائمة'],
        'refund' => ['مرتجعات', 'مدين: مرتجع لنا من المورد · دائن: مسترد للعميل'],
        'payment' => ['سندات دفع', 'مبالغ دفعناها للحساب'],
        'receipt' => ['سندات قبض', 'مبالغ قبضناها من الحساب'],
        'invoice_payment' => ['سداد فواتير', 'سداد على فواتير عميل الكونتر'],
        'opening' => ['رصيد افتتاحي', 'المقيد في كشف الحساب فقط'],
        'other' => ['أخرى', 'حركات لا تنطبق عليها الأنواع السابقة'],
    ];

    /** Label of a single movement of each non-ticket kind (ticket rows use kindLabel()). */
    const ROW_LABELS = [
        'payment' => 'سند دفع', 'receipt' => 'سند قبض', 'invoice_payment' => 'سداد',
        'opening' => 'رصيد افتتاحي', 'other' => 'أخرى',
    ];

    /** The kind of one statement row (see the class comment). */
    public static function kindOf($row): string
    {
        if ($row->es_id === 'FLY-OPEN-BALANCE' || (int) $row->transaction_type === 3) {
            return 'opening';
        }
        $ticket = InvoicePassengerLedger::rowKind($row);
        if ($ticket !== null) {
            return $ticket;
        }
        if ((int) $row->transaction_type === 2) {
            return match ((int) $row->invoice_type) { 9 => 'payment', 10 => 'receipt', default => 'other' };
        }
        return (int) $row->transaction_type === 4 ? 'invoice_payment' : 'other';
    }

    /**
     * [
     *   'kinds'  => [kind => (object) {key, label, hint, count, debit, credit}] (kinds with rows, in KINDS order),
     *   'rows'   => n, 'debit' => total, 'credit' => total,
     *   'latest' => the last LATEST rows, newest first: (object) {row, kind, label, section,
     *               balance_after, invoice (id|null), bond (id|null), employee (name|null)}
     * ]
     * One ledger query plus one each for the latest rows' invoices, vouchers and employees.
     * Summed in cents, so the kinds and the running balance are exact.
     */
    public static function forAccount(int $accountId): array
    {
        $rows = AccountStatement::where('supp_client_id', $accountId)
            ->where('is_storage', '!=', 1)
            ->orderBy('crt_date')->orderBy('id')   // the account statement's order
            ->get(['id', 'es_id', 'transaction_type', 'invoice_type', 'debit_balance', 'credit_balance',
                'description', 'crt_date', 'transaction_txt', 'added_by']);

        $cents = [];
        $running = 0;
        $after = [];
        foreach ($rows as $i => $r) {
            $k = self::kindOf($r);
            $d = (int) round($r->debit_balance * 100);
            $c = (int) round($r->credit_balance * 100);
            $cents[$k]['n'] = ($cents[$k]['n'] ?? 0) + 1;
            $cents[$k]['d'] = ($cents[$k]['d'] ?? 0) + $d;
            $cents[$k]['c'] = ($cents[$k]['c'] ?? 0) + $c;
            $running += $d - $c;
            $after[$i] = $running;
        }

        $kinds = [];
        foreach (self::KINDS as $key => [$label, $hint]) {
            if (isset($cents[$key])) {
                $kinds[$key] = (object) [
                    'key' => $key, 'label' => $label, 'hint' => $hint, 'count' => $cents[$key]['n'],
                    'debit' => $cents[$key]['d'] / 100, 'credit' => $cents[$key]['c'] / 100,
                ];
            }
        }

        return [
            'kinds' => $kinds,
            'rows' => $rows->count(),
            'debit' => array_sum(array_column($cents, 'd')) / 100,
            'credit' => array_sum(array_column($cents, 'c')) / 100,
            'latest' => self::latest($rows, $after),
        ];
    }

    /** The last LATEST rows, newest first, with labels, links and the balance after each. */
    private static function latest($rows, array $after): array
    {
        $last = $rows->slice(-self::LATEST)->reverse();
        $esIds = $last->pluck('es_id')->filter()->unique()->values();
        $invoices = Invoice::whereIn('es_id', $esIds)->get(['id', 'es_id', 'invoice_section', 'invoice_shared'])->keyBy('es_id');
        $bonds = Bond::whereIn('es_id', $esIds)->pluck('id', 'es_id');
        $users = User::whereIn('id', $last->pluck('added_by')->filter()->unique()->values())->pluck('name', 'id');

        $out = [];
        foreach ($last as $i => $r) {
            $kind = self::kindOf($r);
            $invoice = $invoices->get($r->es_id);
            $ticket = InvoicePassengerLedger::rowKind($r) !== null || $kind === 'invoice_payment';
            $out[] = (object) [
                'row' => $r,
                'kind' => $kind,
                'label' => $ticket ? InvoicePassengerLedger::kindLabel($r, $invoice) : self::ROW_LABELS[$kind],
                'section' => $invoice && (int) $r->transaction_type === 1 ? (InvoiceFullReport::SECTIONS[(int) $invoice->invoice_section] ?? null) : null,
                'balance_after' => $after[$i] / 100,
                'invoice' => $invoice ? (int) $invoice->id : null,
                'bond' => (int) $r->transaction_type === 2 && isset($bonds[$r->es_id]) ? (int) $bonds[$r->es_id] : null,
                'employee' => $users[$r->added_by] ?? null,
            ];
        }
        return $out;
    }

    /**
     * The account's vouchers (bonds from or to it -- Counter Customer invoice payments
     * included), newest first: ['count' => all, 'latest' => up to LATEST (object) {bond,
     * direction 'receipt' (from the account: «سند قبض») | 'payment' (to it: «سند دفع»),
     * method, invoice (object {id, es_id}|null), missing_invoice (id of a linked invoice
     * that no longer exists|null)}]. A list with links only: every money
     * figure on the overview comes from the ledger, never from bonds.amount.
     */
    public static function vouchers(int $accountId): array
    {
        $mine = fn ($q) => $q->where(fn ($b) => $b->where('from_type', 'supplier')->where('from_account', $accountId))
            ->orWhere(fn ($b) => $b->where('to_type', 'supplier')->where('to_account', $accountId));

        $count = Bond::where($mine)->count();
        $bonds = Bond::where($mine)->orderByDesc('crt_date')->orderByDesc('id')->limit(self::LATEST)
            ->get(['id', 'es_id', 'type', 'from_type', 'from_account', 'to_type', 'to_account', 'amount',
                'money_way', 'bank_id', 'collector_info', 'crt_date', 'bond_status', 'is_invoice', 'invoice_id', 'info']);

        $banks = Bank::whereIn('id', $bonds->where('money_way', 2)->pluck('bank_id')->filter()->unique()->values())->pluck('bank_name', 'id');
        $collectors = Collector::whereIn('id', $bonds->whereNotIn('money_way', [1, 2])->pluck('collector_info')->filter()->unique()->values())->pluck('name', 'id');
        $invoices = Invoice::whereIn('id', $bonds->pluck('invoice_id')->filter()->unique()->values())->get(['id', 'es_id'])->keyBy('id');

        return [
            'count' => $count,
            'latest' => $bonds->map(fn ($b) => (object) [
                'bond' => $b,
                'direction' => $b->from_type === 'supplier' && (int) $b->from_account === $accountId ? 'receipt' : 'payment',
                // same wording as the vouchers list (moneyarea/bonds/all)
                'method' => match ((int) $b->money_way) {
                    1 => 'دفع نقدي',
                    2 => trim('تحويل بنكي ' . ($banks[$b->bank_id] ?? '')),
                    default => trim('تحصيل من المندوب: ' . ($collectors[$b->collector_info] ?? '')),
                },
                'invoice' => $b->invoice_id ? $invoices->get($b->invoice_id) : null,
                // linked to an invoice that no longer exists (its ledger rows are gone with it)
                'missing_invoice' => $b->invoice_id && ! $invoices->has($b->invoice_id) ? (int) $b->invoice_id : null,
            ])->all(),
        ];
    }
}
