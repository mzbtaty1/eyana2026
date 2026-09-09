<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;
    
     /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        
"es_id",
"ticket_system_id",
"invoice_date",
"invoice_travel_date",
"return_date",
"invoice_airline",
"from_location",
"to_location",
"invoice_group_id",
"invoice_beneficiaries",
"invoice_section",
"invoice_comments",
"invoice_currency",
"invoice_draft",
"invoice_create_by",
"invoice_approved_by",
"invoice_ticket_file", 
        
"invoice_shared", 
"invoice_account_1", 
"invoice_account_1_comm", 
"invoice_account_2", 
"invoice_account_2_comm", 
        "crt_at",

    ];




//     public function ticketVendors()
// {
//     return $this->hasMany(TicketVendor::class, 'ticket_system_id', 'ticket_system_id');
// }

public function beneficiaries()
{
    return $this->belongsTo(Supplier::class, 'invoice_beneficiaries');
}

public function users()
{
    return $this->hasMany(TicketUser::class, 'ticket_system_id', 'ticket_system_id');
}

public function mostarad()
{
    return $this->hasOne(AccountStatement::class, 'es_id', 'es_id')->where('credit_balance', 0);
}

public function mortaga()
{
    return $this->hasOne(AccountStatement::class, 'es_id', 'es_id')->where('debit_balance', 0);
}

public function creator()
{
    return $this->belongsTo(User::class, 'invoice_create_by');
}

public function accountStatements()
{
    return $this->hasMany(AccountStatement::class, 'es_id', 'es_id');
}
public function ticketVendors()
{
    return $this->hasMany(TicketVendor::class, 'ticket_system_id', 'ticket_system_id');
}

    
}
