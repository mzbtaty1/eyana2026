<?php

namespace App\Exports;
use App\Models\AccountStatement;
use App\Models\Supplier;
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
        
        
        if(isset($this->date_from) && isset($this->date_to)){
            $AccountStatements = $AccountStatements->whereBetween('crt_date' , [$this->date_from , $this->date_to]);
        }
        
        if(isset($this->transaction_type)){
            $AccountStatements = $AccountStatements->where('transaction_type' , $this->transaction_type);
        }
         
        
        $AccountStatements = $AccountStatements->get();
        
        
        
        $total_debit_balance = 0;
        foreach ($AccountStatements as $AccountStatement) {
            $total_debit_balance += $AccountStatement->debit_balance;
        }
        $total_credit_balance = 0;
        foreach ($AccountStatements as $AccountStatement) {
            $total_credit_balance += $AccountStatement->credit_balance;
        }
        
     return view('excel.accountat_excel', [
            'AccountStatements' => $AccountStatements,
         "st" => $st,
                     "total_debit_balance" => $total_debit_balance,
            "total_credit_balance" => $total_credit_balance,
            "supplier" => $supplier,
         
            "date_from" => $this->date_from,
            "date_to" => $this->date_to,
        ]);
 }
}
