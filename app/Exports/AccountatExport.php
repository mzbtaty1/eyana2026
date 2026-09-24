<?php

namespace App\Exports;
use App\Models\{AccountStatement, Supplier, Bond, Invoice, TicketUser, Bank, Collector, SubStorage, User};
//use Maatwebsite\Excel\Concerns\FromCollection;


use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class AccountatExport implements FromView
{
    /**
    * @return \Illuminate\Support\Collection
    */
    
    protected $invoice_beneficiaries;
    protected $date_from;
    protected $date_to;
    protected $transaction_type;

 function __construct($invoice_beneficiaries , $date_from , $date_to , $transaction_type) {
        $this->invoice_beneficiaries = $invoice_beneficiaries;
        $this->date_from = $date_from;
        $this->date_to = $date_to;
        $this->transaction_type = $transaction_type;
 }
    
    
    public function view(): View
    {
        
                $search_status = (int) $this->invoice_beneficiaries;

//        dd($request);
       

        $AccountStatements = AccountStatement::select('*')->where('is_storage', '!=' , 1);

//        $st = 0;
        
        if($search_status == 0){
            $supplier = [];
            $st = 0;
            
           $AccountStatements = $AccountStatements->where('is_supp_account' , '!=',1);
            
        }else{
            $AccountStatements = $AccountStatements->where('supp_client_id' , $search_status);
             
            $supplier = Supplier::select('*')->where('id' , $search_status)->get();
            abort_if(count($supplier) == 0 , 404);
            
            $supplier = $supplier[0];
            
            $st = 1;
            
            
        }
        
//       dd($st , $AccountStatements->get());
        
        
        $isDateFiltered = isset($this->date_from) && isset($this->date_to);

        if($isDateFiltered){
            $AccountStatements = $AccountStatements->whereBetween('crt_date' , [$this->date_from , $this->date_to]);
        }
        
        if(isset($this->transaction_type)){
            $AccountStatements = $AccountStatements->where('transaction_type' , $this->transaction_type);
        }
         
        
        // Same chronological ledger order as the Account Statement screen and
        // Print Preview (see AccountsController::accounts_statement_search()).
        $AccountStatements = $AccountStatements->orderBy('crt_date', 'asc')->orderBy('id', 'asc')->get();

        // Same carried-forward opening balance as the screen and Print Preview.
        $opening_balance_for_period = $isDateFiltered
            ? AccountStatement::openingBalanceBefore($search_status, $this->invoice_beneficiaries, $this->date_from, $this->transaction_type)
            : 0;
        
        
        
        $total_debit_balance = 0;
        foreach ($AccountStatements as $AccountStatement) {
            $total_debit_balance += $AccountStatement->debit_balance;
        }
        $total_credit_balance = 0;
        foreach ($AccountStatements as $AccountStatement) {
            $total_credit_balance += $AccountStatement->credit_balance;
        }

        // Batched lookups replacing the per-row queries the Excel view used to
        // run (same approach as accounts_statement_search()).
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
        
     return view('excel.accountat_excel', [
            'AccountStatements' => $AccountStatements,
         "st" => $st,
                     "total_debit_balance" => $total_debit_balance,
            "total_credit_balance" => $total_credit_balance,
            "opening_balance_for_period" => $opening_balance_for_period,
            "supplier" => $supplier,
            "bondsByEsId" => $bondsByEsId,
            "invoicesByEsId" => $invoicesByEsId,
            "usersByTicketSystemId" => $usersByTicketSystemId,
            "banksById" => $banksById,
            "collectorsById" => $collectorsById,
            "subStoragesById" => $subStoragesById,
            "usersById" => $usersById,
         
            "date_from" => $this->date_from,
            "date_to" => $this->date_to,
        ]);
 }
}
