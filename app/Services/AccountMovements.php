<?php

namespace App\Services;

use App\Models\AccountStatement;

/**
 * One account's ledger broken down by kind of movement (account overview, «تفصيل الحركات»).
 * The rows are exactly the account statement's (is_storage != 1); every row falls in one
 * kind, so the kinds always add up to the statement's total debit / credit / balance.
 * One query; kinds from the existing rules only:
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
     * ['kinds' => [kind => (object) {key, label, hint, count, debit, credit}] (kinds with rows,
     * in KINDS order), 'rows' => n, 'debit' => total, 'credit' => total]. Summed in cents,
     * so the kinds add up exactly.
     */
    public static function forAccount(int $accountId): array
    {
        $rows = AccountStatement::where('supp_client_id', $accountId)
            ->where('is_storage', '!=', 1)
            ->get(['id', 'es_id', 'transaction_type', 'invoice_type', 'debit_balance', 'credit_balance', 'description']);

        $cents = [];
        foreach ($rows as $r) {
            $k = self::kindOf($r);
            $cents[$k]['n'] = ($cents[$k]['n'] ?? 0) + 1;
            $cents[$k]['d'] = ($cents[$k]['d'] ?? 0) + (int) round($r->debit_balance * 100);
            $cents[$k]['c'] = ($cents[$k]['c'] ?? 0) + (int) round($r->credit_balance * 100);
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
        ];
    }
}
