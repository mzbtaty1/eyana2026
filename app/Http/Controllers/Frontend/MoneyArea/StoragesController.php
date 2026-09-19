<?php

namespace App\Http\Controllers\Frontend\MoneyArea;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    StorageStatement,
    SubStorage,
};

class StoragesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $storages = Storage::select('*')->orderBy('id','DESC')->get();
        return view('moneyarea.storages.all' , [
            "storages" => $storages,
        ]);
    }
    public function sub_index()
    {
        $storages = SubStorage::select('*')->orderBy('id','DESC')->get();
        return view('moneyarea.sub_storages.all' , [
            "storages" => $storages,
        ]);
    }
 public function acc_all()
    {
     $storages = Storage::select('*')->orderBy('id','DESC')->get();
        return view('moneyarea.storages.all_get' , ["storages" => $storages]);
    }

    public function acc_get(Request $request){
        
        $id = $request->storage_id;
        
    
        
//        dd($request);
        
        

        $AccountStatements = AccountStatement::select('*')->where('is_storage' , 1);
        
        if($request->storage_id == 0){
            $storage_info = array();
            $st = 0;
        }else{
           
            
                 $check_storage = Storage::select('*')->where('id',$id)->get();
        abort_if(count($check_storage) == 0 , 404);
        $storage_info = $check_storage[0];
            
            $st = 1;
            $AccountStatements = $AccountStatements->where('supp_client_id' , $request->storage_id);
//            $request->invoice_beneficiaries
        }
        
        

        if(isset($request->date_from) && isset($request->date_to)){
            $AccountStatements = $AccountStatements->whereBetween('crt_date' , [$request->date_from , $request->date_to]);
        }
        
        if(isset($request->transaction_type)){
            $AccountStatements = $AccountStatements->where('transaction_type' , $request->transaction_type);
        }
         
        
        $AccountStatements = $AccountStatements->get();
        
        
        
        
//        $total_debit_balance = 0;
//        foreach ($AccountStatements as $AccountStatement) {
//            $total_debit_balance += $AccountStatement->debit_balance;
//        }
//        $total_credit_balance = 0;
//        foreach ($AccountStatements as $AccountStatement) {
//            $total_credit_balance += $AccountStatement->credit_balance;
//        }

        return view('moneyarea.storages.acc_all', [
            "AccountStatements" => $AccountStatements,
            "storage_info" => $storage_info,
            "st" => $st,
            
"storage_id" => $request->storage_id,
"date_from" => $request->date_from,
"date_to" => $request->date_to,
"transaction_type" => $request->transaction_type,
//            "total_debit_balance" => $total_debit_balance,
//            "total_credit_balance" => $total_credit_balance,
        ]);
        
        
        
    }
    
    public function stor_acc_print($storage_id , $date_from = null , $date_to = null , $transaction_type = null){
        
        $id = $storage_id;
        
        $AccountStatements = AccountStatement::select('*')->where('is_storage' , 1);
        
        if($storage_id == 0){
            $storage_info = [];
            $st = 0;
            $st_name = "";
        }else{
           
            
                 $check_storage = Storage::select('*')->where('id',$id)->get();
        abort_if(count($check_storage) == 0 , 404);
        $storage_info = $check_storage[0];
           $st_name =  $storage_info->name;
            $st = 1;
            $AccountStatements = $AccountStatements->where('supp_client_id' , $storage_id);
//            $request->invoice_beneficiaries
        }
        
        

        if(isset($request->date_from) && isset($request->date_to)){
            $AccountStatements = $AccountStatements->whereBetween('crt_date' , [$date_from , $date_to]);
        }
        
        if(isset($request->transaction_type)){
            $AccountStatements = $AccountStatements->where('transaction_type' , $transaction_type);
        }
         
        
        $AccountStatements = $AccountStatements->get();
//        dd($AccountStatements);
//        dd($st);
        return view('moneyarea.storages.print_report', [
            "AccountStatements" => $AccountStatements,
            "storage_info" => $storage_info,
            "st" => $st,
"date_from" => $date_from,
"date_to" => $date_to,
        ]);
        
    }
    
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $banks = Bank::select('*')->orderBy('id','DESC')->get();
        return view('moneyarea.storages.create' , [
            "banks" => $banks,
        ]);
    }
 public function sub_create()
    {
        $banks = Bank::select('*')->orderBy('id','DESC')->get();
     $storages = Storage::select('*')->orderBy('id','DESC')->get();
        return view('moneyarea.sub_storages.create' , [
            "banks" => $banks,
            "storages" => $storages,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function save(Request $request)
    {
        $create = Storage::create([
"name" => $request->name,
"type" => $request->type,
"bank_id" => $request->bank_id,
"bank_number" => $request->bank_number,
"balance" => $request->balance,
        ]);

        
          $save_log = Log::create([
            "log_txt" => "تم اضافة الخزينة  $request->name",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        
        
        return redirect()->route('site.storages');
        
    }
 public function sub_save(Request $request)
    {
        $create = SubStorage::create([
"main_storage" => $request->main_storage,
"name" => $request->name,
"type" => $request->type,
"bank_id" => $request->bank_id,
"bank_number" => $request->bank_number,
"balance" => $request->balance,
        ]);
        
     $save_log = Log::create([
            "log_txt" => "تم اضافة الخزينة الفرعية  $request->name",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
     
        return redirect()->route('site.sub_storages');
        
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
         $check_storage = Storage::select('*')->where('id',$id)->get();
        abort_if(count($check_storage) == 0 , 404);
        $storage_info = $check_storage[0];
        
        $banks = Bank::select('*')->orderBy('id','DESC')->get();
        
        
        
         
     
        
        
        return view('moneyarea.storages.edit' , [
            "storage_info" => $storage_info,
            "banks" => $banks,
        ]);
        
    }
    public function sub_edit($id)
    {
         $check_storage = SubStorage::select('*')->where('id',$id)->get();
        abort_if(count($check_storage) == 0 , 404);
        $storage_info = $check_storage[0];
         $storages = Storage::select('*')->orderBy('id','DESC')->get();
        $banks = Bank::select('*')->orderBy('id','DESC')->get();
        
         
        
        
        return view('moneyarea.sub_storages.edit' , [
            "storage_info" => $storage_info,
            "banks" => $banks,
            "storages" => $storages,
        ]);
        
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
//        dd($request);
        $id = (int) $request->id;

        // P3 safety fix + storage ledger: lock the storage row for the
        // duration of the transaction (this form previously overwrote
        // "balance" with zero locking/transaction at all), and log a
        // manual 'adjustment' ledger entry whenever the edit actually
        // changes the balance, so a direct admin edit can no longer
        // silently desync storage_statements from storages.balance.
        DB::transaction(function () use ($request, $id) {
            $storage_info = Storage::where('id',$id)->lockForUpdate()->first();
            abort_if(!$storage_info, 404);

            $save_log = Log::create([
                "log_txt" => "تم تعديل بيانات الخزينة $storage_info->name",
                "log_ip" => $request->ip(),
                "log_by" => Auth::user()->id,
                "log_date" => date('Y-m-d'),
            ]);

            $oldBalance = (float) $storage_info->balance;
            $newBalance = (float) $request->balance;

            $update_storage = Storage::where('id',$id)->update([
//            "name" => $request->name,
"type" => $request->type,
"bank_id" => $request->bank_id,
"bank_number" => $request->bank_number,
"balance" => $request->balance,

        ]);

            $delta = round($newBalance - $oldBalance, 2);
            if ($delta != 0) {
                StorageStatement::record([
                    'storage_id' => $id,
                    'bond_id' => null,
                    'entry_type' => 'adjustment',
                    'transaction_date' => date('Y-m-d'),
                    'description' => "تعديل يدوي لرصيد الخزينة {$storage_info->name}",
                    'reference' => null,
                    'debit' => $delta < 0 ? abs($delta) : 0,
                    'credit' => $delta > 0 ? $delta : 0,
                    'commission' => 0,
                    'created_by' => Auth::user()->id,
                ]);
            }
        });

        return redirect()->route('site.storages_edit' , $id);

    }
  public function sub_update(Request $request)
    {
//        dd($request);
        $id = (int) $request->id;

        // P3 safety fix: lock the sub-storage row for the duration of the
        // transaction (this form previously had zero locking/transaction
        // at all). No storage_statements entry is written here: the
        // approved design's FK is to storages.id only, and sub_storages
        // is a separate table whose balance is not reflected in
        // storages.balance by any existing code path -- logging a
        // sub-storage edit against its parent storage's ledger would
        // create a running_balance that no longer matches the parent
        // Storage's actual balance, corrupting reconciliation. Flagging
        // this as a scoping decision, not an oversight: a genuine
        // sub-storage ledger would need its own dedicated table.
        DB::transaction(function () use ($request, $id) {
            $storage_info = SubStorage::where('id',$id)->lockForUpdate()->first();
            abort_if(!$storage_info, 404);

            $save_log = Log::create([
                "log_txt" => "تم تعديل بيانات الخزينة الفرعية $storage_info->name",
                "log_ip" => $request->ip(),
                "log_by" => Auth::user()->id,
                "log_date" => date('Y-m-d'),
            ]);

            $update_storage = SubStorage::where('id',$id)->update([
                "main_storage" => $request->main_storage,
                "name" => $request->name,
"type" => $request->type,
"bank_id" => $request->bank_id,
"bank_number" => $request->bank_number,
"balance" => $request->balance,

        ]);
        });

        return redirect()->route('site.sub_storages_edit' , $id);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete($id)
    {
              $check_storage = Storage::select('*')->where('id',$id)->get();
        abort_if(count($check_storage) == 0 , 404);
        $storage_info = $check_storage[0];
        
        
          
           $save_log = Log::create([
            "log_txt" => "تم حذف بيانات الخزينة $storage_info->name",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        
        
        
        $delete = Storage::select('*')->where('id',$id)->delete();
        return redirect()->route('site.storages');
    }
    public function sub_delete($id)
    {
              $check_storage = SubStorage::select('*')->where('id',$id)->get();
        abort_if(count($check_storage) == 0 , 404);
        $storage_info = $check_storage[0];
        
          
           $save_log = Log::create([
            "log_txt" => "تم حذف بيانات الخزينة الفرعية $storage_info->name",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        
        $delete = SubStorage::select('*')->where('id',$id)->delete();
        return redirect()->route('site.sub_storages');
    }
}
