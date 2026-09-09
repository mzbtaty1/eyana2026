<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketVendor extends Model
{
    use HasFactory;
    
        protected $fillable = [
"ticket_system_id",
"vendor_id",
"price",
    ];

    public function supplier()
{
    return $this->belongsTo(Supplier::class, 'vendor_id');
}


    
}
