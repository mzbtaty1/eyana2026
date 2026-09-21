<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use Redirect;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\{
    Supplier,
    Log,
    AccountStatement,
};


class ExpensesController extends Controller
{
     /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $expenses = Supplier::select('*')->where('acc_type' , 3)->orderBy('id','DESC')->get();
        return view('expenses.all' , [
          "expenses" => $expenses,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('expenses.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function save(StoreExpenseRequest $request)
    {
//        dd($request);
        $create = Supplier::create([
"name" => $request->name,
"email" => $request->email,
"address" => $request->address,
"phone_1" => "00000000",
"phone_2" => $request->phone_2,
"passport_id" => $request->passport_id,
"passport_expiration_date" => $request->passport_expiration_date,
"debit_opening_balance" => $request->debit_opening_balance,
"opening_credit_balance" => $request->opening_credit_balance,
"status" => $request->status,
"type" => $request->type,
"acc_type" => 3,
        ]);
//        dd($create);
        $save_account_log = AccountStatement::create([
            "supp_client_id" => $create->id,
            "trans_storage" => 0,
            "is_storage" => 0,
            "is_supp_account" => 1,
            "invoice_type" => 11,
            "es_id" => "FLY-OPEN-BALANCE",
            "invoice_date" => date('Y-m-d'),
            "debit_balance" => $request->debit_opening_balance,
            "credit_balance" => $request->opening_credit_balance,
            "ledger_net_effect" => floatval($request->debit_opening_balance) - floatval($request->opening_credit_balance),
            "transaction_txt" => "الارصدة الافتتاحية",
            "transaction_type" => 3,
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
            
        ]);
        
         $save_log = Log::create([
            "log_txt" => "تم اضافة بيانات المصروفات  $request->name",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        
        
        return redirect()->route('site.expenses'); 
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $id = (int) $id;
        $supplier = Supplier::select('*')->where('id',$id)->get();
        abort_if(count($supplier) == 0 , 404);
        
        $supplier = $supplier[0];
        
        return view('expenses.edit' , [
          "supplier" => $supplier,
        ]);
        
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExpenseRequest $request)
    {
        $id = (int) $request->id;
//        dd($request);
        $supplier = Supplier::select('*')->where('id',$id)->get();
        abort_if(count($supplier) == 0 , 404);
        
        
        
     
           if($request->opening_credit_balance == null){
            $debit_opening_balance = 0;
        }else{
               $debit_opening_balance = $request->debit_opening_balance;
           }
        
           if($request->opening_credit_balance == null){
            $opening_credit_balance = 0;
        }else{
               $opening_credit_balance = $request->opening_credit_balance;
           }
        
        
        $supplier = $supplier[0];
        $update_supplier = Supplier::select('*')->where('id',$id)->update([
           "name" => $request->name,
           "email" => $request->email,
           "address" => $request->address,
           "phone_1" => "0000000000",
           "phone_2" => $request->phone_2,
           "passport_id" => $request->passport_id,
           "passport_expiration_date" => $request->passport_expiration_date,
           "debit_opening_balance" => $request->debit_opening_balance,
           "opening_credit_balance" => $request->opening_credit_balance,
           "status" => 1,
           "type" => 1,
//           "acc_type" => $request->acc_type,
        ]);
        
        $update_in_account = AccountStatement::select('*')->where('supp_client_id',$id)->where('is_supp_account',1)->update([
            "debit_balance" => $request->debit_opening_balance,
            "credit_balance" => $request->opening_credit_balance,
            "ledger_net_effect" => floatval($request->debit_opening_balance) - floatval($request->opening_credit_balance),
        ]);
        
           $save_log = Log::create([
            "log_txt" => "تم تعديل بيانات المصروفات  $request->name",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        
        
        $msg = "تم تعديل بيانات المصروفات " . $request->name;
        return Redirect::back()->withErrors(['msg' => $msg]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete(Request $request , $id)
    {
        $id = (int) $id;
        $supplier = Supplier::select('*')->where('id',$id)->get();
        abort_if(count($supplier) == 0 , 404);
        $supplier = $supplier[0];
        $delete = Supplier::select('*')->where('id',$id)->delete();
        
         $msg = "تم حذف بيانات المصروفات " . $supplier->name;
        
        
            $save_log = Log::create([
            "log_txt" => "تم حذف بيانات المصروفات " . $supplier->name,
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        
//        return $msg;
        return Redirect::back()->withErrors(['msg' => $msg]);
    }
}
