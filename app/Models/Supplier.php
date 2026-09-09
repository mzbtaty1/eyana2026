<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
"name",
"phone_1",
"phone_2",
"type",
"passport_id",
"passport_expiration_date", 
"email",
"address",
"debit_opening_balance",
"opening_credit_balance",
"status",
"acc_type",
"limit_balance",
"in_index",
"in_stat",
    ];

    
    
    
}
