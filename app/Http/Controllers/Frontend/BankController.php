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
    Bond,
};


class BankController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $banks = Bank::select('*')->orderBy('id','DESC')->get();
        
        return view('moneyarea.banks.all' , [
            "banks" => $banks,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('moneyarea.banks.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function save(Request $request)
    {
        $create_bank = Bank::create([
            "bank_name" => $request->bank_name,
            "bank_balance" => $request->bank_balance,
        ]);
        
          $save_log = Log::create([
            "log_txt" => "تم اضافة بنك $request->bank_name",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        
        return redirect()->route('site.banks');
        
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $check_bank = Bank::select('*')->where('id',$id)->get();
        abort_if(count($check_bank) == 0 , 404);
        $bank_info = $check_bank[0];
        
        return view('moneyarea.banks.edit' , [
            "bank_info" => $bank_info,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $id = (int) $request->id;
        $check_bank = Bank::select('*')->where('id',$id)->get();
        abort_if(count($check_bank) == 0 , 404);
        $bank_info = $check_bank[0];
        
        $update_bank = Bank::select('*')->where('id',$id)->update([
            "bank_name" => $request->bank_name,
            "bank_balance" => $request->bank_balance,
        ]);
        
        $save_log = Log::create([
            "log_txt" => "تم تعديل اسم بنك $bank_info->bank_name",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        return redirect()->route('site.banks_edit' , $id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete($id)
    {
          $check_bank = Bank::select('*')->where('id',$id)->get();
        abort_if(count($check_bank) == 0 , 404);
        $bank_info = $check_bank[0];
        
       
        $delete = Bank::select('*')->where('id',$id)->delete();
         return redirect()->route('site.banks');
    }
    
    public function bank_account_transactions($id){
           $check_bank = Bank::select('*')->where('id',$id)->get();
        abort_if(count($check_bank) == 0 , 404);
        $bank_info = $check_bank[0];
        
        
        $bonds = Bond::select('*')
            ->where('bank_id' ,$id)
            ->where('money_way' ,2)
            ->get();
        return view('moneyarea.banks.bnk_transactions' , [
            "bank_info" => $bank_info,
            "bonds" => $bonds,
        ]);
    }
}
