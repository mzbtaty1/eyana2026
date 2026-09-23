<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use Redirect;
use App\Models\{Supplier, Invoice, TicketUser, TicketVendor, Airline, AccountStatement, Log , Bond, Bank, Collector, SubStorage, User};
use App\Exports\AccountatExport;
//use App\Imports\ImportProduct;
use DB;
use Maatwebsite\Excel\Facades\Excel;

class AccountsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function accounts_statement()
    {
        $suppliers = Supplier::select('*')
            ->where('status', 1)
            ->orderBy('id', 'DESC')
            ->get();
        return view('accounts_statement.all', ["suppliers" => $suppliers]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Net balance (debit - credit) carried forward from every accounting
     * transaction on this account/report dated before $date_from. Opening
     * balances are themselves booked as ordinary dated AccountStatement rows
     * (es_id "FLY-OPEN-BALANCE"), so this is a plain historical sum -- no
     * separate addition of Supplier::opening_credit_balance/debit_opening_balance,
     * which would double-count that same booked row.
     */
    private function accounts_statement_opening_balance($search_status, $invoice_beneficiaries, $date_from, $transaction_type = null)
    {
        $q = AccountStatement::where('is_storage', '!=', 1)->where('crt_date', '<', $date_from);

        if ($search_status == 0) {
            $q = $q->where('is_supp_account', '!=', 1);
        } else {
            $q = $q->where('supp_client_id', $invoice_beneficiaries);
        }

        if (isset($transaction_type)) {
            $q = $q->where('transaction_type', $transaction_type);
        }

        $totals = $q->selectRaw('SUM(debit_balance) as d, SUM(credit_balance) as c')->first();

        return ($totals->d ?? 0) - ($totals->c ?? 0);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function accounts_statement_search(Request $request)
    {
        $search_status = (int) $request->invoice_beneficiaries;

//        dd($request);


        $AccountStatements = AccountStatement::select('*')->where('is_storage', '!=' , 1);


        
        if($search_status == 0){
            $supplier = [];
            $st = 0;
            
           $AccountStatements = $AccountStatements->where('is_supp_account' , '!=',1);
            
        }else{
            $AccountStatements = $AccountStatements->where('supp_client_id' , $request->invoice_beneficiaries);
             
            $supplier = Supplier::select('*')->where('id' , $search_status)->get();
            abort_if(count($supplier) == 0 , 404);
            
            $supplier = $supplier[0];
            
            $st = 1;
            
            
        }
        
//       dd($st , $AccountStatements->get());
        
        
        $isDateFiltered = isset($request->date_from) && isset($request->date_to);

        if($isDateFiltered){
            $AccountStatements = $AccountStatements->whereBetween('crt_date' , [$request->date_from , $request->date_to]);
        }

        if(isset($request->transaction_type)){
            $AccountStatements = $AccountStatements->where('transaction_type' , $request->transaction_type);
        }


        // Chronological ledger order: the running balance below is recalculated
        // fresh on every request from this order, so a transaction added later
        // but dated earlier (a backdated invoice) must still land in its real
        // date position -- not wherever it happened to be inserted. crt_date is
        // the same field the date filter and opening-balance calc use, so this
        // is "the" accounting date throughout. id is a deterministic tiebreaker
        // for same-day rows, using the existing auto-increment primary key.
        $AccountStatements = $AccountStatements->orderBy('crt_date', 'asc')->orderBy('id', 'asc')->get();

        // Carried-forward balance: when a date range is selected, the period's
        // running/closing balance must continue from the real historical balance
        // as of the day before date_from, not restart at zero. Zero when there is
        // no date filter, so the full (unfiltered) statement is unaffected.
        $opening_balance_for_period = $isDateFiltered
            ? $this->accounts_statement_opening_balance($search_status, $request->invoice_beneficiaries, $request->date_from, $request->transaction_type)
            : 0;


        $total_debit_balance = 0;
        foreach ($AccountStatements as $AccountStatement) {
            $total_debit_balance += $AccountStatement->debit_balance;
        }
        $total_credit_balance = 0;
        foreach ($AccountStatements as $AccountStatement) {
            $total_credit_balance += $AccountStatement->credit_balance;
        }

        // Batched replacement for what used to be up to 7 per-row lookup
        // queries (Bond-or-Invoice by es_id, TicketUser, Bank-or-Collector,
        // SubStorage, User) executed inline in the Blade view, once per
        // statement row. Same conditions the view used to evaluate per row.
        $esIds = $AccountStatements->pluck('es_id')->filter()->unique();
        $bondsByEsId = Bond::whereIn('es_id', $esIds)->get()->keyBy('es_id');
        $invoicesByEsId = Invoice::whereIn('es_id', $esIds)->get()->keyBy('es_id');

        $ticketSystemIds = $invoicesByEsId->pluck('ticket_system_id')->filter()->unique();
        $usersByTicketSystemId = TicketUser::whereIn('ticket_system_id', $ticketSystemIds)->get()->groupBy('ticket_system_id');

        $bankIds = $bondsByEsId->where('money_way', 2)->pluck('bank_id')->filter()->unique();
        $banksById = Bank::whereIn('id', $bankIds)->get()->keyBy('id');

        $collectorIds = $bondsByEsId->filter(fn ($b) => $b->money_way != 1 && $b->money_way != 2)
            ->pluck('collector_info')->filter()->unique();
        $collectorsById = Collector::whereIn('id', $collectorIds)->get()->keyBy('id');

        $subIds = $AccountStatements->filter(fn ($a) => $a->trans_storage == 1 && (int) $a->sub_id !== 0)
            ->pluck('sub_id')->filter()->unique();
        $subStoragesById = SubStorage::whereIn('id', $subIds)->get()->keyBy('id');

        $addedByIds = $AccountStatements->pluck('added_by')->filter()->unique();
        $usersById = User::whereIn('id', $addedByIds)->get()->keyBy('id');

        return view('accounts_statement.show', [
            "AccountStatements" => $AccountStatements,
            "supplier" => $supplier,
            "total_debit_balance" => $total_debit_balance,
            "total_credit_balance" => $total_credit_balance,
            "opening_balance_for_period" => $opening_balance_for_period,
            "st" => $st,
            "bondsByEsId" => $bondsByEsId,
            "invoicesByEsId" => $invoicesByEsId,
            "usersByTicketSystemId" => $usersByTicketSystemId,
            "banksById" => $banksById,
            "collectorsById" => $collectorsById,
            "subStoragesById" => $subStoragesById,
            "usersById" => $usersById,


"invoice_beneficiaries" => $request->invoice_beneficiaries,
"date_from" => $request->date_from,
"date_to" => $request->date_to,
"transaction_type" => $request->transaction_type,

        ]);


    }
    
    public function accounts_statement_print_all($invoice_beneficiaries , $date_from = null , $date_to = null , $transaction_type = null){
        
        
        
        
        
                $search_status = (int) $invoice_beneficiaries;

//        dd($request);
       

        $AccountStatements = AccountStatement::select('*')->where('is_storage', '!=' , 1);

        
        
        if($search_status == 0){
            $supplier = [];
            $st = 0;
            
           $AccountStatements = $AccountStatements->where('is_supp_account' , '!=',1);
            
        }else{
            $AccountStatements = $AccountStatements->where('supp_client_id' , $invoice_beneficiaries);
             
            $supplier = Supplier::select('*')->where('id' , $search_status)->get();
            abort_if(count($supplier) == 0 , 404);
            
            $supplier = $supplier[0];
            
            $st = 1;
            
            
        }
 
//       dd($st , $AccountStatements->get());
        
        
        $isDateFiltered = isset($date_from) && isset($date_to);

        if($isDateFiltered){
            $AccountStatements = $AccountStatements->whereBetween('crt_date' , [$date_from , $date_to]);
        }

        if(isset($transaction_type)){
            $AccountStatements = $AccountStatements->where('transaction_type' , $transaction_type);
        }


        // See accounts_statement_search() for why this must be a real chronological
        // sort (by crt_date, then id) rather than default/insertion order.
        $AccountStatements = $AccountStatements->orderBy('crt_date', 'asc')->orderBy('id', 'asc')->get();

        // See accounts_statement_search() for why this must be date-based, not
        // Supplier::opening_credit_balance/debit_opening_balance (would double-count).
        $opening_balance_for_period = $isDateFiltered
            ? $this->accounts_statement_opening_balance($search_status, $invoice_beneficiaries, $date_from, $transaction_type)
            : 0;


        $total_debit_balance = 0;
        foreach ($AccountStatements as $AccountStatement) {
            $total_debit_balance += $AccountStatement->debit_balance;
        }
        $total_credit_balance = 0;
        foreach ($AccountStatements as $AccountStatement) {
            $total_credit_balance += $AccountStatement->credit_balance;
        }
        
        
//        dd($AccountStatements);
//               dd($st);
        
        return view('accounts_statement.print_report', [
            "AccountStatements" => $AccountStatements,
            "supplier" => $supplier,
            "total_debit_balance" => $total_debit_balance,
            "total_credit_balance" => $total_credit_balance,
            "opening_balance_for_period" => $opening_balance_for_period,
            "st" => $st,
            "date_from" => $date_from,
            "date_to" => $date_to,
            ]);

    }
    public function accounts_statement_print_excel($invoice_beneficiaries , $date_from = null , $date_to = null , $transaction_type = null){
//        dd($invoice_beneficiaries , $date_from ,  $date_to, $transaction_type);
         
     $name = date('Y-m-d') . "_" . rand() . ".xlsx";    
        
return Excel::download(new AccountatExport($invoice_beneficiaries , $date_from , $date_to , $transaction_type), $name);     

        
         
    }

    /**
     * Display the specified resource.
     */
    public function accounts_statement_get($id)
    {
        $id = (int) $id;
        
    }
    public function accounts_statement_print($id)
    {
        $id = (int) $id;
        $supplier = Supplier::select('*')
            ->where('id', $id)
            ->get();
        abort_if(count($supplier) == 0, 404);

        $supplier = $supplier[0];

        $AccountStatements = AccountStatement::select('*')
            ->where('supp_client_id', $id)
            ->where('is_storage', '!=' , 1)
            ->get();

        $total_debit_balance = 0;
        foreach ($AccountStatements as $AccountStatement) {
            $total_debit_balance += $AccountStatement->debit_balance;
        }
        $total_credit_balance = 0;
        foreach ($AccountStatements as $AccountStatement) {
            $total_credit_balance += $AccountStatement->credit_balance;
        }
        return view('accounts_statement.print', [
            "AccountStatements" => $AccountStatements,
            "supplier" => $supplier,
            "total_debit_balance" => $total_debit_balance,
            "total_credit_balance" => $total_credit_balance,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function accounts_statement_custom_get()
    {
         return view('accounts_statement.custom.get');
    }
public function accounts_statement_suppliers_get()
    {
         return view('accounts_statement.suppliers.get');
    }

    /**
     * Update the specified resource in storage.
     */
    public function accounts_statement_custom_view(Request $request)
    {
        
        
        $report_type = $request->report_type;
        if($report_type == 0){
            $suppliers = Supplier::select('*')
            ->where('status', 1)
                ->where('in_stat', 1)
                ->where('acc_type' , '!=',3)
            ->orderBy('id', 'DESC')
            ->get();
        }elseif($report_type == 1){
              $suppliers = Supplier::select('*')
            ->where('status', 1)
                  ->where('in_stat', 1)
            ->where('acc_type' , 2)
            ->orderBy('id', 'DESC')
            ->get();
            
        }else{
            $suppliers = Supplier::select('*')
            ->where('status', 1)
                ->where('in_stat', 1)
            ->where('acc_type' , 1)
            ->orderBy('id', 'DESC')
            ->get();  
        }
        
        if(isset($request->check_get_zero)){
            $zero = 1;
        }else{
            $zero = 0;
        }
        
        
        if(isset($request->date_from) && isset($request->date_to)){
            $sts = 0;
        }else{
            
        
            if($request->date_from == NULL OR $request->date_to == NULL){
                $sts = 0;
            }else{
                
                $sts = 1;
            }
            
        }
//        dd($sts);
        return view('accounts_statement.custom.all' , [
            "report_type" => $report_type,
            "suppliers" => $suppliers,
            "sts" => $sts,
            "date_from" => $request->date_from,
            "date_to" => $request->date_to,
            "zero" => $zero,
        ]);
    }
    public function accounts_statement_suppliers_view(Request $request)
    {
        
        
        $report_type = $request->report_type;
        if($report_type == 0){
            $suppliers = Supplier::select('*')
            ->where('status', 1)
                ->where('in_stat', 0)
                ->where('acc_type' , '!=',3)
            ->orderBy('id', 'DESC')
            ->get();
        }elseif($report_type == 1){
              $suppliers = Supplier::select('*')
            ->where('status', 1)
                ->where('in_stat', 0)
            ->where('acc_type' , 2)
            ->orderBy('id', 'DESC')
            ->get();
            
        }else{
            $suppliers = Supplier::select('*')
            ->where('status', 1)
                ->where('in_stat', 0)
            ->where('acc_type' , 1)
            ->orderBy('id', 'DESC')
            ->get();  
        }
        
        if(isset($request->check_get_zero)){
            $zero = 1;
        }else{
            $zero = 0;
        }
        
        
        if(isset($request->date_from) && isset($request->date_to)){
            $sts = 0;
        }else{
            
        
            if($request->date_from == NULL OR $request->date_to == NULL){
                $sts = 0;
            }else{
                
                $sts = 1;
            }
            
        }
//        dd($sts);
        return view('accounts_statement.suppliers.all' , [
            "report_type" => $report_type,
            "suppliers" => $suppliers,
            "sts" => $sts,
            "date_from" => $request->date_from,
            "date_to" => $request->date_to,
            "zero" => $zero,
        ]);
    }
    
    public function accounts_statement_custom_print($sup_stauts , $from = null , $to = null){
        
        
           $report_type = $sup_stauts;
        if($report_type == 0){
            $suppliers = Supplier::select('*')
            ->where('status', 1)
            ->orderBy('id', 'DESC')
                ->where('in_stat', 1)
                ->where('acc_type' , '!=',3)
            ->get();
        }elseif($report_type == 1){
              $suppliers = Supplier::select('*')
            ->where('status', 1)
            ->where('acc_type' , 2)
                ->where('in_stat', 1)
            ->orderBy('id', 'DESC')
            ->get();
            
        }else{
            $suppliers = Supplier::select('*')
            ->where('status', 1)
                ->where('in_stat', 1)
            ->where('acc_type' , 1)
            ->orderBy('id', 'DESC')
            ->get();  
        }
        
       if($from == NULL OR $to == NULL){
                $sts = 0;
            }else{
                
                $sts = 1;
            }
        return view('accounts_statement.custom.print' , [
            "report_type" => $report_type,
            "suppliers" => $suppliers,
            "sts" => $sts,
            "date_from" => $from,
            "date_to" => $to,
        ]);
        
    }
    public function accounts_statement_suppliers_print($sup_stauts , $from = null , $to = null){
        
        
           $report_type = $sup_stauts;
        if($report_type == 0){
            $suppliers = Supplier::select('*')
            ->where('status', 1)
            ->orderBy('id', 'DESC')
                ->where('in_stat', 0)
                ->where('acc_type' , '!=',3)
            ->get();
        }elseif($report_type == 1){
              $suppliers = Supplier::select('*')
            ->where('status', 1)
            ->where('acc_type' , 2)
                ->where('in_stat', 0)
            ->orderBy('id', 'DESC')
            ->get();
            
        }else{
            $suppliers = Supplier::select('*')
            ->where('status', 1)
                ->where('in_stat', 0)
            ->where('acc_type' , 1)
            ->orderBy('id', 'DESC')
            ->get();  
        }
        
       if($from == NULL OR $to == NULL){
                $sts = 0;
            }else{
                
                $sts = 1;
            }
        return view('accounts_statement.suppliers.print' , [
            "report_type" => $report_type,
            "suppliers" => $suppliers,
            "sts" => $sts,
            "date_from" => $from,
            "date_to" => $to,
        ]);
        
    }

    /**
     * Remove the specified resource from storage.
     */
    public function accounts_statement_approve($id)
    {
        $check = AccountStatement::select('*')->where('id' , $id)->get();
        if(count($check) == 0){
            $st_code = 404;
        }else{
            $st_code = 200;
            $update = AccountStatement::select('*')->where('id' , $id)->update([
                "transaction_approved" => 1,
            ]);
        }
        
        
             return response()->json([
    'status_code' => $st_code,
   
]);
        
    }
    
    function accounts_statement_trans_all(){
//        $bonds = Bond::select('*')->get();
//        $invoices = Invoice::select('*')->get();
        return view('accounts_statement.transactions.get');
        
        
    }
    public function accounts_statement_trans_get_all(Request $request){
        
//        $bonds = Bond::select('*');
//        $invoices = Invoice::select('*');
//        
//        
//        if(isset($request->date_from) && isset($request->date_to)){
//            $bonds = $bonds->whereBetween('crt_date' , [$request->date_from , $request->date_to]);
//            $invoices = $invoices->whereBetween('invoice_date' , [$request->date_from , $request->date_to]);
//        }
//        
//        $bonds = $bonds->get();
//        $invoices = $invoices->get();
        
        $expenses = Supplier::select('*')->where('acc_type' , 3)->orderBy('id','DESC')->get();
        
        return view('accounts_statement.transactions.get_all' , [
            "expenses" => $expenses ,
            "date_from" => $request->date_from ,
            "date_to" => $request->date_to ,
        ]);
        
        
    }
    
}
