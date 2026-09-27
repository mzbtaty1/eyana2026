<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\CommissionTierTableRequest;
use App\Models\{CommissionTierTable, Log};
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

/**
 * «اعدادات البرنامج ← شرائح العمولات» (Employees step B): the admin's reusable commission
 * tier tables. Configuration only -- no employee assignment and no commission calculation
 * here. Every action needs commission.settings (admin); the routes carry the same check.
 */
class CommissionTierController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:' . Permissions::COMMISSION_SETTINGS);
    }

    public function index()
    {
        $tables = CommissionTierTable::withCount('tiers')->orderByDesc('status')->orderBy('name')->get();
        $usage = $tables->mapWithKeys(fn ($t) => [$t->id => $t->usageCount()]);

        return view('commission_tiers.all', ['tables' => $tables, 'usage' => $usage]);
    }

    public function create()
    {
        return view('commission_tiers.form', ['table' => null, 'tiers' => collect()]);
    }

    public function store(CommissionTierTableRequest $request)
    {
        $table = DB::transaction(function () use ($request) {
            $table = CommissionTierTable::create(['name' => $request->input('name'), 'status' => (int) $request->input('status')]);
            $table->tiers()->createMany($request->sortedTiers());
            return $table;
        });
        $this->log($request, 'تم اضافة جدول شرائح العمولات ' . $table->name);

        return redirect()->route('site.commission_tiers_show', $table->id)->with('success', 'تم حفظ جدول الشرائح');
    }

    public function show($id)
    {
        $table = CommissionTierTable::with('tiers')->findOrFail((int) $id);

        return view('commission_tiers.show', ['table' => $table, 'used' => $table->usageCount()]);
    }

    public function edit($id)
    {
        $table = CommissionTierTable::with('tiers')->findOrFail((int) $id);

        return view('commission_tiers.form', ['table' => $table, 'tiers' => $table->tiers]);
    }

    /** Name, status and the whole tier list (the tiers are replaced by the submitted ones). */
    public function update(CommissionTierTableRequest $request, $id)
    {
        $table = CommissionTierTable::findOrFail((int) $id);
        DB::transaction(function () use ($request, $table) {
            $table->update(['name' => $request->input('name'), 'status' => (int) $request->input('status')]);
            $table->tiers()->delete();
            $table->tiers()->createMany($request->sortedTiers());
        });
        $this->log($request, 'تم تعديل جدول شرائح العمولات ' . $table->name);

        return redirect()->route('site.commission_tiers_show', $table->id)->with('success', 'تم حفظ جدول الشرائح');
    }

    /** Activate / deactivate (an inactive table is kept). */
    public function status(Request $request, $id)
    {
        $table = CommissionTierTable::findOrFail((int) $id);
        $table->update(['status' => $table->isActive() ? 0 : 1]);
        $this->log($request, ($table->isActive() ? 'تم تفعيل' : 'تم تعطيل') . ' جدول شرائح العمولات ' . $table->name);

        return redirect()->route('site.commission_tiers')->with('success', $table->isActive() ? 'تم تفعيل الجدول' : 'تم تعطيل الجدول');
    }

    /** Only a table no employee uses can be deleted; a used one can only be deactivated. */
    public function destroy(Request $request, $id)
    {
        $table = CommissionTierTable::findOrFail((int) $id);
        if ($table->usageCount() > 0) {
            return redirect()->route('site.commission_tiers')->withErrors(['table' => 'لا يمكن حذف جدول مستخدم لموظفين. يمكنك تعطيله.']);
        }
        DB::transaction(fn () => $table->delete()); // its tiers go with it (foreign key cascade)
        $this->log($request, 'تم حذف جدول شرائح العمولات ' . $table->name);

        return redirect()->route('site.commission_tiers')->with('success', 'تم حذف الجدول');
    }

    private function log(Request $request, string $text): void
    {
        Log::create([
            'log_txt' => $text,
            'log_ip' => $request->ip(),
            'log_by' => Auth::id(),
            'log_date' => date('Y-m-d'),
        ]);
    }
}
