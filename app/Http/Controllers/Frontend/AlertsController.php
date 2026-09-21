<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use Redirect;
use App\Http\Requests\StoreAlertRequest;
use App\Http\Requests\UpdateAlertRequest;
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
    public function save(StoreAlertRequest $request)
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
        $id = (int) $id;
        $alert = Alert::select('*')->where('id',$id)->get();
        abort_if(count($alert) == 0 , 404);

        $alert = $alert[0];

        return view('alerts.edit' , [
          "alert" => $alert,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAlertRequest $request)
    {
        $id = (int) $request->id;

        $alert = Alert::select('*')->where('id',$id)->get();
        abort_if(count($alert) == 0 , 404);

        Alert::select('*')->where('id',$id)->update([
            "alert_txt" => $request->alert_txt,
        ]);

        $msg = "تم تعديل نص الاشعار";
        return Redirect::back()->withErrors(['msg' => $msg]);
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
