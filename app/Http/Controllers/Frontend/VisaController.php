<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use Redirect;
use App\Http\Requests\StoreVisaRequest;
use App\Http\Requests\UpdateVisaRequest;
use App\Models\{
    Visa,
    Log,
};

/**
 * Visa types («التأشيرات», under «خطوط الطيران»; separate from airlines). Admin-only.
 * A visa type used by visa invoices cannot be deleted or renamed -- only disabled
 * (status 0), so historical invoices keep their visa type.
 */
class VisaController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(Auth::user()->account_type == 2, 403);
            return $next($request);
        });
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Visas = Visa::select('*')->orderBy('id','DESC')->get();
        return view('visas.all' , [
          "visas" => $Visas,
          "usage" => Visa::invoiceCounts(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('visas.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function save(StoreVisaRequest $request)
    {
        $create = Visa::create([
            "visa_name" => $request->visa_name,
            // visa types are names only; the old NOT NULL price columns get 0 and are not used anywhere
            "visa_price" => 0,
            "visa_ext_price" => 0,
            "status" => (int) $request->status,
        ]);
        Log::create([
            "log_txt" => "تم اضافة التأشيرة $request->visa_name",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        return redirect()->route('site.visas');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $Visa = Visa::find((int) $id);
        abort_if(! $Visa, 404);

        return view('visas.edit' , [
          "visa" => $Visa,
          "used" => $Visa->invoicesCount(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVisaRequest $request)
    {
        $Visa = Visa::find((int) $request->id);
        abort_if(! $Visa, 404);

        // invoices keep the name they were saved with: a used visa type keeps its name
        if ($request->visa_name !== $Visa->visa_name && $Visa->invoicesCount() > 0) {
            return Redirect::back()->withInput()->withErrors(['visa_name' => 'لا يمكن تغيير اسم تأشيرة مستخدمة في فواتير. يمكنك تعطيلها وإضافة تأشيرة جديدة.']);
        }

        $Visa->update([
            "visa_name" => $request->visa_name,
            "status" => (int) $request->status, // stored prices are left as they are (not used)
        ]);
        Log::create([
            "log_txt" => "تم تعديل التأشيرة $request->visa_name",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);

        return redirect()->route('site.visas_edit' , $Visa->id)->withErrors(['msg' => 'تم حفظ بيانات التأشيرة ' . $Visa->visa_name]);
    }

    /**
     * Enable / disable a visa type (status 1 / 0).
     */
    public function status(Request $request, $id)
    {
        $Visa = Visa::find((int) $id);
        abort_if(! $Visa, 404);

        $Visa->update(["status" => $Visa->status == 1 ? 0 : 1]);
        $txt = ($Visa->status == 1 ? "تم تفعيل التأشيرة " : "تم تعطيل التأشيرة ") . $Visa->visa_name;
        Log::create([
            "log_txt" => $txt,
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);

        return Redirect::back()->withErrors(['msg' => $txt]);
    }

    /**
     * Remove the specified resource from storage -- only a visa type no invoice uses.
     */
    public function delete(Request $request , $id)
    {
        $Visa = Visa::find((int) $id);
        abort_if(! $Visa, 404);

        $used = $Visa->invoicesCount();
        if ($used > 0) {
            return Redirect::back()->withErrors(['delete' => "لا يمكن حذف «{$Visa->visa_name}» لأنها مستخدمة في $used فاتورة. يمكنك تعطيلها بدلاً من حذفها."]);
        }

        $Visa->delete();
        $msg = "تم حذف بيانات التأشيرة " . $Visa->visa_name;
        Log::create([
            "log_txt" => $msg,
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);

        return Redirect::back()->withErrors(['msg' => $msg]);
    }
}
