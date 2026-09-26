<?php

namespace App\Services;

/**
 * One account's invoices for the account overview («تحليل الفواتير»), straight from the
 * detailed invoice report (InvoiceFullReport, filtered by supplier_id / customer_id) --
 * no calculation of its own. One report per role the account has invoices in (the
 * report caches its rows, so totals and the three breakdowns cost one load per role).
 *
 * Only each role's own figures are shown:
 *   as supplier  purchase («مشتريات»), supplier_return («مرتجع من المورد»), net = purchase - return
 *   as customer  sale («مبيعات»), client_refund («مسترد للعميل»), net = sale - refund
 * The report's amounts are passenger prices; on dev they equal the account's ledger
 * (AccountMovements sale / reissue / refund kinds) -- see SupplierOverviewInvoicesTest.
 */
class AccountInvoices
{
    /** role => [report filter, amount key, refund key, amount label, refund label]. */
    const ROLES = [
        'supplier' => ['supplier_id', 'purchase', 'supplier_return', 'مشتريات', 'مرتجع من المورد'],
        'customer' => ['customer_id', 'sale', 'client_refund', 'مبيعات', 'مسترد للعميل'],
    ];

    /** The report's breakdowns shown, with their titles. */
    const GROUPS = ['airline' => 'حسب شركة الطيران', 'employee' => 'حسب الموظف', 'op' => 'حسب نوع العملية'];

    /**
     * [role => (object) {role, amount_label, refund_label, invoices, tickets, refund_tickets,
     *   amount, refund, net, groups => [by => [(object) {label, invoices, tickets, amount, refund}]]}]
     * for the roles in which $row (SupplierDirectory row) has invoices.
     */
    public static function forAccount(object $row, $user): array
    {
        $out = [];
        foreach (self::ROLES as $role => [$filter, $amountKey, $refundKey, $amountLabel, $refundLabel]) {
            if (! $row->{'invoices_as_' . $role}) {
                continue;
            }
            $report = new InvoiceFullReport([$filter => $row->account->id], $user);
            $s = $report->summary();
            $groups = [];
            foreach (array_keys(self::GROUPS) as $by) {
                $groups[$by] = array_map(fn ($g) => (object) [
                    'label' => $g['label'], 'invoices' => $g['invoices'], 'tickets' => $g['tickets'],
                    'amount' => $g[$amountKey], 'refund' => $g[$refundKey],
                ], $report->groups($by));
            }
            $out[$role] = (object) [
                'role' => $role, 'amount_label' => $amountLabel, 'refund_label' => $refundLabel,
                'invoices' => $s['invoices'], 'tickets' => $s['tickets'], 'refund_tickets' => $s['refund_tickets'],
                'amount' => $s[$amountKey], 'refund' => $s[$refundKey], 'net' => round($s[$amountKey] - $s[$refundKey], 2),
                'groups' => $groups,
            ];
        }
        return $out;
    }
}
