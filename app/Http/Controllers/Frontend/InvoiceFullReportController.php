<?php

namespace App\Http\Controllers\Frontend;

use App\Exports\InvoiceFullReportExport;
use App\Http\Controllers\Controller;
use App\Services\InvoiceFullReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * «التقرير التفصيلي للفواتير» (/invoices/full-report). Read-only; every figure
 * comes from App\Services\InvoiceFullReport.
 */
class InvoiceFullReportController extends Controller
{
    protected function report(Request $request): InvoiceFullReport
    {
        return new InvoiceFullReport($request->all(), Auth::user());
    }

    public function index(Request $request)
    {
        $report = $this->report($request);

        return view('invoices.full_report', [
            'report' => $report,
            'f' => $report->f,
            'isAdmin' => $report->isAdmin(),
            'employees' => $report->isAdmin() ? DB::table('users')->orderBy('name')->get(['id', 'name']) : collect(),
            'customers' => DB::table('suppliers')->whereIn('id', DB::table('invoices')->select('invoice_beneficiaries')->distinct())
                ->orderBy('name')->get(['id', 'name']),
            'suppliers' => DB::table('suppliers')->whereIn('id', DB::table('ticket_vendors')->select('vendor_id')->distinct())
                ->orderBy('name')->get(['id', 'name']),
            'airlines' => DB::table('invoices')->whereRaw("TRIM(COALESCE(invoice_airline, '')) <> ''")
                ->distinct()->orderBy('invoice_airline')->pluck('invoice_airline'),
        ]);
    }

    /** The former search form posted here: keep it working by showing the report with its filters. */
    public function legacy(Request $request)
    {
        $section = $request->input('invoice_section');

        return redirect()->route('site.invoices_full_report', array_filter([
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'section' => $section,
        ]));
    }

    /** Server-side DataTables data: the requested page, plus the totals of all matching rows. */
    public function data(Request $request)
    {
        $input = $request->all();
        $input['search'] = $request->input('search.value');
        $report = new InvoiceFullReport($input, Auth::user());

        $col = (int) $request->input('order.0.column', -1);
        $key = $request->input("columns.$col.data");
        $dir = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $rows = $report->sorted($col >= 0 ? $key : null, $dir);

        $length = (int) $request->input('length', 25);
        $length = $length > 0 ? min($length, 500) : 25;
        $page = $rows->slice(max(0, (int) $request->input('start', 0)), $length)->values();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $rows->count(),
            'recordsFiltered' => $rows->count(),
            'data' => $page->map(fn ($r) => $this->display($r)),
            'summary' => $report->summary(),
        ]);
    }

    public function groups(Request $request)
    {
        $by = array_key_exists($request->input('by'), InvoiceFullReport::GROUPS) ? $request->input('by') : 'employee';

        return response()->json([
            'by' => $by,
            'label' => InvoiceFullReport::GROUPS[$by],
            'groups' => $this->report($request)->groups($by),
        ]);
    }

    public function excel(Request $request)
    {
        return Excel::download(new InvoiceFullReportExport($this->report($request)),
            'invoices-report-' . date('Y-m-d-His') . '.xlsx');
    }

    public function print(Request $request)
    {
        $report = $this->report($request);
        $by = array_key_exists($request->input('by'), InvoiceFullReport::GROUPS) ? $request->input('by') : null;

        return view('invoices.full_report_print', [
            'report' => $report,
            'rows' => $by ? collect() : $report->rows(),
            'summary' => $report->summary(),
            'by' => $by,
            'groups' => $by ? $report->groups($by) : [],
            'filters' => $report->filterLabels(),
        ]);
    }

    /** A row as the table shows it (amounts formatted). */
    protected function display(array $r): array
    {
        $out = array_intersect_key($r, array_flip(['id', 'es_id', 'op', 'op_label', 'edits', 'last_edit', 'type', 'type_label',
            'invoice_date', 'travel_date', 'route', 'airline', 'section', 'customer', 'supplier', 'employee', 'rate_label',
            'passenger', 'pnr', 'ticket']));
        foreach (['purchase', 'sale', 'supplier_return', 'client_refund', 'profit', 'commission'] as $k) {
            $out[$k] = round($r[$k], 2);
        }
        return $out;
    }
}
