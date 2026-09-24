<?php

namespace App\Http\Controllers\Frontend\MoneyArea;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Auth;
use Redirect;
use App\Http\Requests\StoreBondRequest;
use App\Http\Requests\UpdateBondRequest;
use App\Services\CounterPayments;
use App\Services\BondReversal;
use App\Models\{
    Supplier,
    Invoice,
    TicketUser,
    TicketVendor,
    Airline,
    AccountStatement,
    Log,
    Bank,
    BankStatement,
    Storage,
    StorageStatement,
    SubStorage,
    Bond,
    Collector,
    User,
};

class BondsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $bonds = Bond::select('*')->orderBy('id','DESC')->get();

        // Batched replacement for what used to be up to 5 per-row lookup
        // queries (Storage/Supplier for from_account+to_account, SubStorage,
        // Bank, Collector, User) executed inline in the Blade view, once per
        // bond. Same conditions as the view previously used per row.
        $storageIds = $bonds->where('from_type', 'storage')->pluck('from_account')
            ->merge($bonds->where('to_type', 'storage')->pluck('to_account'))
            ->filter()->unique();
        $supplierIds = $bonds->where('from_type', '!=', 'storage')->pluck('from_account')
            ->merge($bonds->where('to_type', '!=', 'storage')->pluck('to_account'))
            ->filter()->unique();
        $subStorageIds = $bonds->pluck('sub_id')->filter(fn ($v) => (int) $v !== 0)->unique();
        $bankIds = $bonds->where('money_way', 2)->pluck('bank_id')->filter()->unique();
        $collectorIds = $bonds->filter(fn ($b) => $b->money_way != 1 && $b->money_way != 2)
            ->pluck('collector_info')->filter()->unique();
        $userIds = $bonds->pluck('created_by')->filter()->unique();

        return view('moneyarea.bonds.all' , [
            "bonds" => $bonds,
            "bondStorages" => Storage::whereIn('id', $storageIds)->get()->keyBy('id'),
            "bondSuppliers" => Supplier::whereIn('id', $supplierIds)->get()->keyBy('id'),
            "bondSubStorages" => SubStorage::whereIn('id', $subStorageIds)->get()->keyBy('id'),
            "bondBanks" => Bank::whereIn('id', $bankIds)->get()->keyBy('id'),
            "bondCollectors" => Collector::whereIn('id', $collectorIds)->get()->keyBy('id'),
            "bondUsers" => User::whereIn('id', $userIds)->get()->keyBy('id'),
        ]);
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
    public function save(StoreBondRequest $request)
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
        
        // P2 safety fix: lock the Storage/Bank rows and make the whole
        // money movement + ledger write atomic, mirroring the fix already
        // applied to InvoicesController::pay_part_save(). Without this,
        // two concurrent bond submissions touching the same storage/bank
        // can both read the same starting balance and one update is lost.
        // Optional link to a Counter Customer invoice: a refund payout ("رد مبلغ للعميل")
        // that settles the amount due to the client on that invoice.
        $linkInvoiceId = (int) $request->invoice_id;

        $payoutError = DB::transaction(function () use ($request, $path, $type_slctd, $storage_id, $sub_id, $supp_id, $amount, $commission, $money_way, $bank_id, $collector_info, $transaction_info, $date, $total, $linkInvoiceId) {
        // Linked payout: lock the invoice FIRST (same order as the invoice payment screen:
        // invoice -> storage -> bank) and never pay out more than is due to the client.
        $linkInvoice = null;
        if ($linkInvoiceId) {
            $linkInvoice = Invoice::where('id', $linkInvoiceId)->lockForUpdate()->first();
            if (!$linkInvoice || !CounterPayments::isPayable($linkInvoice) || (int) $linkInvoice->invoice_beneficiaries !== (int) $supp_id) {
                return Redirect::back()->withErrors(['msg' => 'الفاتورة المرتبطة غير صحيحة لهذا الحساب']);
            }
            if ((float) $amount > CounterPayments::summary($linkInvoice)['due_to_client'] + 0.005) {
                return Redirect::back()->withErrors(['msg' => 'قيمة الرد أكبر من المبلغ المستحق للعميل على الفاتورة.']);
            }
        }

        $storage_info = Storage::where('id',$storage_id)->lockForUpdate()->first();
        abort_if(!$storage_info, 404);

        Storage::where('id',$storage_id)->update([
            "balance" => $storage_info->balance - $total,
        ]);

        if($request->money_way == 2){
            $bank_info = Bank::where('id',$request->bank_id)->lockForUpdate()->first();

            Bank::where('id',$request->bank_id)->update([
            "bank_balance" => $bank_info->bank_balance - $total,
        ]);
        }
        // P2 bank ledger: recorded once $createBond/$supplier are known below.


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

        // linked to the invoice -> part of its payment history (not deletable / editable)
        if ($linkInvoice) {
            Bond::where('id', $createBond->id)->update(["is_invoice" => 1, "invoice_id" => $linkInvoice->id]);
        }
        
        
  
       
        

        
        
         $supplier = Supplier::where('id',$supp_id)->first();
        abort_if(!$supplier, 404);


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
            "transaction_txt" => "سند دفع من خزينة $storage_info->name لصالح $supplier->name" . ($linkInvoice ? " - رد مبلغ مستحق لفاتورة {$linkInvoice->es_id}" : ""),
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
            "transaction_txt" => "سند دفع من خزينة $storage_info->name لصالح $supplier->name" . ($linkInvoice ? " - رد مبلغ مستحق لفاتورة {$linkInvoice->es_id}" : ""),
            "transaction_type" => 2,
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
        ]);

        // P3 storage ledger: one debit entry per payment bond -- every
        // payment bond touches Storage regardless of money_way, unlike the
        // bank ledger which only applies when money_way==2. Debit = amount
        // + commission, matching exactly what was just subtracted from
        // storage balance above.
        StorageStatement::record([
            'storage_id' => (int) $storage_id,
            'bond_id' => $createBond->id,
            'entry_type' => 'bond',
            'transaction_date' => $date,
            'description' => "سند دفع رقم FLY-BD{$createBond->id} من خزينة {$storage_info->name} لصالح $supplier->name",
            'reference' => $createBond->es_id,
            'debit' => $total,
            'credit' => 0,
            'commission' => $commission,
            'created_by' => Auth::user()->id,
        ]);

        // P2 bank ledger: one debit entry per payment bond that actually
        // moved money out of a bank/e-wallet. Debit = amount + commission,
        // matching exactly what was just subtracted from bank_balance above.
        if ($request->money_way == 2) {
            BankStatement::record([
                'bank_id' => (int) $request->bank_id,
                'bond_id' => $createBond->id,
                'entry_type' => 'bond',
                'transaction_date' => $date,
                'description' => "سند دفع رقم FLY-BD{$createBond->id} من بنك {$bank_info->bank_name} لصالح $supplier->name",
                'reference' => $createBond->es_id,
                'debit' => $total,
                'credit' => 0,
                'commission' => $commission,
                'created_by' => Auth::user()->id,
            ]);
        }
        return null;
        });
        if ($payoutError) {
            return $payoutError;
        }
        if ($linkInvoiceId) {
            return redirect()->route('site.invoices')->with('success', 'تم رد المبلغ للعميل');
        }



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

// P2 safety fix: lock the Storage/Bank rows and make the whole money
// movement + ledger write atomic, mirroring the fix already applied to
// InvoicesController::pay_part_save(). Without this, two concurrent bond
// submissions touching the same storage/bank can race and lose an update.
// Canonical lock order (matches save()'s other branch, delete() and
// save_update()): Storage before Bank, to avoid ABBA deadlocks between
// concurrent requests that touch the same storage+bank pair.
DB::transaction(function () use ($request, $path, $type_slctd, $storage_id, $sub_id, $supp_id, $amount, $money_way, $bank_id, $collector_info, $transaction_info, $date) {

              $storage_info = Storage::where('id',$storage_id)->lockForUpdate()->first();
        abort_if(!$storage_info, 404);

        Storage::where('id',$storage_id)->update([
            "balance" => $storage_info->balance + $amount,
        ]);

if ($request->money_way2 == 2) {
    // dd(50);
    $bank = Bank::where('id', $request->bank_id2)->lockForUpdate()->first();

    if ($bank) { // التأكد من أن البنك موجود
        $bank->increment('bank_balance', $amount);

        // dd('none');
    }else{
        // dd('nBank');
    }
}





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
        
        
        
            $supplier = Supplier::where('id',$supp_id)->first();
        abort_if(!$supplier, 404);


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

        // P3 storage ledger: one credit entry per receipt bond -- every
        // receipt bond touches Storage regardless of money_way. Receipt
        // bonds never carry a commission, so credit = amount.
        StorageStatement::record([
            'storage_id' => (int) $storage_id,
            'bond_id' => $createBond->id,
            'entry_type' => 'bond',
            'transaction_date' => $date,
            'description' => "سند قبض رقم FLY-BD{$createBond->id} لصالح خزينة {$storage_info->name} من حساب $supplier->name",
            'reference' => $createBond->es_id,
            'debit' => 0,
            'credit' => $amount,
            'commission' => 0,
            'created_by' => Auth::user()->id,
        ]);

        // P2 bank ledger: one credit entry per receipt bond that actually
        // moved money into a bank/e-wallet. Receipt bonds never carry a
        // commission (unchanged from existing behavior), so credit = amount.
        if ($request->money_way2 == 2 && $bank) {
            BankStatement::record([
                'bank_id' => (int) $request->bank_id2,
                'bond_id' => $createBond->id,
                'entry_type' => 'bond',
                'transaction_date' => $date,
                'description' => "سند قبض رقم FLY-BD{$createBond->id} لصالح بنك {$bank->bank_name} من حساب $supplier->name",
                'reference' => $createBond->es_id,
                'debit' => 0,
                'credit' => $amount,
                'commission' => 0,
                'created_by' => Auth::user()->id,
            ]);
        }
        });





    }

        return redirect()->route('site.bonds');
        
    }

    /**
     * Display the specified resource.
     */
    public function delete($id)
    {
        $id = (int) $id;
        // Invoice-payment vouchers (counter-customer "سداد") are part of the invoice's
        // payment history: never deleted or edited here -- a correction is a reversing entry.
        if ((int) Bond::where('id', $id)->value('is_invoice') === 1) {
            return Redirect::back()->withErrors(['msg' => 'لا يمكن حذف أو تعديل سند سداد فاتورة؛ سجل السداد محفوظ في كشف الحساب والخزنة/البنك. للتصحيح استخدم قيد عكسي.']);
        }

        // P2 safety fix: lock the Bond row plus whichever Storage/Bank rows
        // it moved money through, and make the reversal + delete atomic,
        // mirroring the fix already applied to InvoicesController::pay_part_save().
        // Without this, a concurrent request touching the same storage/bank
        // can race with this reversal and lose an update.
        // P2 safety fix: lock the Bond row plus whichever Storage/Bank rows
        // it moved money through, and make the reversal + delete atomic.
        // The reversal itself (balances + immutable storage/bank reversal
        // entries) is shared with the Counter Customer invoice deletion.
        DB::transaction(function () use ($id) {
            $check_bond = Bond::where('id',$id)->lockForUpdate()->first();
            abort_if(!$check_bond, 404);

            BondReversal::reverse($check_bond, "عكس سند محذوف رقم {$check_bond->es_id}");
        });

        return redirect()->route('site.bonds');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
                 $id = (int) $id;
        // Invoice-payment vouchers (counter-customer "سداد") are part of the invoice's
        // payment history: never deleted or edited here -- a correction is a reversing entry.
        if ((int) Bond::where('id', $id)->value('is_invoice') === 1) {
            return Redirect::back()->withErrors(['msg' => 'لا يمكن حذف أو تعديل سند سداد فاتورة؛ سجل السداد محفوظ في كشف الحساب والخزنة/البنك. للتصحيح استخدم قيد عكسي.']);
        }
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
    public function save_update(UpdateBondRequest $request)
    {
       $id = (int) $request->bond_id;
        // Invoice-payment vouchers (counter-customer "سداد") are part of the invoice's
        // payment history: never deleted or edited here -- a correction is a reversing entry.
        if ((int) Bond::where('id', $id)->value('is_invoice') === 1) {
            return Redirect::back()->withErrors(['msg' => 'لا يمكن حذف أو تعديل سند سداد فاتورة؛ سجل السداد محفوظ في كشف الحساب والخزنة/البنك. للتصحيح استخدم قيد عكسي.']);
        }
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

        $new_commission = ($request->money_way == 2) ? (float) $request->commission : 0;
        $new_total      = (float) $amount + $new_commission;

        // P2 safety fix: lock every row this edit touches -- the Bond row
        // itself, then every distinct Storage row, then every distinct Bank
        // row -- in one canonical, deterministic order before mutating
        // anything, and make the whole reversal + re-apply + ledger rewrite
        // atomic. This mirrors the fix already applied to
        // InvoicesController::pay_part_save().
        //
        // Canonical lock order (shared with save() and delete()): Bond,
        // then Storage rows ascending by id, then Bank rows ascending by
        // id, with duplicate ids (e.g. editing a bond back onto the same
        // storage/bank) locked only once. This avoids ABBA deadlocks
        // between concurrent requests that touch overlapping rows in
        // different orders.
        //
        // The ORIGINAL bond state (orig_amount/orig_storage_id/etc.) is
        // derived from the Bond row AFTER it is locked below, not from the
        // pre-transaction $check_bond read above (that read is only used
        // for the file_path fallback and the early 404 check, both
        // non-financial). Deriving orig_* from a locked row prevents a
        // concurrent edit or delete of the very same bond from committing
        // between the read and this transaction, which would otherwise
        // make this reversal silently wrong.
        DB::transaction(function () use ($request, $id, $path, $storage_id, $sub_id, $supp_id, $amount, $money_way, $bank_id, $collector_info, $transaction_info, $date, $new_commission, $new_total) {

            // Canonical lock order, step 1: the Bond row being edited.
            $check_bond = Bond::where('id', $id)->lockForUpdate()->first();
            abort_if(!$check_bond, 404);

            $orig_amount     = $check_bond->amount;
            $orig_commission = $check_bond->commission ?? 0;
            $orig_money_way  = $check_bond->money_way;
            $orig_bank_id    = $check_bond->bank_id;
            $orig_storage_id = (int) $check_bond->from_account;
            $orig_total      = (float) $orig_amount + (float) $orig_commission;

            $new_storage_id     = (int) $storage_id;
            $orig_bank_id_lock  = ($orig_money_way == 2 && $orig_bank_id) ? (int) $orig_bank_id : null;
            $new_bank_id_lock   = ($request->money_way == 2 && $bank_id) ? (int) $bank_id : null;

            // Canonical lock order, step 2: Storage rows, ascending id, deduplicated.
            $storageIds = array_unique([$orig_storage_id, $new_storage_id]);
            sort($storageIds);
            $storages = [];
            foreach ($storageIds as $sid) {
                $storages[$sid] = Storage::where('id', $sid)->lockForUpdate()->first();
                abort_if(!$storages[$sid], 404);
            }

            // Canonical lock order, step 3: Bank rows, ascending id, deduplicated.
            $bankIds = array_values(array_unique(array_filter(
                [$orig_bank_id_lock, $new_bank_id_lock],
                fn ($v) => $v !== null
            )));
            sort($bankIds);
            $banks = [];
            foreach ($bankIds as $bid) {
                $bankRow = Bank::where('id', $bid)->lockForUpdate()->first();
                if ($bankRow) {
                    $banks[$bid] = $bankRow;
                }
            }

               $createBond = Bond::where('id',$id)->update([
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
        Storage::where('id',$orig_storage_id)->update([
            "balance" => $storages[$orig_storage_id]->balance + $orig_total,
        ]);
        // Keep the in-memory copy in sync in case orig_storage_id === new_storage_id,
        // so Step 2 below (re)uses the post-reversal balance instead of a stale one.
        $storages[$orig_storage_id]->balance += $orig_total;

        // P3 storage ledger: void the original storage entry and append its
        // exact reversal -- runs unconditionally, every edit touches Storage.
        StorageStatement::reverseActiveEntryForBond(
            $id,
            "عكس السند الأصلي رقم {$check_bond->es_id} بسبب تعديل السند",
            Auth::user()->id
        );

        // Step 1b: reverse the ORIGINAL Bank movement, only if the original bond used money_way==2.
        if ($orig_bank_id_lock !== null && isset($banks[$orig_bank_id_lock])) {
            Bank::where('id',$orig_bank_id_lock)->update([
                "bank_balance" => $banks[$orig_bank_id_lock]->bank_balance + $orig_total,
            ]);
            $banks[$orig_bank_id_lock]->bank_balance += $orig_total;

            // P2 bank ledger: void the original bank entry and append its
            // exact reversal -- the original row is never deleted or edited.
            BankStatement::reverseActiveEntryForBond(
                $id,
                "عكس السند الأصلي رقم {$check_bond->es_id} بسبب تعديل السند",
                Auth::user()->id
            );
        }

        // Step 2: apply the NEW Storage movement (on the NEW storage_id).
        $storage_info = $storages[$new_storage_id];
        Storage::where('id',$storage_id)->update([
            "balance" => $storage_info->balance - $new_total,
        ]);

        // Step 2b: apply the NEW Bank movement, only if the new money_way==2.
        if ($new_bank_id_lock !== null && isset($banks[$new_bank_id_lock])) {
            Bank::where('id',$new_bank_id_lock)->update([
                "bank_balance" => $banks[$new_bank_id_lock]->bank_balance - $new_total,
            ]);
        }

$rmv = AccountStatement::where('es_id',$check_bond->es_id)->delete();


         $supplier = Supplier::where('id',$supp_id)->first();
        abort_if(!$supplier, 404);

        // P3 storage ledger: one debit entry for the edited bond's NEW
        // storage movement -- runs unconditionally, every edit touches
        // Storage. Debit = amount + commission, matching exactly what was
        // just subtracted from storage balance in Step 2 above.
        StorageStatement::record([
            'storage_id' => $new_storage_id,
            'bond_id' => $id,
            'entry_type' => 'bond',
            'transaction_date' => $date,
            'description' => "سند دفع (معدل) رقم {$check_bond->es_id} من خزينة {$storage_info->name} لصالح $supplier->name",
            'reference' => $check_bond->es_id,
            'debit' => $new_total,
            'credit' => 0,
            'commission' => $new_commission,
            'created_by' => Auth::user()->id,
        ]);

        // P2 bank ledger: one debit entry for the edited bond's NEW bank
        // movement, only if the new state still touches a bank. Debit =
        // amount + commission, matching exactly what was just subtracted
        // from bank_balance in Step 2b above.
        if ($new_bank_id_lock !== null && isset($banks[$new_bank_id_lock])) {
            BankStatement::record([
                'bank_id' => $new_bank_id_lock,
                'bond_id' => $id,
                'entry_type' => 'bond',
                'transaction_date' => $date,
                'description' => "سند دفع (معدل) رقم {$check_bond->es_id} من بنك {$banks[$new_bank_id_lock]->bank_name} لصالح $supplier->name",
                'reference' => $check_bond->es_id,
                'debit' => $new_total,
                'credit' => 0,
                'commission' => $new_commission,
                'created_by' => Auth::user()->id,
            ]);
        }

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
        });

        }else{
            // P5: receipt-bond edit. Mirrors the payment-bond edit branch
            // above exactly (same canonical Bond -> Storage -> Bank lock
            // order, same reversal-then-reapply structure), swapped for
            // receipt semantics using save()'s receipt branch as the
            // source of truth: original storage/bank is on to_account (not
            // from_account), the movement direction is a credit (not a
            // debit), and receipt bonds never carry a commission.
            $storage_id = $request->storage_id2;
            $sub_id = $request->sub_id2;
            $supp_id = $request->supp_id2;
            $amount = $request->amount2;
            $money_way = $request->money_way2;
            $bank_id = $request->bank_id2;
            $collector_info = $request->collector_info2;
            $transaction_info = $request->transaction_info2;
            $date = $request->crt_date2;

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
                $path = $check_bond->file_path;
            }

            // Receipt bonds never carry a commission (unchanged from
            // save()'s receipt branch and delete()'s receipt reversal).
            $new_total = (float) $amount;

            // Canonical lock order (identical to the payment-edit branch
            // above): Bond, then Storage rows ascending by id, then Bank
            // rows ascending by id, deduplicated.
            DB::transaction(function () use ($request, $id, $path, $storage_id, $sub_id, $supp_id, $amount, $money_way, $bank_id, $collector_info, $transaction_info, $date, $new_total) {

                // Canonical lock order, step 1: the Bond row being edited.
                $check_bond = Bond::where('id', $id)->lockForUpdate()->first();
                abort_if(!$check_bond, 404);

                $orig_amount     = $check_bond->amount;
                $orig_money_way  = $check_bond->money_way;
                $orig_bank_id    = $check_bond->bank_id;
                // Receipt bonds store the storage on to_account (see
                // save()'s receipt branch and delete()'s receipt branch),
                // unlike payment bonds which use from_account.
                $orig_storage_id = (int) $check_bond->to_account;
                $orig_total      = (float) $orig_amount; // no commission

                $new_storage_id    = (int) $storage_id;
                $orig_bank_id_lock = ($orig_money_way == 2 && $orig_bank_id) ? (int) $orig_bank_id : null;
                $new_bank_id_lock  = ($request->money_way2 == 2 && $bank_id) ? (int) $bank_id : null;

                // Canonical lock order, step 2: Storage rows, ascending id, deduplicated.
                $storageIds = array_unique([$orig_storage_id, $new_storage_id]);
                sort($storageIds);
                $storages = [];
                foreach ($storageIds as $sid) {
                    $storages[$sid] = Storage::where('id', $sid)->lockForUpdate()->first();
                    abort_if(!$storages[$sid], 404);
                }

                // Canonical lock order, step 3: Bank rows, ascending id, deduplicated.
                $bankIds = array_values(array_unique(array_filter(
                    [$orig_bank_id_lock, $new_bank_id_lock],
                    fn ($v) => $v !== null
                )));
                sort($bankIds);
                $banks = [];
                foreach ($bankIds as $bid) {
                    $bankRow = Bank::where('id', $bid)->lockForUpdate()->first();
                    if ($bankRow) {
                        $banks[$bid] = $bankRow;
                    }
                }

                Bond::where('id', $id)->update([
                    "file_path" => $path,
                    "type" => 2,
                    "sub_id" => $sub_id,
                    "from_account" => $supp_id,
                    "from_type" => "supplier",
                    "to_account" => $storage_id,
                    "to_type" => "storage",
                    "amount" => $amount,
                    "commission" => 0,
                    "info" => $transaction_info,
                    "money_way" => $money_way,
                    "bank_id" => $bank_id,
                    "collector_info" => $collector_info,
                    "crt_date" => $request->crt_date2,
                ]);

                // Step 1: reverse the ORIGINAL Storage movement -- it was a
                // credit at creation, so reverse it with a debit.
                Storage::where('id',$orig_storage_id)->update([
                    "balance" => $storages[$orig_storage_id]->balance - $orig_total,
                ]);
                // Keep the in-memory copy in sync in case orig_storage_id === new_storage_id,
                // so Step 2 below (re)uses the post-reversal balance instead of a stale one.
                $storages[$orig_storage_id]->balance -= $orig_total;

                // P5 storage ledger: void the original storage entry and
                // append its exact reversal -- runs unconditionally, every
                // edit touches Storage.
                StorageStatement::reverseActiveEntryForBond(
                    $id,
                    "عكس السند الأصلي رقم {$check_bond->es_id} بسبب تعديل السند",
                    Auth::user()->id
                );

                // Step 1b: reverse the ORIGINAL Bank movement (credit -> debit), only if the original bond used money_way==2.
                if ($orig_bank_id_lock !== null && isset($banks[$orig_bank_id_lock])) {
                    Bank::where('id',$orig_bank_id_lock)->update([
                        "bank_balance" => $banks[$orig_bank_id_lock]->bank_balance - $orig_total,
                    ]);
                    $banks[$orig_bank_id_lock]->bank_balance -= $orig_total;

                    // P5 bank ledger: void the original bank entry and
                    // append its exact reversal -- the original row is
                    // never deleted or edited.
                    BankStatement::reverseActiveEntryForBond(
                        $id,
                        "عكس السند الأصلي رقم {$check_bond->es_id} بسبب تعديل السند",
                        Auth::user()->id
                    );
                }

                // Step 2: apply the NEW Storage movement (credit) on the NEW storage_id.
                $storage_info = $storages[$new_storage_id];
                Storage::where('id',$storage_id)->update([
                    "balance" => $storage_info->balance + $new_total,
                ]);

                // Step 2b: apply the NEW Bank movement (credit), only if the new money_way2==2.
                if ($new_bank_id_lock !== null && isset($banks[$new_bank_id_lock])) {
                    Bank::where('id',$new_bank_id_lock)->update([
                        "bank_balance" => $banks[$new_bank_id_lock]->bank_balance + $new_total,
                    ]);
                }

                AccountStatement::where('es_id',$check_bond->es_id)->delete();

                $supplier = Supplier::where('id',$supp_id)->first();
                abort_if(!$supplier, 404);

                // P5 storage ledger: one credit entry for the edited bond's
                // NEW storage movement -- runs unconditionally, every edit
                // touches Storage. Reuses the bond's original es_id, exactly
                // as the payment-edit branch does.
                StorageStatement::record([
                    'storage_id' => $new_storage_id,
                    'bond_id' => $id,
                    'entry_type' => 'bond',
                    'transaction_date' => $date,
                    'description' => "سند قبض (معدل) رقم {$check_bond->es_id} لصالح خزينة {$storage_info->name} من حساب $supplier->name",
                    'reference' => $check_bond->es_id,
                    'debit' => 0,
                    'credit' => $new_total,
                    'commission' => 0,
                    'created_by' => Auth::user()->id,
                ]);

                // P5 bank ledger: one credit entry for the edited bond's NEW
                // bank movement, only if the new state still touches a bank.
                if ($new_bank_id_lock !== null && isset($banks[$new_bank_id_lock])) {
                    BankStatement::record([
                        'bank_id' => $new_bank_id_lock,
                        'bond_id' => $id,
                        'entry_type' => 'bond',
                        'transaction_date' => $date,
                        'description' => "سند قبض (معدل) رقم {$check_bond->es_id} لصالح بنك {$banks[$new_bank_id_lock]->bank_name} من حساب $supplier->name",
                        'reference' => $check_bond->es_id,
                        'debit' => 0,
                        'credit' => $new_total,
                        'commission' => 0,
                        'created_by' => Auth::user()->id,
                    ]);
                }

                // Two AccountStatement rows, exact field set/invoice_type=10
                // mirror of save()'s receipt branch, reusing the bond's
                // original es_id (not a freshly generated one).
                $storage_log = AccountStatement::create([
                    "supp_client_id" => $storage_id,
                    "is_storage" => 1,
                    "trans_storage" => 1,
                    "invoice_type" => 10,
                    "sub_id" => $sub_id,
                    "es_id" => $check_bond->es_id,
                    "invoice_date" => $date,
                    "debit_balance" => $amount,
                    "credit_balance" => 0,
                    "ledger_net_effect" => $amount,
                    "transaction_txt" => "سند قبض من حساب $supplier->name لصالح خزينة {$storage_info->name}",
                    "transaction_type" => 2,
                    "added_by" => Auth::user()->id,
                    "crt_date" => date('Y-m-d'),
                ]);

                $storage_log2 = AccountStatement::create([
                    "supp_client_id" => $supp_id,
                    "trans_storage" => 1,
                    "invoice_type" => 10,
                    "sub_id" => $sub_id,
                    "es_id" => $check_bond->es_id,
                    "invoice_date" => $date,
                    "debit_balance" => 0,
                    "credit_balance" => $amount,
                    "ledger_net_effect" => -$amount,
                    "transaction_txt" => "سند قبض من حساب $supplier->name لصالح خزينة {$storage_info->name}",
                    "transaction_type" => 2,
                    "added_by" => Auth::user()->id,
                    "crt_date" => date('Y-m-d'),
                ]);
            });
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
