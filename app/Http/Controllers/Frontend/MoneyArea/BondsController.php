<?php

namespace App\Http\Controllers\Frontend\MoneyArea;

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
    Storage,
    SubStorage,
    Bond,
    Collector,
};

class BondsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $bonds = Bond::select('*')->orderBy('id','DESC')->get();
//        dd($bonds);
        return view('moneyarea.bonds.all' , ["bonds" => $bonds]);
    }
 public function daily_report()
    {
        $bonds = Bond::select('*')->where('crt_date' , date('Y-m-d'))->get();

     return view('moneyarea.bonds.daily_report' , ["bonds" => $bonds]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
         $storages = Storage::select('*')->orderBy('id','DESC')->get();
         $sub_storages = SubStorage::select('*')->orderBy('id','DESC')->get();
        
        $suppliers = Supplier::select('*')
            ->where('status', 1)
            ->orderBy('id', 'DESC')
            ->get();
        
        $banks = Bank::select('*')->orderBy('id','DESC')->get();
         $collectors = Collector::select('*')->orderBy('id','DESC')->get();
        
        return view('moneyarea.bonds.create' , [
            "storages" => $storages,
            "sub_storages" => $sub_storages,
            "suppliers" => $suppliers,
            "banks" => $banks,
            "collectors" => $collectors,
        ]);
    }
    public function print_one($id){
        $id = (int) $id;
        $check_bond = Bond::select('*')->where('id',$id)->get();
        abort_if(count($check_bond) == 0 , 404);
        $check_bond = $check_bond[0];
        
        
        return view('moneyarea.bonds.print_one' , [
            "bond_info" => $check_bond,
        ]);
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function save(Request $request)
    {
//        dd($request);
        
$type_slctd = $request->type_slctd;
$storage_id = $request->storage_id;
$sub_id = $request->sub_id;
$supp_id = $request->supp_id;
$amount = $request->amount;
$money_way = $request->money_way;
$bank_id = $request->bank_id;
$collector_info = $request->collector_info;
$transaction_info = $request->transaction_info;
$date = $request->crt_date;
        
    if($type_slctd == 1){
        
        
        
             if ($request->hasFile('myPoster')) {
            $imagePath = $request->file('myPoster');
            $imageName = $imagePath->getClientOriginalName();
            $extension = $imagePath->extension();

            $list_allow = ["jpg", "png", "jpeg", "webp", "pdf"];
            if (!in_array($extension, $list_allow)) {
                $msg = "برجاء الالتزام بالصيغة المحددة لرفع صورة / ملف التذكرة الخاصة بالفاتورة";
                return Redirect::back()->withErrors(['msg' => $msg]);
            }

            $imageNewName = \Str::random(32) . "." . $extension;
            $path = 'storage/' . $request->file('myPoster')->storeAs('bonds_files', $imageNewName, 'public');
        } else {
            $path = "";
        }
        
        
        // P0.5: bank/e-wallet commission -- payment bonds (type_slctd==1) with money_way==2 only.
        $commission = ($request->money_way == 2) ? (float) $request->commission : 0;
        $total = (float) $amount + $commission;
        
           $check_storage = Storage::select('*')->where('id',$storage_id)->get();
        abort_if(count($check_storage) == 0 , 404);
        $storage_info = $check_storage[0];
        
        $update_storage = Storage::select('*')->where('id',$storage_id)->update([
            "balance" => $storage_info->balance - $total,
        ]);
        
        if($request->money_way == 2){
            $bank_info = Bank::select('*')->where('id',$request->bank_id)->get();
        $bank_info = $bank_info[0];
            
            
            $update_bank = Bank::select('*')->where('id',$request->bank_id)->update([
            "bank_balance" => $bank_info->bank_balance - $total,
        ]);
        }
        
        
       $createBond = Bond::create([
           "file_path" => $path, 
           "type" => $type_slctd,
           "system_id" => \Str::random(8),
           "sub_id" => $sub_id,
           "from_account" => $storage_id,
           "from_type" => "storage",
           "to_account" => $supp_id,
           "to_type" => "supplier",
           "amount" => $amount,
           "commission" => $commission,
           "info" => $transaction_info,      
           "money_way" => $money_way,
           "bank_id" => $bank_id,
           "collector_info" => $collector_info,
           "crt_date" => $date, 
           "created_by" => Auth::user()->id,
        ]);  
        
         $update_es_id = Bond::select('*')
            ->where('id', $createBond->id)
            ->update([
                "es_id" => "FLY-BD" . $createBond->id,
            ]);
        
        
  
       
        

        
        
         $supplier = Supplier::select('*')->where('id',$supp_id)->get();
        abort_if(count($supplier) == 0 , 404);
        
        $supplier = $supplier[0];
        
        
        $storage_log = AccountStatement::create([
            "supp_client_id" => $storage_id,
            "trans_storage" => 1,
            "is_storage" => 1, 
            "invoice_type" => 9,
            "sub_id" => $sub_id,
            "es_id" => "FLY-BD" . $createBond->id,
            "invoice_date" => $date,
            "debit_balance" => 0,
            "credit_balance" => $total,
            "ledger_net_effect" => -$total,
            "transaction_txt" => "سند دفع من خزينة $storage_info->name لصالح $supplier->name",
            "transaction_type" => 2,
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
        ]);
        
           
        $storage_log2 = AccountStatement::create([
            "supp_client_id" => $supp_id,
            "trans_storage" => 1,
            "invoice_type" => 9,
            "sub_id" => $sub_id,
            "es_id" => "FLY-BD" . $createBond->id,
            "invoice_date" => $date,
            "debit_balance" => $amount,
            "credit_balance" => 0,
            "ledger_net_effect" => $amount,
            "transaction_txt" => "سند دفع من خزينة $storage_info->name لصالح $supplier->name",
            "transaction_type" => 2,
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
        ]);
        
        
        
    }else{
        
        // dd($request->money_way2);
        // dd(5);
        
            if ($request->hasFile('myPoster2')) {
            $imagePath = $request->file('myPoster2');
            $imageName = $imagePath->getClientOriginalName();
            $extension = $imagePath->extension();

            $list_allow = ["jpg", "png", "jpeg", "webp", "pdf"];
            if (!in_array($extension, $list_allow)) {
                $msg = "برجاء الالتزام بالصيغة المحددة لرفع صورة / ملف التذكرة الخاصة بالفاتورة";
                return Redirect::back()->withErrors(['msg' => $msg]);
            }

            $imageNewName = \Str::random(32) . "." . $extension;
            $path = 'storage/' . $request->file('myPoster2')->storeAs('bonds_files', $imageNewName, 'public');
        } else {
            $path = "";
        }
        
$storage_id = $request->storage_id2;
$sub_id = $request->sub_id2;
$supp_id = $request->supp_id2;
$amount = $request->amount2;
$money_way = $request->money_way2;
$bank_id = $request->bank_id2;
$collector_info = $request->collector_info2;
$transaction_info = $request->transaction_info2;
$date = $request->crt_date2;
        
        
// dd($request->money_way2);

if ($request->money_way2 == 2) {
    // dd(50); 
    $bank = Bank::find($request->bank_id2);

    if ($bank) { // التأكد من أن البنك موجود
        $bank->increment('bank_balance', $amount);

        // dd('none');
    }else{
        // dd('nBank');
    } 
}
 

       
              $check_storage = Storage::select('*')->where('id',$storage_id)->get();
        abort_if(count($check_storage) == 0 , 404);
        $storage_info = $check_storage[0];
        
        $update_storage = Storage::select('*')->where('id',$storage_id)->update([
            "balance" => $storage_info->balance + $amount,
        ]);
        
        
       
         
        
        $createBond = Bond::create([
           "file_path" => $path,
           "type" => $type_slctd,
           "system_id" => \Str::random(8),
            "sub_id" => $sub_id,
           "from_account" => $supp_id,
           "from_type" => "supplier",
           "to_account" => $storage_id,
           "to_type" => "storage",
           "amount" => $amount,
           "info" => $transaction_info,      
           "money_way" => $money_way,
           "bank_id" => $bank_id,
           "collector_info" => $collector_info,
           "crt_date" => $date, 
           "created_by" => Auth::user()->id,

        ]);  
        
         $update_es_id = Bond::select('*')
            ->where('id', $createBond->id)
            ->update([
                "es_id" => "FLY-BD" . $createBond->id,
            ]);
        
        
        
            $supplier = Supplier::select('*')->where('id',$supp_id)->get();
        abort_if(count($supplier) == 0 , 404);
        
        $supplier = $supplier[0];
        
        
        $storage_log = AccountStatement::create([
            "supp_client_id" => $storage_id,
            "is_storage" => 1,
            "trans_storage" => 1,
            "invoice_type" => 10,
            "sub_id" => $sub_id,
            "es_id" => "FLY-BD" . $createBond->id,
            "invoice_date" => $date,
            "debit_balance" => $amount,
            "credit_balance" => 0,
            "ledger_net_effect" => $amount,
            "transaction_txt" => "سند قبض من حساب $supplier->name لصالح خزينة $storage_info->name",
            "transaction_type" => 2,
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
        ]);
        
           
        $storage_log2 = AccountStatement::create([
            "supp_client_id" => $supp_id,
            "trans_storage" => 1,
            "invoice_type" => 10,
            "sub_id" => $sub_id,
            "es_id" => "FLY-BD" . $createBond->id,
            "invoice_date" => $date,
            "debit_balance" => 0,
            "credit_balance" => $amount,
            "ledger_net_effect" => -$amount,
            "transaction_txt" => "سند قبض من حساب $supplier->name لصالح خزينة $storage_info->name",
            "transaction_type" => 2,
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
        ]);
        
        
        
        
        
    }    
        
        return redirect()->route('site.bonds');
        
    }

    /**
     * Display the specified resource.
     */
    public function delete($id)
    {
         $id = (int) $id;
        $check_bond = Bond::select('*')->where('id',$id)->get();
        abort_if(count($check_bond) == 0 , 404);
        $check_bond = $check_bond[0];
        
        if($check_bond->type == 1){
            $storage_id = $check_bond->from_account;
            
            $amount = $check_bond->amount;
            $commission = $check_bond->commission ?? 0;
            $total = (float) $amount + (float) $commission;
        $check_storage = Storage::select('*')->where('id',$storage_id)->get();
        abort_if(count($check_storage) == 0 , 404);
        $storage_info = $check_storage[0];
        
        $update_storage = Storage::select('*')->where('id',$storage_id)->update([
            "balance" => $storage_info->balance + $total,
        ]);

        if ($check_bond->money_way == 2 && $check_bond->bank_id) {
            $check_bank = Bank::select('*')->where('id',$check_bond->bank_id)->get();
            if (count($check_bank) > 0) {
                $bank_info = $check_bank[0];
                Bank::select('*')->where('id',$check_bond->bank_id)->update([
                    "bank_balance" => $bank_info->bank_balance + $total,
                ]);
            }
        }
        
            
        }else{
            
            
            $storage_id = $check_bond->to_account;
            
            $amount = $check_bond->amount;
        $check_storage = Storage::select('*')->where('id',$storage_id)->get();
        abort_if(count($check_storage) == 0 , 404);
        $storage_info = $check_storage[0];
        
        $update_storage = Storage::select('*')->where('id',$storage_id)->update([
            "balance" => $storage_info->balance - $amount,
        ]);  
            
        }
        
        
        $delete  = Bond::select('*')->where('id',$id)->delete();
        $delete2  = AccountStatement::select('*')->where('es_id',$check_bond->es_id)->delete();
        
        return redirect()->route('site.bonds');
        
        
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
                 $id = (int) $id;
        $check_bond = Bond::select('*')->where('id',$id)->get();
        abort_if(count($check_bond) == 0 , 404);
        $check_bond = $check_bond[0];
        
        
          $storages = Storage::select('*')->orderBy('id','DESC')->get();
         $sub_storages = SubStorage::select('*')->orderBy('id','DESC')->get();
        
        $suppliers = Supplier::select('*')
            ->where('status', 1)
            ->orderBy('id', 'DESC')
            ->get();
        
        $banks = Bank::select('*')->orderBy('id','DESC')->get();
         $collectors = Collector::select('*')->orderBy('id','DESC')->get();
        
        return view('moneyarea.bonds.edit' , [
            "storages" => $storages,
            "sub_storages" => $sub_storages,
            "suppliers" => $suppliers,
            "banks" => $banks,
            "collectors" => $collectors,
            "check_bond" => $check_bond,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function save_update(Request $request)
    {
       $id = (int) $request->bond_id;
          $check_bond = Bond::select('*')->where('id',$id)->get();
        abort_if(count($check_bond) == 0 , 404);
        $check_bond = $check_bond[0];
        
        if($request->type_slctd == 1){
         $type_slctd = $request->type_slctd;
$storage_id = $request->storage_id;
$sub_id = $request->sub_id;
$supp_id = $request->supp_id;
$amount = $request->amount;
$money_way = $request->money_way;
$bank_id = $request->bank_id;
$collector_info = $request->collector_info;
$transaction_info = $request->transaction_info;
$date = $request->crt_date;
            
                    
        
             if ($request->hasFile('myPoster')) {
            $imagePath = $request->file('myPoster');
            $imageName = $imagePath->getClientOriginalName();
            $extension = $imagePath->extension();

            $list_allow = ["jpg", "png", "jpeg", "webp", "pdf"];
            if (!in_array($extension, $list_allow)) {
                $msg = "برجاء الالتزام بالصيغة المحددة لرفع صورة / ملف التذكرة الخاصة بالفاتورة";
                return Redirect::back()->withErrors(['msg' => $msg]);
            }

            $imageNewName = \Str::random(32) . "." . $extension;
            $path = 'storage/' . $request->file('myPoster')->storeAs('bonds_files', $imageNewName, 'public');
        } else {
            $path = $check_bond->file_path;
        }
//        dd($request->crt_date);
       
        // P0.5: capture the ORIGINAL bond state before any mutation, so the
        // original Storage/Bank movement (including any original commission)
        // can be reversed using the ORIGINAL storage_id/bank_id/money_way --
        // never the new request values.
        $orig_amount     = $check_bond->amount;
        $orig_commission = $check_bond->commission ?? 0;
        $orig_money_way  = $check_bond->money_way;
        $orig_bank_id    = $check_bond->bank_id;
        $orig_storage_id = $check_bond->from_account;
        $orig_total      = (float) $orig_amount + (float) $orig_commission;

        $new_commission = ($request->money_way == 2) ? (float) $request->commission : 0;
        $new_total      = (float) $amount + $new_commission;

               $createBond = Bond::select('*')->where('id',$id)->update([
           "file_path" => $path, 
           "type" => 1,
           "sub_id" => $sub_id,
           "from_account" => $storage_id,
           "from_type" => "storage",
           "to_account" => $supp_id,
           "to_type" => "supplier",
           "amount" => $amount,
           "commission" => $new_commission,
           "info" => $transaction_info,      
           "money_way" => $money_way,
           "bank_id" => $bank_id,
           "collector_info" => $collector_info,
           "crt_date" => $request->crt_date, 
        ]);  
            
            
        // Step 1: reverse the ORIGINAL Storage movement (on the ORIGINAL storage_id).
        $check_orig_storage = Storage::select('*')->where('id',$orig_storage_id)->get();
        abort_if(count($check_orig_storage) == 0 , 404);
        $orig_storage_info = $check_orig_storage[0];

        Storage::select('*')->where('id',$orig_storage_id)->update([
            "balance" => $orig_storage_info->balance + $orig_total,
        ]);

        // Step 1b: reverse the ORIGINAL Bank movement, only if the original bond used money_way==2.
        if ($orig_money_way == 2 && $orig_bank_id) {
            $check_orig_bank = Bank::select('*')->where('id',$orig_bank_id)->get();
            if (count($check_orig_bank) > 0) {
                $orig_bank_info = $check_orig_bank[0];
                Bank::select('*')->where('id',$orig_bank_id)->update([
                    "bank_balance" => $orig_bank_info->bank_balance + $orig_total,
                ]);
            }
        }

        // Step 2: apply the NEW Storage movement (on the NEW storage_id).
           $check_storage = Storage::select('*')->where('id',$storage_id)->get();
        abort_if(count($check_storage) == 0 , 404);
        $storage_info = $check_storage[0];
        
        $update_storage = Storage::select('*')->where('id',$storage_id)->update([
            "balance" => $storage_info->balance - $new_total,
        ]);

        // Step 2b: apply the NEW Bank movement, only if the new money_way==2.
        if ($request->money_way == 2 && $bank_id) {
            $check_new_bank = Bank::select('*')->where('id',$bank_id)->get();
            if (count($check_new_bank) > 0) {
                $new_bank_info = $check_new_bank[0];
                Bank::select('*')->where('id',$bank_id)->update([
                    "bank_balance" => $new_bank_info->bank_balance - $new_total,
                ]);
            }
        }

$rmv = AccountStatement::select('*')->where('es_id',$check_bond->es_id)->delete();
        
        
         $supplier = Supplier::select('*')->where('id',$supp_id)->get();
        abort_if(count($supplier) == 0 , 404);
        
        $supplier = $supplier[0];
        
        
        $storage_log = AccountStatement::create([
            "supp_client_id" => $storage_id,
            "trans_storage" => 1,
            "is_storage" => 1, 
            "invoice_type" => 9,
            "sub_id" => $sub_id,
            "es_id" => $check_bond->es_id,
            "invoice_date" => $date,
            "debit_balance" => 0,
            "credit_balance" => $new_total,
            "ledger_net_effect" => -$new_total,
            "transaction_txt" => "سند دفع من خزينة $storage_info->name لصالح $supplier->name",
            "transaction_type" => 2,
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
        ]);
        
           
        $storage_log2 = AccountStatement::create([
            "supp_client_id" => $supp_id,
            "trans_storage" => 1,
            "invoice_type" => 9,
            "sub_id" => $sub_id,
            "es_id" => $check_bond->es_id,
            "invoice_date" => $date,
            "debit_balance" => $amount,
            "credit_balance" => 0,
            "ledger_net_effect" => $amount,
            "transaction_txt" => "سند دفع من خزينة $storage_info->name لصالح $supplier->name",
            "transaction_type" => 2,
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
        ]);
            
        }else{
            dd('TWo');
        }
        
        return redirect()->route('site.bonds_edit',$id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function bonds_approve($id)
    {
                 $check = Bond::select('*')->where('id' , $id)->get();
        if(count($check) == 0){
            $st_code = 404;
        }else{
            $st_code = 200;
            $update = Bond::select('*')->where('id' , $id)->update([
                "bond_status" => 1,
            ]);
        }
        
        
             return response()->json([
    'status_code' => $st_code,
   
]);
    }
}
