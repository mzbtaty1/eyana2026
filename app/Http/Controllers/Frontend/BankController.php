<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Auth;
use Redirect;
use App\Http\Requests\StoreBankRequest;
use App\Http\Requests\UpdateBankRequest;
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

        // Batched replacement for a per-row Bond::where(...)->get() query that
        // previously ran once per bank on every page load.
        $bondTotals = Bond::where('bond_status', 0)
            ->where('money_way', 2)
            ->select('bank_id', DB::raw('SUM(amount) as total_amount'))
            ->groupBy('bank_id')
            ->pluck('total_amount', 'bank_id');

        return view('moneyarea.banks.all' , [
            "banks" => $banks,
            "bondTotals" => $bondTotals,
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
    public function save(StoreBankRequest $request)
    {
        // P4 ledger bypass fix: a newly created bank previously started
        // with zero ledger history, so accounting:reconcile would list it
        // as "pending cutover" forever. Now writes an 'opening' entry for
        // the initial balance immediately, matching the same cutover
        // convention used for pre-existing banks.
        DB::transaction(function () use ($request) {
            $create_bank = Bank::create([
                "bank_name" => $request->bank_name,
                "bank_balance" => $request->bank_balance,
            ]);

            $balance = (float) $request->bank_balance;
            BankStatement::record([
                'bank_id' => $create_bank->id,
                'bond_id' => null,
                'entry_type' => 'opening',
                'transaction_date' => date('Y-m-d'),
                'description' => "الرصيد الافتتاحي لبنك $request->bank_name",
                'reference' => null,
                'debit' => $balance < 0 ? abs($balance) : 0,
                'credit' => $balance >= 0 ? $balance : 0,
                'commission' => 0,
                'created_by' => Auth::user()->id,
            ]);

            $save_log = Log::create([
                "log_txt" => "تم اضافة بنك $request->bank_name",
                "log_ip" => $request->ip(),
                "log_by" => Auth::user()->id,
                "log_date" => date('Y-m-d'),
            ]);
        });

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
    public function update(UpdateBankRequest $request)
    {
        $id = (int) $request->id;

        // P4 ledger bypass fix: this form previously overwrote bank_balance
        // directly with zero locking/transaction and no ledger entry --
        // exactly the class of bug already fixed for
        // StoragesController::update(). Now locks the row, and logs a
        // manual 'adjustment' entry whenever the edit actually changes the
        // balance, so a direct admin edit can no longer silently desync
        // bank_statements from banks.bank_balance.
        DB::transaction(function () use ($request, $id) {
            $bank_info = Bank::where('id',$id)->lockForUpdate()->first();
            abort_if(!$bank_info, 404);

            $oldBalance = (float) $bank_info->bank_balance;
            $newBalance = (float) $request->bank_balance;

            $update_bank = Bank::where('id',$id)->update([
                "bank_name" => $request->bank_name,
                "bank_balance" => $request->bank_balance,
            ]);

            $delta = round($newBalance - $oldBalance, 2);
            if ($delta != 0) {
                BankStatement::record([
                    'bank_id' => $id,
                    'bond_id' => null,
                    'entry_type' => 'adjustment',
                    'transaction_date' => date('Y-m-d'),
                    'description' => "تعديل يدوي لرصيد بنك {$bank_info->bank_name}",
                    'reference' => null,
                    'debit' => $delta < 0 ? abs($delta) : 0,
                    'credit' => $delta > 0 ? $delta : 0,
                    'commission' => 0,
                    'created_by' => Auth::user()->id,
                ]);
            }

            $save_log = Log::create([
                "log_txt" => "تم تعديل اسم بنك $bank_info->bank_name",
                "log_ip" => $request->ip(),
                "log_by" => Auth::user()->id,
                "log_date" => date('Y-m-d'),
            ]);
        });

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
    
    public function bank_account_transactions(Request $request, $id)
    {
        $id = (int) $id;
        $bank_info = Bank::where('id', $id)->first();
        abort_if(!$bank_info, 404);

        $date_from = $request->date_from;
        $date_to   = $request->date_to;

        // Opening balance for the requested range = the running_balance of
        // the latest ledger entry strictly before date_from. With no
        // date_from (or no entries at all yet -- e.g. before this bank's
        // opening-balance cutover has been recorded), this is 0.
        $opening_balance = 0.0;
        if ($date_from) {
            $priorEntry = BankStatement::where('bank_id', $id)
                ->where('transaction_date', '<', $date_from)
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->first();
            $opening_balance = $priorEntry ? (float) $priorEntry->running_balance : 0.0;
        }

        $entries = BankStatement::where('bank_id', $id)
            ->when($date_from, fn ($q) => $q->where('transaction_date', '>=', $date_from))
            ->when($date_to, fn ($q) => $q->where('transaction_date', '<=', $date_to))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $total_debit  = (float) $entries->sum('debit');
        $total_credit = (float) $entries->sum('credit');
        $closing_balance = $entries->isNotEmpty()
            ? (float) $entries->last()->running_balance
            : $opening_balance;

        return view('moneyarea.banks.bnk_transactions', [
            "bank_info" => $bank_info,
            "entries" => $entries,
            "opening_balance" => $opening_balance,
            "total_debit" => $total_debit,
            "total_credit" => $total_credit,
            "closing_balance" => $closing_balance,
            "date_from" => $date_from,
            "date_to" => $date_to,
        ]);
    }
}
