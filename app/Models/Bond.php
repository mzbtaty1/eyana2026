<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bond extends Model
{
    use HasFactory;
    
    
          protected $fillable = [
"type",
"es_id",
"system_id", // rand
"sub_id",
              
"from_account",
"from_type",
              
"to_account",
"to_type",
              
"amount",
"info",
              
              
"money_way",
"bank_id",
"commission",
"collector_info",
"crt_date",
"created_by",
"file_path",
           "bond_status",   
              
              "is_invoice",
              "invoice_id",
              
    ];

    
    protected $casts = [
        "commission" => "decimal:2",
    ];

}
