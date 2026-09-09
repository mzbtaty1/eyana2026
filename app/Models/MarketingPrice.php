<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingPrice extends Model
{
    use HasFactory;
    
         protected $fillable = [
"title",
"travel_date",
"time_departure",
"total_price",
"cost_price",
"booking_id",
"screen_id",
"added_by",
"places_available",
"hold_finish_time",
    ];
     
}
