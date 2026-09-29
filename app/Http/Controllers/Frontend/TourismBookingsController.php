<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\{Bank, Log, Storage, Supplier, TourismBooking, TourismBookingItem, TourismBookingPassenger, TourismProgram, User};
use App\Services\{CounterPayments, TourismBookings, TourismLedger, TourismPayments};
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Gate, Validator};
use Illuminate\Validation\Rule;

/**
 * Internal tourism bookings («حجوزات السياحة الداخلية»). The rules are in
 * App\Services\TourismBookings (bookings), TourismLedger (accounting) and TourismPayments
 * (vouchers).
 *
 * Access: tourism.view / create / edit (employees: their own bookings, tourism.view_all: all);
 * editing a confirmed booking and confirming: tourism.confirm; cancelling: tourism.cancel;
 * customer receipt: finance.manage or the booking's owner (tourism.edit); customer refund,
 * supplier payment and voucher reversal: finance.manage.
 */
class TourismBookingsController extends Controller
{
    public function index(Request $request)
    {
        $f = [
            'status' => isset(TourismBooking::STATUSES[$request->input('status')]) ? $request->input('status') : null,
            'program_id' => ctype_digit((string) $request->input('program_id')) ? (int) $request->input('program_id') : null,
            'customer_id' => ctype_digit((string) $request->input('customer_id')) ? (int) $request->input('customer_id') : null,
            'supplier_id' => ctype_digit((string) $request->input('supplier_id')) ? (int) $request->input('supplier_id') : null,
            'employee_id' => ctype_digit((string) $request->input('employee_id')) ? (int) $request->input('employee_id') : null,
            'date_from' => $this->date($request->input('date_from')),
            'date_to' => $this->date($request->input('date_to')),
            'q' => trim((string) $request->input('q')),
        ];
        $q = TourismBooking::visibleTo(Auth::user())->with(['customer:id,name', 'program:id,name', 'owner:id,name', 'items']);
        foreach (['status' => 'status', 'program_id' => 'program_id', 'customer_id' => 'customer_id', 'employee_id' => 'created_by'] as $k => $col) {
            if ($f[$k] !== null) {
                $q->where($col, $f[$k]);
            }
        }
        if ($f['supplier_id'] !== null) {
            $q->whereHas('items', fn ($i) => $i->where('supplier_id', $f['supplier_id']));
        }
        if ($f['date_from']) {
            $q->where('start_date', '>=', $f['date_from']);
        }
        if ($f['date_to']) {
            $q->where('start_date', '<=', $f['date_to']);
        }
        if ($f['q'] !== '') {
            $q->where(fn ($w) => $w->where('booking_no', 'like', '%' . $f['q'] . '%')->orWhere('contact_name', 'like', '%' . $f['q'] . '%')
                ->orWhere('contact_phone', 'like', '%' . $f['q'] . '%'));
        }
        $bookings = $q->orderByDesc('id')->paginate(50)->withQueryString();

        return view('tourism.bookings.index', [
            'bookings' => $bookings,
            'summaries' => TourismPayments::summaries($bookings->getCollection()),
            'f' => $f,
            'accounts' => $this->accounts(),
            'programs' => TourismProgram::orderBy('name')->get(['id', 'name']),
            'employees' => Gate::allows(Permissions::TOURISM_VIEW_ALL) ? User::orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    public function create(Request $request)
    {
        $program = null;
        $items = [];
        if (ctype_digit((string) $request->input('program_id'))) {
            $program = TourismProgram::with('items')->where('status', 1)->findOrFail((int) $request->input('program_id'));
            $items = TourismBookings::itemsFromProgram($program, $this->date($request->input('start_date')),
                (int) $request->input('adults', 1), (int) $request->input('children', 0));
        }
        return $this->form(null, $program, $items, [
            'program_id' => $program?->id,
            'start_date' => $this->date($request->input('start_date')),
            'end_date' => $program && $program->days && $this->date($request->input('start_date'))
                ? \Illuminate\Support\Carbon::parse($request->input('start_date'))->addDays($program->days - 1)->format('Y-m-d') : null,
            'adults' => (int) $request->input('adults', 1),
            'children' => (int) $request->input('children', 0),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $owner = Gate::allows(Permissions::TOURISM_VIEW_ALL) && !empty($data['owner_id']) ? (int) $data['owner_id'] : (int) Auth::id();
        $booking = TourismBookings::create($data, $owner);
        return redirect()->route('site.tourism_bookings_show', $booking->id)->with('success', 'تم حفظ الحجز ' . $booking->booking_no . ' كمسودة');
    }

    public function show($id)
    {
        $booking = $this->visible($id);
        $booking->load(['items.supplier:id,name', 'passengers', 'customer', 'program:id,name', 'owner:id,name']);
        return view('tourism.bookings.show', [
            'booking' => $booking,
            'summary' => TourismPayments::summary($booking),
            'ledger' => TourismLedger::rows($booking),
            'logs' => Log::where('log_txt', 'like', '%' . $booking->booking_no . '%')->orderByDesc('id')->limit(50)->get(),
            'users' => User::pluck('name', 'id'),
            'storages' => Storage::orderBy('id')->get(['id', 'name']),
            'banks' => Bank::orderBy('id')->get(['id', 'bank_name']),
            'canReceive' => $this->canReceive($booking),
        ]);
    }

    public function edit($id)
    {
        $booking = $this->visible($id);
        $this->authorizeEdit($booking);
        $booking->load(['items', 'passengers']);
        return $this->form($booking, $booking->program, $booking->items->map(fn ($i) => $i->toArray() + [
            'start_date' => $i->start_date?->format('Y-m-d'), 'end_date' => $i->end_date?->format('Y-m-d'),
        ])->all(), $booking->toArray());
    }

    public function update(Request $request, $id)
    {
        $booking = $this->visible($id);
        $this->authorizeEdit($booking);
        $data = $this->validated($request, $booking);
        $error = TourismBookings::update($booking->id, $data);
        return $error
            ? back()->withErrors(['booking' => $error])->withInput()
            : redirect()->route('site.tourism_bookings_show', $booking->id)->with('success', 'تم حفظ التعديلات');
    }

    public function confirm($id)
    {
        $booking = $this->visible($id);
        $error = TourismBookings::confirm($booking->id);
        return $this->back($booking, $error, 'تم تأكيد الحجز وتسجيله في كشوف الحساب');
    }

    public function cancelItem(Request $request, $id, $itemId)
    {
        $booking = $this->visible($id);
        $p = $request->validate(['cancel_cost' => ['nullable', 'numeric', 'min:0'], 'cancel_fee' => ['nullable', 'numeric', 'min:0']],
            ['*' => 'أدخل غرامة إلغاء صحيحة']);
        $error = TourismBookings::cancelItem($booking->id, (int) $itemId, (float) ($p['cancel_cost'] ?? 0), (float) ($p['cancel_fee'] ?? 0));
        return $this->back($booking, $error, 'تم إلغاء الخدمة');
    }

    public function cancel(Request $request, $id)
    {
        $booking = $this->visible($id);
        $p = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:500'],
            'penalties' => ['nullable', 'array'],
            'penalties.*.cost' => ['nullable', 'numeric', 'min:0'],
            'penalties.*.fee' => ['nullable', 'numeric', 'min:0'],
        ], ['*' => 'أدخل بيانات إلغاء صحيحة']);
        $error = TourismBookings::cancel($booking->id, (string) ($p['cancel_reason'] ?? ''), $p['penalties'] ?? []);
        return $this->back($booking, $error, 'تم إلغاء الحجز');
    }

    public function destroy($id)
    {
        $booking = $this->visible($id);
        $error = TourismBookings::deleteDraft($booking->id);
        return $error ? $this->back($booking, $error, '') : redirect()->route('site.tourism_bookings')->with('success', 'تم حذف المسودة');
    }

    public function receive(Request $request, $id)
    {
        $booking = $this->visible($id);
        abort_unless($this->canReceive($booking), 403);
        return $this->back($booking, TourismPayments::receive($booking->id, $this->money($request)), 'تم تسجيل سند القبض');
    }

    public function refund(Request $request, $id)
    {
        $booking = $this->visible($id);
        return $this->back($booking, TourismPayments::refund($booking->id, $this->money($request)), 'تم تسجيل رد المبلغ للعميل');
    }

    public function paySupplier(Request $request, $id)
    {
        $booking = $this->visible($id);
        $p = $this->money($request) + $request->validate([
            'supplier_id' => ['required', 'integer'],
            'item_id' => ['nullable', 'integer'],
        ], ['supplier_id.*' => 'اختر المورد']);
        return $this->back($booking, TourismPayments::paySupplier($booking->id, $p), 'تم تسجيل سند الدفع للمورد');
    }

    public function reverseVoucher($id, $bondId)
    {
        $booking = $this->visible($id);
        return $this->back($booking, TourismPayments::reverse($booking->id, (int) $bondId), 'تم عكس السند');
    }

    /** Printable documents: confirmation (customer), service order (one supplier), passengers. */
    public function print(Request $request, $id, $doc)
    {
        $booking = $this->visible($id);
        $booking->load(['items.supplier', 'passengers', 'customer', 'program:id,name', 'owner:id,name']);
        abort_unless(in_array($doc, ['confirmation', 'service_order', 'passengers'], true), 404);
        $supplier = null;
        if ($doc === 'service_order') {
            $supplier = Supplier::findOrFail((int) $request->input('supplier_id'));
            abort_unless($booking->items->where('supplier_id', $supplier->id)->isNotEmpty(), 404);
        }
        return view('tourism.bookings.print_' . $doc, [
            'booking' => $booking,
            'summary' => TourismPayments::summary($booking),
            'supplier' => $supplier,
        ]);
    }

    // ---------------------------------------------------------------------------------------

    private function form(?TourismBooking $booking, ?TourismProgram $program, array $items, array $header)
    {
        return view('tourism.bookings.form', [
            'booking' => $booking,
            'program' => $program,
            'header' => $header,
            'items' => old('items', $items ?: [[]]),
            'passengers' => old('passengers', $booking ? $booking->passengers->toArray() : []),
            'accounts' => $this->accounts($booking),
            'programs' => TourismProgram::where('status', 1)->orderBy('name')->get(['id', 'name', 'days', 'nights']),
            'employees' => !$booking && Gate::allows(Permissions::TOURISM_VIEW_ALL) ? User::where('status', 1)->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    /** The booking, if the signed-in user may see it. */
    private function visible($id): TourismBooking
    {
        $booking = TourismBooking::findOrFail((int) $id);
        abort_unless($booking->isVisibleTo(Auth::user()), 403);
        return $booking;
    }

    private function authorizeEdit(TourismBooking $booking): void
    {
        abort_if($booking->isCancelled(), 403);
        abort_if($booking->isConfirmed() && !Gate::allows(Permissions::TOURISM_CONFIRM), 403);
    }

    private function canReceive(TourismBooking $booking): bool
    {
        return Gate::allows(Permissions::FINANCE_MANAGE)
            || (Gate::allows(Permissions::TOURISM_EDIT) && (int) $booking->created_by === (int) Auth::id());
    }

    /** Active accounts (not expense accounts), the Counter Customer first, plus the booking's own. */
    private function accounts(?TourismBooking $booking = null)
    {
        $keep = $booking ? array_merge([(int) $booking->customer_id], $booking->items->pluck('supplier_id')->map(fn ($v) => (int) $v)->all()) : [];
        $counter = CounterPayments::counterIds();
        return Supplier::where(fn ($q) => $q->where('status', 1)->where('acc_type', '!=', 3))
            ->orWhereIn('id', array_merge($keep, $counter) ?: [0])
            ->orderBy('name')->get(['id', 'name', 'acc_type', 'status'])
            ->sortBy(fn ($a) => in_array((int) $a->id, $counter, true) ? 0 : 1)->values();
    }

    private function validated(Request $request, ?TourismBooking $booking): array
    {
        $allowed = $this->accounts($booking)->pluck('id')->all();
        $types = array_keys(TourismBookingItem::TYPES);
        $v = Validator::make($request->all(), [
            'program_id' => ['nullable', 'integer', 'exists:tourism_programs,id'],
            'customer_id' => ['required', 'integer', Rule::in($allowed)],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'adults' => ['required', 'integer', 'min:0', 'max:999'],
            'children' => ['required', 'integer', 'min:0', 'max:999'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.source_program_item_id' => ['nullable', 'integer'],
            'items.*.service_type' => ['required', Rule::in($types)],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.supplier_id' => ['required', 'integer', Rule::in($allowed), 'different:customer_id'],
            'items.*.start_date' => ['nullable', 'date_format:Y-m-d', 'required_if:items.*.service_type,hotel'],
            'items.*.end_date' => ['nullable', 'date_format:Y-m-d', 'required_if:items.*.service_type,hotel'],
            'items.*.rooms' => ['nullable', 'integer', 'min:1', 'max:999', 'required_if:items.*.service_type,hotel'],
            'items.*.nights' => ['nullable', 'integer', 'min:0', 'max:999'],
            'items.*.room_type' => ['nullable', 'string', 'max:100'],
            'items.*.adults' => ['nullable', 'integer', 'min:0', 'max:999'],
            'items.*.children' => ['nullable', 'integer', 'min:0', 'max:999'],
            'items.*.quantity' => ['nullable', 'numeric', 'gt:0', 'max:99999', 'required_unless:items.*.service_type,hotel'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
            'passengers' => ['nullable', 'array'],
            'passengers.*.name' => ['required', 'string', 'max:255'],
            'passengers.*.type' => ['required', Rule::in(array_keys(TourismBookingPassenger::TYPES))],
            'passengers.*.age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'passengers.*.id_number' => ['nullable', 'string', 'max:50'],
            'passengers.*.phone' => ['nullable', 'string', 'max:50'],
            'passengers.*.room_ref' => ['nullable', 'string', 'max:50'],
            'passengers.*.notes' => ['nullable', 'string', 'max:500'],
        ], [
            'customer_id.*' => 'اختر العميل',
            'items.required' => 'أضف خدمة واحدة على الأقل',
            'items.*.supplier_id.different' => 'مورد الخدمة لا يكون هو نفس عميل الحجز',
            'items.*.supplier_id.*' => 'اختر مورد كل خدمة',
            'items.*.description.*' => 'أدخل وصف كل خدمة',
            'items.*.start_date.required_if' => 'أدخل تاريخ الدخول لكل فندق / قرية',
            'items.*.end_date.required_if' => 'أدخل تاريخ الخروج لكل فندق / قرية',
            'items.*.rooms.*' => 'أدخل عدد الغرف لكل فندق / قرية',
            'items.*.quantity.*' => 'أدخل كمية صحيحة أكبر من صفر لكل خدمة',
            'items.*.unit_cost.*' => 'أدخل سعر تكلفة صحيح',
            'items.*.unit_price.*' => 'أدخل سعر بيع صحيح',
            'passengers.*.name.*' => 'أدخل اسم كل فرد',
            'end_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية',
        ]);
        $v->after(function ($v) use ($request) {
            if ((int) $request->input('adults') + (int) $request->input('children') < 1) {
                $v->errors()->add('adults', 'أدخل عدد الأفراد');
            }
            foreach ((array) $request->input('items', []) as $i => $item) {
                if (($item['service_type'] ?? '') === TourismBookingItem::HOTEL && !empty($item['start_date']) && !empty($item['end_date'])
                    && $item['end_date'] <= $item['start_date']) {
                    $v->errors()->add("items.$i.end_date", 'تاريخ الخروج يجب أن يكون بعد تاريخ الدخول (ليلة واحدة على الأقل)');
                }
            }
        });
        return $v->validate();
    }

    private function money(Request $request): array
    {
        return $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'storage_id' => ['required', 'integer', 'exists:storages,id'],
            'money_way' => ['required', 'in:1,2'],
            'bank_id' => ['exclude_unless:money_way,2', 'required', 'integer', 'exists:banks,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:200'],
        ], [
            'amount.*' => 'أدخل مبلغا صحيحا أكبر من صفر',
            'storage_id.*' => 'اختر الخزنة',
            'money_way.*' => 'اختر طريقة الدفع',
            'bank_id.*' => 'اختر البنك',
            'date.*' => 'أدخل التاريخ',
        ]);
    }

    private function back(TourismBooking $booking, ?string $error, string $done)
    {
        $to = redirect()->route('site.tourism_bookings_show', $booking->id);
        return $error ? $to->withErrors(['booking' => $error])->withInput() : $to->with('success', $done);
    }

    private function date($v): ?string
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }
}
