<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use Redirect;
use App\Models\{
    Supplier,
    Invoice, 
    TicketUser,
    TicketVendor,
    Airline,
    AccountStatement,
    Log,
    Bank,
    Collector,
};

class CollectorsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $collectors = Collector::select('*')->orderBy('id','DESC')->get();
        
        return view('collectors.all' , [
            "collectors" => $collectors,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('collectors.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function save(Request $request)
    {
        $create_bank = Collector::create([
            "name" => $request->name,
            "phone" => $request->phone,
        ]);
        
          $save_log = Log::create([
            "log_txt" => "تم اضافة محصل $request->name",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        
        return redirect()->route('site.collectors');
        
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $check_bank = Collector::select('*')->where('id',$id)->get();
        abort_if(count($check_bank) == 0 , 404);
        $bank_info = $check_bank[0];
        
        return view('collectors.edit' , [
            "collector_info" => $bank_info,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $id = (int) $request->id;
        $check_bank = Collector::select('*')->where('id',$id)->get();
        abort_if(count($check_bank) == 0 , 404);
        $bank_info = $check_bank[0];
        
        $update_bank = Collector::select('*')->where('id',$id)->update([
            "name" => $request->name,
            "phone" => $request->phone,
        ]);
        
        $save_log = Log::create([
            "log_txt" => "تم تعديل بيانات محصل $bank_info->name",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        return redirect()->route('site.collectors_edit' , $id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete(Request $request , $id)
    {
          $check_bank = Collector::select('*')->where('id',$id)->get();
        abort_if(count($check_bank) == 0 , 404);
        $bank_info = $check_bank[0];
        
        $save_log = Log::create([
            "log_txt" => "تم حذف بيانات محصل $bank_info->name",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        
       
        $delete = Collector::select('*')->where('id',$id)->delete();
         return redirect()->route('site.collectors');
    }
}
