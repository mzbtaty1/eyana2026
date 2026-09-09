<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketUser extends Model
{ 
    use HasFactory;
    
            protected $fillable = [
"ticket_system_id",
"client_name",
"client_type", 
"client_net_pice",
"client_bought_price",
"client_booking_id",
"client_ticket_id",
"client_passport_id",
"client_phone",
"crt_at",
    ];

    
}
