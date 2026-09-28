<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmployeeCommissionReport;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * «تقرير عمولات الموظفين» (Employees step D): an employee's profit and commission for a
 * period (App\Services\EmployeeCommissionReport). Read-only. reports.all (admin): any
 * employee, or all of them; otherwise (commission.view_own) always the signed-in employee --
 * a requested employee_id is ignored.
 */
class EmployeeCommissionReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:' . Permissions::COMMISSION_OWN);
    }

    public function index(Request $request)
    {
        $all = Gate::allows(Permissions::REPORTS_ALL);
        $report = null;

        if ($request->hasAny(['date_from', 'date_to'])) {
            $data = $request->validate([
                'date_from' => ['required', 'date_format:Y-m-d'],
                'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
                'employee_id' => $all ? ['nullable', 'integer', 'exists:users,id'] : ['nullable'],
            ], [
                'date_from.*' => 'أدخل تاريخ البداية بصيغة صحيحة',
                'date_to.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية أو مساويا له',
                'date_to.*' => 'أدخل تاريخ النهاية بصيغة صحيحة',
                'employee_id.*' => 'الموظف غير موجود',
            ]);
            $report = new EmployeeCommissionReport(Auth::user(), $data['date_from'], $data['date_to'],
                $all && !empty($data['employee_id']) ? (int) $data['employee_id'] : null);
        }

        return view('reports.employee_commission', [
            'report' => $report,
            'all' => $all,
            'employees' => $all ? User::orderBy('name')->get(['id', 'name']) : collect(),
            'date_from' => $request->input('date_from', date('Y-m-01')),
            'date_to' => $request->input('date_to', date('Y-m-d')),
            'employee_id' => $all ? $request->input('employee_id') : null,
        ]);
    }
}
