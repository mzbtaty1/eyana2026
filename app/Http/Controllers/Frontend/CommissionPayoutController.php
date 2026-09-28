<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Exports\InvoiceFullReportSheet;
use App\Models\{Bank, CommissionPayout, CommissionPeriod, Storage, User};
use App\Services\{CommissionDashboard, CommissionPayouts};
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * «صرف عمولات الموظفين» (Employees step E): open an employee's commission period with the
 * Step D figures, pay it (in part or in full) through a payment voucher, reverse a payment.
 * finance.manage (admin) for everything; commission.view_own for an employee's own history.
 * All the rules are in App\Services\CommissionPayouts.
 */
class CommissionPayoutController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:' . Permissions::FINANCE_MANAGE)->except('mine');
        $this->middleware('can:' . Permissions::COMMISSION_OWN)->only('mine');
    }

    /**
     * «لوحة العمولات» (Employees step F): every period with the commission at opening, the
     * current Step D commission, the difference, paid, remaining, status and last payment
     * (App\Services\CommissionDashboard); filters employee / from / to / status.
     */
    public function index(Request $request)
    {
        $dashboard = new CommissionDashboard(Auth::user(), $request->all());

        return view('commission_payouts.index', [
            'dashboard' => $dashboard,
            'employees' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** The dashboard's figures, printable. */
    public function print(Request $request)
    {
        return view('commission_payouts.print', ['dashboard' => new CommissionDashboard(Auth::user(), $request->all())]);
    }

    /** The dashboard's figures as an Excel sheet (the detailed report's sheet class). */
    public function excel(Request $request)
    {
        $dashboard = new CommissionDashboard(Auth::user(), $request->all());
        return Excel::download(new InvoiceFullReportSheet('عمولات الموظفين', $dashboard->table(), 1, true), 'commissions-' . date('Y-m-d-His') . '.xlsx');
    }

    /** The current Step D result for an employee and period, the period (if opened) and its payments. */
    public function preview(Request $request)
    {
        $data = $request->validate($this->periodRules(), $this->messages());
        $employee = User::findOrFail((int) $data['employee_id']);
        [$from, $to] = [$data['from'], $data['to']];
        $period = CommissionPeriod::with(['payouts.creator:id,name', 'payouts.reverser:id,name', 'payouts.bond:id,es_id'])
            ->where('employee_id', $employee->id)->where('period_from', $from)->where('period_to', $to)->first();

        return view('commission_payouts.preview', [
            'employee' => $employee,
            'from' => $from,
            'to' => $to,
            'current' => CommissionPayouts::calculate(Auth::user(), $employee->id, $from, $to),
            'period' => $period,
            'overlap' => $period ? null : CommissionPayouts::overlapping($employee->id, $from, $to),
            'beforeStart' => $from < CommissionPayouts::START_DATE,
            'storages' => Storage::orderBy('id')->get(['id', 'name', 'balance']),
            'banks' => Bank::orderBy('id')->get(['id', 'bank_name']),
            'token' => (string) Str::uuid(),
        ]);
    }

    /** action = open (a period) / refresh (an unpaid period's figures) / pay. */
    public function store(Request $request)
    {
        $action = $request->input('action');
        if ($action === 'open') {
            $data = $request->validate($this->periodRules(), $this->messages());
            [$period, $error] = CommissionPayouts::open(User::findOrFail((int) $data['employee_id']), $data['from'], $data['to']);
            $back = ['employee_id' => $data['employee_id'], 'from' => $data['from'], 'to' => $data['to']];
            return $error
                ? redirect()->route('site.commission_payouts_preview', $back)->withErrors(['period' => $error])
                : redirect()->route('site.commission_payouts_preview', $back)->with('success', 'تم فتح فترة العمولة');
        }

        $data = $request->validate(['period_id' => ['required', 'integer', 'exists:commission_periods,id']], $this->messages());
        $period = CommissionPeriod::findOrFail((int) $data['period_id']);
        $back = redirect()->route('site.commission_payouts_preview', $this->periodQuery($period));

        if ($action === 'refresh') {
            $error = CommissionPayouts::refresh($period->id);
            return $error ? $back->withErrors(['period' => $error]) : $back->with('success', 'تم اعتماد العمولة الحالية للفترة');
        }

        abort_unless($action === 'pay', 422);
        $p = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'storage_id' => ['required', 'integer', 'exists:storages,id'],
            'money_way' => ['required', 'in:1,2'],
            'bank_id' => ['exclude_unless:money_way,2', 'required', 'integer', 'exists:banks,id'],
            'paid_date' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:200'],
            'request_token' => ['required', 'string', 'max:64'],
        ], $this->messages());
        [$payout, $error] = CommissionPayouts::pay($period->id, $p);
        return $error
            ? $back->withErrors(['payout' => $error])->withInput()
            : redirect()->route('site.commission_payouts_receipt', $payout->id)->with('success', 'تم صرف العمولة');
    }

    public function reverse($id)
    {
        $payout = CommissionPayout::findOrFail((int) $id);
        $error = CommissionPayouts::reverse($payout->id);
        $back = redirect()->route('site.commission_payouts_preview', $this->periodQuery($payout->period));
        return $error ? $back->withErrors(['payout' => $error]) : $back->with('success', 'تم عكس عملية الصرف وإعادة فتح الفترة');
    }

    public function receipt($id)
    {
        $payout = CommissionPayout::with(['period.employee:id,name', 'bond', 'creator:id,name'])->findOrFail((int) $id);

        return view('commission_payouts.receipt', ['payout' => $payout, 'period' => $payout->period]);
    }

    /**
     * «كشف عمولاتي» (Employees step F): the signed-in employee's periods (profit and commission
     * at opening, current commission, paid, remaining, status) and their payments / reversals.
     * Read-only. finance.manage (admin) may view any employee's statement; for anyone else a
     * requested employee_id is ignored.
     */
    public function mine(Request $request)
    {
        $admin = Gate::allows(Permissions::FINANCE_MANAGE);
        $employeeId = $admin && ctype_digit((string) $request->input('employee_id')) && User::whereKey((int) $request->input('employee_id'))->exists()
            ? (int) $request->input('employee_id') : (int) Auth::id();

        return view('commission_payouts.mine', [
            'dashboard' => new CommissionDashboard(Auth::user(), ['employee_id' => $employeeId]),
            'employee' => User::find($employeeId),
            'employees' => $admin ? User::orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    private function periodRules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:users,id'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    private function periodQuery(CommissionPeriod $period): array
    {
        return ['employee_id' => $period->employee_id, 'from' => $period->period_from->format('Y-m-d'), 'to' => $period->period_to->format('Y-m-d')];
    }

    private function messages(): array
    {
        return [
            'employee_id.*' => 'اختر الموظف',
            'from.*' => 'أدخل تاريخ البداية بصيغة صحيحة',
            'to.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية أو مساويا له',
            'to.*' => 'أدخل تاريخ النهاية بصيغة صحيحة',
            'period_id.*' => 'الفترة غير موجودة',
            'amount.*' => 'أدخل مبلغا صحيحا أكبر من صفر',
            'storage_id.*' => 'اختر الخزنة',
            'money_way.*' => 'اختر طريقة الدفع',
            'bank_id.*' => 'اختر البنك',
            'paid_date.*' => 'أدخل تاريخ الصرف',
            'request_token.*' => 'طلب غير صالح، أعد تحميل الصفحة',
        ];
    }
}
