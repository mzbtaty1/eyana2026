<?php

namespace App\Exports;

use App\Services\InvoiceFullReport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Excel of «التقرير التفصيلي للفواتير»: summary, every ticket row, and one sheet per
 * grouping -- all for the current filters, from the same rows as the screen.
 */
class InvoiceFullReportExport implements WithMultipleSheets
{
    const DETAIL_COLUMNS = [
        'es_id' => 'رقم الفاتورة', 'op_label' => 'نوع العملية', 'type_label' => 'نوع الفاتورة', 'invoice_date' => 'تاريخ الفاتورة',
        'travel_date' => 'تاريخ السفر', 'passenger' => 'الراكب', 'pnr' => 'PNR', 'ticket' => 'رقم التذكرة', 'route' => 'الرحلة',
        'airline' => 'شركة الطيران', 'customer' => 'العميل', 'supplier' => 'المورد', 'employee' => 'الموظف',
        'purchase' => 'سعر الشراء', 'sale' => 'سعر البيع', 'supplier_return' => 'مرتجع من المورد',
        'client_refund' => 'مسترد للعميل', 'profit' => 'الربح / الخسارة', 'rate_label' => 'نسبة العمولة', 'commission' => 'العمولة',
        'edits' => 'عدد التعديلات',
    ];

    const TOTAL_COLUMNS = [
        'invoices' => 'عدد الفواتير', 'tickets' => 'عدد التذاكر', 'refund_tickets' => 'تذاكر مرتجعة', 'sale' => 'إجمالي البيع',
        'purchase' => 'إجمالي الشراء', 'gross_profit' => 'إجمالي الربح', 'supplier_return' => 'مرتجع من الموردين',
        'client_refund' => 'مسترد للعملاء', 'refund_net' => 'صافي المرتجعات', 'net_profit' => 'صافي الربح', 'commission' => 'إجمالي العمولة',
    ];

    public function __construct(protected InvoiceFullReport $report)
    {
    }

    public function sheets(): array
    {
        $summary = [['التقرير التفصيلي للفواتير'], ['تاريخ الاستخراج', now()->format('Y-m-d H:i')]];
        foreach ($this->report->filterLabels() as $label => $value) {
            $summary[] = [$label, $value];
        }
        $summary[] = [];
        foreach ($this->report->summary() as $k => $v) {
            $summary[] = [self::TOTAL_COLUMNS[$k], $v];
        }

        $details = [array_values(self::DETAIL_COLUMNS)];
        foreach ($this->report->rows() as $r) {
            $details[] = array_map(fn ($k) => is_float($r[$k]) ? round($r[$k], 2) : $r[$k], array_keys(self::DETAIL_COLUMNS));
        }
        $t = $this->report->summary();
        $details[] = ['الإجمالي', '', '', '', '', '', '', '', '', '', '', '', '', $t['purchase'], $t['sale'], $t['supplier_return'],
            $t['client_refund'], round($t['gross_profit'] + $t['refund_net'], 2), '', $t['commission'], ''];

        $sheets = [new InvoiceFullReportSheet('الملخص', $summary, 0), new InvoiceFullReportSheet('التفاصيل', $details, 1, true)];
        foreach (InvoiceFullReport::GROUPS as $by => $label) {
            $rows = [array_merge([$label], array_values(self::TOTAL_COLUMNS))];
            foreach ($this->report->groups($by) as $g) {
                $rows[] = array_merge([$g['label']], array_map(fn ($k) => $g[$k], array_keys(self::TOTAL_COLUMNS)));
            }
            $sheets[] = new InvoiceFullReportSheet('حسب ' . $label, $rows, 1);
        }
        return $sheets;
    }
}
