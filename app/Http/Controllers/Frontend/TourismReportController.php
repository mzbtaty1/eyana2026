<?php

namespace App\Http\Controllers\Frontend;

use App\Exports\InvoiceFullReportSheet;
use App\Http\Controllers\Controller;
use App\Models\{Supplier, TourismBookingItem, TourismProgram, User};
use App\Services\TourismReport;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Gate};
use Maatwebsite\Excel\Facades\Excel;

/**
 * «تقرير السياحة الداخلية» (App\Services\TourismReport: screen, print, Excel) and the
 * operation lists (rooming list / transport list). reports.own: an employee sees their own
 * bookings; reports.all or tourism.view_all: every booking.
 */
class TourismReportController extends Controller
{
    const OPERATIONS = [TourismBookingItem::HOTEL => 'كشف تسكين الفنادق (Rooming List)', TourismBookingItem::TRANSPORT => 'كشف الانتقالات'];

    public function index(Request $request)
    {
        return view('tourism.reports.index', ['report' => new TourismReport($request->all(), Auth::user())] + $this->lists());
    }

    public function print(Request $request)
    {
        return view('tourism.reports.print', ['report' => new TourismReport($request->all(), Auth::user())]);
    }

    public function excel(Request $request)
    {
        $report = new TourismReport($request->all(), Auth::user());
        return Excel::download(new InvoiceFullReportSheet('السياحة الداخلية', $report->table(), 1, true), 'tourism-' . date('Y-m-d-His') . '.xlsx');
    }

    public function operations(Request $request, $type)
    {
        abort_unless(isset(self::OPERATIONS[$type]), 404);
        return view('tourism.operations.index', [
            'type' => $type,
            'title' => self::OPERATIONS[$type],
            'items' => TourismReport::operations($type, $request->all(), Auth::user()),
            'f' => $request->only(['date_from', 'date_to', 'supplier_id', 'program_id']),
            'print' => $request->boolean('print'),
        ] + $this->lists());
    }

    private function lists(): array
    {
        return [
            'accounts' => Supplier::where('acc_type', '!=', 3)->orderBy('name')->get(['id', 'name']),
            'programs' => TourismProgram::orderBy('name')->get(['id', 'name']),
            'employees' => Gate::allows(Permissions::REPORTS_ALL) || Gate::allows(Permissions::TOURISM_VIEW_ALL)
                ? User::orderBy('name')->get(['id', 'name']) : collect(),
        ];
    }
}
