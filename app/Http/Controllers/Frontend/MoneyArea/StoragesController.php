<?php

namespace App\Http\Controllers\Frontend\MoneyArea;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Auth;
use Redirect;
use App\Http\Requests\StoreStorageRequest;
use App\Http\Requests\UpdateStorageRequest;
use App\Http\Requests\StoreSubStorageRequest;
use App\Http\Requests\UpdateSubStorageRequest;
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

        // Batched replacement for what used to be up to 6 per-row lookup
        // queries (Invoice-or-Bond by es_id, TicketUser, Storage,
        // Bank-or-Collector, User) executed inline in the Blade view, once
        // per statement row. Same conditions/branching the view used to
        // evaluate per row -- resolved here from pre-fetched keyed
        // collections instead.
        $esIds = $AccountStatements->pluck('es_id')->filter()->unique();
        $invoicesByEsId = Invoice::whereIn('es_id', $esIds)->get()->keyBy('es_id');
        $bondsByEsId = Bond::whereIn('es_id', $esIds)->get()->keyBy('es_id');

        $ticketSystemIds = $invoicesByEsId->pluck('ticket_system_id')->filter()->unique();
        $usersByTicketSystemId = TicketUser::whereIn('ticket_system_id', $ticketSystemIds)->get()->groupBy('ticket_system_id');

        $storageIds = $AccountStatements->pluck('supp_client_id')->filter()->unique();
        $storagesById = Storage::whereIn('id', $storageIds)->get()->keyBy('id');

        $bankIds = $bondsByEsId->where('money_way', 2)->pluck('bank_id')->filter()->unique();
        $banksById = Bank::whereIn('id', $bankIds)->get()->keyBy('id');

        $collectorIds = $bondsByEsId->filter(fn ($b) => $b->money_way != 1 && $b->money_way != 2)
            ->pluck('collector_info')->filter()->unique();
        $collectorsById = \App\Models\Collector::whereIn('id', $collectorIds)->get()->keyBy('id');

        $addedByIds = $AccountStatements->pluck('added_by')->filter()->unique();
        $usersById = \App\Models\User::whereIn('id', $addedByIds)->get()->keyBy('id');

        return view('moneyarea.storages.acc_all', [
            "AccountStatements" => $AccountStatements,
            "storage_info" => $storage_info,
            "st" => $st,
            "invoicesByEsId" => $invoicesByEsId,
            "bondsByEsId" => $bondsByEsId,
            "usersByTicketSystemId" => $usersByTicketSystemId,
            "storagesById" => $storagesById,
            "banksById" => $banksById,
            "collectorsById" => $collectorsById,
            "usersById" => $usersById,

"storage_id" => $request->storage_id,
"date_from" => $request->date_from,
"date_to" => $request->date_to,
"transaction_type" => $request->transaction_type,
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
        
        

        if(isset($date_from) && isset($date_to)){
            $AccountStatements = $AccountStatements->whereBetween('crt_date' , [$date_from , $date_to]);
        }

        if(isset($transaction_type)){
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
    public function save(StoreStorageRequest $request)
    {
        // P4 ledger bypass fix: a newly created storage previously started
        // with zero ledger history, so accounting:reconcile would list it
        // as "pending cutover" forever. Now writes an 'opening' entry for
        // the initial balance immediately, matching the same cutover
        // convention used for the pre-existing storage.
        DB::transaction(function () use ($request) {
            $create = Storage::create([
"name" => $request->name,
"type" => $request->type,
"bank_id" => $request->bank_id,
"bank_number" => $request->bank_number,
"balance" => $request->balance,
        ]);

            $balance = (float) $request->balance;
            StorageStatement::record([
                'storage_id' => $create->id,
                'bond_id' => null,
                'entry_type' => 'opening',
                'transaction_date' => date('Y-m-d'),
                'description' => "الرصيد الافتتاحي لخزينة $request->name",
                'reference' => null,
                'debit' => $balance < 0 ? abs($balance) : 0,
                'credit' => $balance >= 0 ? $balance : 0,
                'commission' => 0,
                'created_by' => Auth::user()->id,
            ]);

            $save_log = Log::create([
                "log_txt" => "تم اضافة الخزينة  $request->name",
                "log_ip" => $request->ip(),
                "log_by" => Auth::user()->id,
                "log_date" => date('Y-m-d'),
            ]);
        });

        return redirect()->route('site.storages');

    }
 public function sub_save(StoreSubStorageRequest $request)
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
    public function update(UpdateStorageRequest $request)
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
  public function sub_update(UpdateSubStorageRequest $request)
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
    public function delete(Request $request, $id)
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
    public function sub_delete(Request $request, $id)
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
