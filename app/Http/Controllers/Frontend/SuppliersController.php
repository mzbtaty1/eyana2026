<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use Redirect;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\{
    Supplier,
    Log,
    AccountStatement,
};

class SuppliersController extends Controller
{
    /**
     * «الموردين و العملاء» is admin-only (account_type 2), the same rule the
     * sidebar uses -- enforced here too so the URLs cannot be opened directly.
     */
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
        $suppliers = Supplier::select('*')->orderBy('id','DESC')->get();
        return view('suppliers.all' , [ 
          "suppliers" => $suppliers,
        ]); 
    }

    /**
     * Show the form for creating a new resource. 
     */
    public function create()
    {
        return view('suppliers.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function save(StoreSupplierRequest $request)
    {
//        dd($request);
        $create = Supplier::create([
"name" => $request->name,
"email" => $request->email,
"address" => $request->address,
"phone_1" => $request->phone_1,
"phone_2" => $request->phone_2,
//"passport_id" => $request->passport_id,
//"passport_expiration_date" => $request->passport_expiration_date,
"debit_opening_balance" => $request->debit_opening_balance,
"opening_credit_balance" => $request->opening_credit_balance,
"status" => $request->status,
"type" => $request->type,
"limit_balance" => $request->limit_balance,
"in_index" => $request->in_index,
            "in_stat" => $request->in_stat,
"acc_type" => 2,
        ]);
        
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
            "log_txt" => "تم اضافة بيانات المورد  $request->name",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        
        
        return redirect()->route('site.suppliers'); 
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
        
        return view('suppliers.edit' , [
          "supplier" => $supplier,
        ]);
        
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSupplierRequest $request)
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
//        dd($opening_credit_balance , $debit_opening_balance);
        
        $supplier = $supplier[0];
        $update_supplier = Supplier::select('*')->where('id',$id)->update([
           "name" => $request->name,
           "email" => $request->email,
           "address" => $request->address,
           "phone_1" => $request->phone_1,
           "phone_2" => $request->phone_2,
//           "passport_id" => $request->passport_id,
//           "passport_expiration_date" => $request->passport_expiration_date,
           "debit_opening_balance" => $request->debit_opening_balance,
           "opening_credit_balance" => $request->opening_credit_balance,
           "status" => $request->status,
           "type" => $request->type,
           "acc_type" => $request->acc_type,
           "limit_balance" => $request->limit_balance,
           "in_index" => $request->in_index,
             "in_stat" => $request->in_stat,
        ]);
        
        $update_in_account = AccountStatement::select('*')->where('supp_client_id',$id)->where('is_supp_account',1)->update([
            "debit_balance" => $request->debit_opening_balance,
            "credit_balance" => $request->opening_credit_balance,
            "ledger_net_effect" => floatval($request->debit_opening_balance) - floatval($request->opening_credit_balance),
        ]);
        
           $save_log = Log::create([
            "log_txt" => "تم تعديل بيانات المورد  $request->name",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        
        
        $msg = "تم تعديل بيانات المورد " . $request->name;
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

        $blockers = $supplier->deletionBlockers();
        if ($blockers) {
            return Redirect::back()->withErrors(['delete' => "لا يمكن حذف «{$supplier->name}» لوجود: " . implode(' ، ', $blockers) . ". يمكنك إيقاف الحساب بدلاً من حذفه."]);
        }

        $delete = Supplier::select('*')->where('id',$id)->delete();

         $msg = "تم حذف بيانات المورد " . $supplier->name;
        
        
            $save_log = Log::create([
            "log_txt" => "تم حذف بيانات المورد " . $supplier->name,
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        
//        return $msg;
        return Redirect::back()->withErrors(['msg' => $msg]);
    }
}
