<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountStatement extends Model
{
    use HasFactory;
    
    
    protected $fillable = [

"supp_client_id",
"trans_storage",
"is_storage",
"is_supp_account",
"sub_id",
"invoice_type",
"es_id",
"invoice_date",
"debit_balance",
"credit_balance",
"cumulative_balance",
"balance_on_transaction",
"transaction_txt",
"transaction_type",
"added_by",
"crt_date",
"transaction_approved",
        
    ];
    
}
