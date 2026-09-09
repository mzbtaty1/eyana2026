<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use Redirect;
use App\Models\{
    Visa,
    Log,
};

class VisaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Visas = Visa::select('*')->orderBy('id','DESC')->get();
        return view('visas.all' , [
          "visas" => $Visas,
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
    public function save(Request $request)
    {
        $create = Visa::create([
            "visa_name" => $request->visa_name,
            "visa_price" => $request->visa_price,
            "visa_ext_price" => $request->visa_ext_price,
        ]);
//        $save_log = Log::create([
//            "log_txt" => "تم انشاء خط الطيران $request->Visa_name",
//            "log_ip" => $request->ip(),
//            "log_by" => Auth::user()->id,
//            "log_date" => date('Y-m-d'),
//        ]);
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
         $id = (int) $id;
        $Visa = Visa::select('*')->where('id',$id)->get();
        abort_if(count($Visa) == 0 , 404);
        
        $Visa = $Visa[0];
        
        
        
        
        return view('visas.edit' , [
          "visa" => $Visa,
        ]);
        
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $id = (int) $request->id;
        $Visa_name = $request->Visa_name;
        
        $update = Visa::select('*')->where('id',$id)->update([
                 "visa_name" => $request->visa_name,
            "visa_price" => $request->visa_price,
            "visa_ext_price" => $request->visa_ext_price,
        ]);
        
//        
//        $save_log = Log::create([
//            "log_txt" => "تم تعديل خط الطيران $request->Visa_name",
//            "log_ip" => $request->ip(),
//            "log_by" => Auth::user()->id,
//            "log_date" => date('Y-m-d'),
//        ]);
        
        return redirect()->route('site.visas_edit' , $id);
        
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete(Request $request , $id)
    {
                $id = (int) $id;
        $Visa = Visa::select('*')->where('id',$id)->get();
        abort_if(count($Visa) == 0 , 404);
        $Visa = $Visa[0];
        $delete = Visa::select('*')->where('id',$id)->delete();
        
         $msg = "تم حذف بيانات التأشيرة " . $Visa->visa_name;
        
        
        
//         $save_log = Log::create([
//            "log_txt" => "تم حذف بيانات خط الطيران " . $Visa->Visa_name,
//            "log_ip" => $request->ip(),
//            "log_by" => Auth::user()->id,
//            "log_date" => date('Y-m-d'),
//        ]);
        
//        return $msg;
        return Redirect::back()->withErrors(['msg' => $msg]);
    }
}
