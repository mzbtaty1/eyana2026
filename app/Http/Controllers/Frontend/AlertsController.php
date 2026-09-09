<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use Redirect;
use App\Models\{
Alert,
};

class AlertsController extends Controller
{
      /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $alerts = Alert::select('*')->orderBy('id','DESC')->get();
        return view('alerts.all' , [
          "alerts" => $alerts,
        ]);
    }
    /**
     * Show the form for creating a new resource.
     */
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('alerts.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function save(Request $request)
    {
//        dd($request);
        $create = Alert::create([
"alert_txt" => $request->alert_txt,

        ]);
         
        
        
        
        
        return redirect()->route('site.alerts'); 
    } 

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
//        $id = (int) $id;
//        $customer = Supplier::select('*')->where('id',$id)->get();
//        abort_if(count($customer) == 0 , 404);
//        
//        $customer = $customer[0];
//        
//        return view('alerts.edit' , [
//          "customer" => $customer,
//        ]);
        
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
//        $id = (int) $request->id;
//        
//        $Customer = Supplier::select('*')->where('id',$id)->get();
//        abort_if(count($Customer) == 0 , 404);
//        
//        $Customer = $Customer[0];
//        $update_Customer = Supplier::select('*')->where('id',$id)->update([
//           "name" => $request->name,
//           "email" => $request->email,
//           "address" => $request->address,
//           "phone_1" => $request->phone_1,
//           "phone_2" => $request->phone_2,
//           "passport_id" => $request->passport_id,
//           "passport_expiration_date" => $request->passport_expiration_date,
//           "debit_opening_balance" => $request->debit_opening_balance,
//           "opening_credit_balance" => $request->opening_credit_balance,
//           "status" => $request->status,
//           "type" => $request->type,
//           "acc_type" => $request->acc_type,
//        ]);
//        
//        
//         $update_in_account = AccountStatement::select('*')->where('supp_client_id',$id)->where('is_supp_account',1)->update([
//            "debit_balance" => $request->debit_opening_balance,
//            "credit_balance" => $request->opening_credit_balance,
//        ]);
//        
//        
//         $save_log = Log::create([
//            "log_txt" => "تم تعديل العميل (المستفيد)  $request->name",
//            "log_ip" => $request->ip(),
//            "log_by" => Auth::user()->id,
//            "log_date" => date('Y-m-d'),
//        ]);
//        
//        $msg = "تم تعديل بيانات العميل " . $request->name;
//        return Redirect::back()->withErrors(['msg' => $msg]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete(Request $request , $id)
    {
        $id = (int) $id;
        $Customer = Alert::select('*')->where('id',$id)->get();
        abort_if(count($Customer) == 0 , 404);
        $Customer = $Customer[0];
        $delete = Alert::select('*')->where('id',$id)->delete();
        
         $msg = "تم حذف الاشعار " . $Customer->alert_txt;
         
//        
//            $save_log = Log::create([
//            "log_txt" => "تم حذف بيانات العميل " . $Customer->alert_txt,
//            "log_ip" => $request->ip(),
//            "log_by" => Auth::user()->id,
//            "log_date" => date('Y-m-d'),
//        ]);
        
//        return $msg; 
        return Redirect::back()->withErrors(['msg' => $msg]);
    }
}
