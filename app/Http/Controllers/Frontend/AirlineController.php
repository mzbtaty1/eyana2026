<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use Redirect;
use App\Models\{
    Airline,
    Log,
};

class AirlineController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $airlines = Airline::select('*')->orderBy('id','DESC')->get();
        return view('airlines.all' , [
          "airlines" => $airlines,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('airlines.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function save(Request $request)
    {
        $create = Airline::create([
            "airline_name" => $request->airline_name,
        ]);
        $save_log = Log::create([
            "log_txt" => "تم انشاء خط الطيران $request->airline_name",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        return redirect()->route('site.airlines');
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
        $Airline = Airline::select('*')->where('id',$id)->get();
        abort_if(count($Airline) == 0 , 404);
        
        $Airline = $Airline[0];
        
        
        
        
        return view('airlines.edit' , [
          "airline" => $Airline,
        ]);
        
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $id = (int) $request->id;
        $airline_name = $request->airline_name;
        
        $update = Airline::select('*')->where('id',$id)->update([
            "airline_name" => $airline_name,
        ]);
        
        
        $save_log = Log::create([
            "log_txt" => "تم تعديل خط الطيران $request->airline_name",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        
        return redirect()->route('site.airlines_edit' , $id);
        
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete(Request $request , $id)
    {
                $id = (int) $id;
        $Airline = Airline::select('*')->where('id',$id)->get();
        abort_if(count($Airline) == 0 , 404);
        $Airline = $Airline[0];
        $delete = Airline::select('*')->where('id',$id)->delete();
        
         $msg = "تم حذف بيانات خط الطيران " . $Airline->airline_name;
        
        
        
         $save_log = Log::create([
            "log_txt" => "تم حذف بيانات خط الطيران " . $Airline->airline_name,
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        
//        return $msg;
        return Redirect::back()->withErrors(['msg' => $msg]);
    }
}
