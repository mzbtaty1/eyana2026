<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\{Log, Supplier, TourismBookingItem, TourismProgram, TourismProgramItem};
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Validation\Rule;

/**
 * Internal tourism programs («البرامج السياحية»): reusable templates (tourism.programs).
 * Saving a program replaces its items; bookings already made from it keep their own copy.
 * Programs are activated / deactivated, never deleted.
 */
class TourismProgramsController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:' . Permissions::TOURISM_PROGRAMS);
    }

    public function index()
    {
        return view('tourism.programs.index', [
            'programs' => TourismProgram::withCount(['items', 'bookings'])->orderByDesc('status')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return $this->form(new TourismProgram(['status' => 1]));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $program = DB::transaction(function () use ($data) {
            $program = TourismProgram::create($this->header($data) + ['status' => 1, 'created_by' => Auth::id()]);
            $this->saveItems($program, $data['items']);
            $this->log('تم إضافة برنامج سياحي ' . $program->name);
            return $program;
        });
        return redirect()->route('site.tourism_programs')->with('success', 'تم حفظ البرنامج ' . $program->name);
    }

    public function edit($id)
    {
        return $this->form(TourismProgram::with('items')->findOrFail((int) $id));
    }

    public function update(Request $request, $id)
    {
        $program = TourismProgram::findOrFail((int) $id);
        $data = $this->validated($request);
        DB::transaction(function () use ($program, $data) {
            $program->update($this->header($data));
            $program->items()->delete();   // template only: bookings keep their own copies
            $this->saveItems($program, $data['items']);
            $this->log('تم تعديل برنامج سياحي ' . $program->name);
        });
        return redirect()->route('site.tourism_programs')->with('success', 'تم حفظ البرنامج ' . $program->name);
    }

    public function status($id)
    {
        $program = TourismProgram::findOrFail((int) $id);
        $program->update(['status' => $program->isActive() ? 0 : 1]);
        $this->log(($program->isActive() ? 'تم تفعيل' : 'تم إيقاف') . ' برنامج سياحي ' . $program->name);
        return back()->with('success', $program->isActive() ? 'تم تفعيل البرنامج' : 'تم إيقاف البرنامج');
    }

    private function form(TourismProgram $program)
    {
        return view('tourism.programs.form', [
            'program' => $program,
            'items' => old('items', $program->exists ? $program->items->toArray() : [[]]),
            'accounts' => Supplier::where('status', 1)->where('acc_type', '!=', 3)
                ->orWhereIn('id', $program->exists ? $program->items->pluck('supplier_id')->filter()->all() ?: [0] : [0])
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'nights' => ['nullable', 'integer', 'min:0', 'max:365'],
            'description' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_type' => ['required', Rule::in(array_keys(TourismBookingItem::TYPES))],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where(fn ($q) => $q->where('acc_type', '!=', 3))],
            'items.*.day_no' => ['nullable', 'integer', 'min:1', 'max:365'],
            'items.*.nights' => ['nullable', 'integer', 'min:1', 'max:365', 'required_if:items.*.service_type,hotel'],
            'items.*.rooms' => ['nullable', 'integer', 'min:1', 'max:999'],
            'items.*.room_type' => ['nullable', 'string', 'max:100'],
            'items.*.pricing' => ['required', Rule::in(array_keys(TourismProgramItem::PRICING))],
            'items.*.quantity' => ['nullable', 'numeric', 'gt:0', 'max:99999'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.*' => 'أدخل اسم البرنامج',
            'items.required' => 'أضف خدمة واحدة على الأقل',
            'items.*.description.*' => 'أدخل وصف كل خدمة',
            'items.*.nights.required_if' => 'أدخل عدد الليالي لكل فندق / قرية',
            'items.*.unit_cost.*' => 'أدخل سعر تكلفة صحيح',
            'items.*.unit_price.*' => 'أدخل سعر بيع صحيح',
        ]);
    }

    private function header(array $d): array
    {
        return ['name' => $d['name'], 'destination' => $d['destination'] ?? null, 'days' => $d['days'] ?? null,
            'nights' => $d['nights'] ?? null, 'description' => $d['description'] ?? null];
    }

    private function saveItems(TourismProgram $program, array $items): void
    {
        foreach (array_values($items) as $i => $row) {
            $program->items()->create([
                'service_type' => $row['service_type'],
                'description' => $row['description'],
                'supplier_id' => $row['supplier_id'] ?? null,
                'day_no' => $row['day_no'] ?? null,
                'nights' => $row['nights'] ?? null,
                'rooms' => $row['rooms'] ?? null,
                'room_type' => $row['room_type'] ?? null,
                'pricing' => $row['pricing'],
                'quantity' => ($row['quantity'] ?? '') !== '' ? $row['quantity'] : 1,
                'unit_cost' => $row['unit_cost'],
                'unit_price' => $row['unit_price'],
                'notes' => $row['notes'] ?? null,
                'sort' => $i,
            ]);
        }
    }

    private function log(string $text): void
    {
        Log::create(['log_txt' => $text, 'log_ip' => request()->ip(), 'log_by' => Auth::id(), 'log_date' => date('Y-m-d')]);
    }
}
