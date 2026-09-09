<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visa extends Model
{
    use HasFactory;
    protected $fillable = [
"visa_name",
"visa_price",
"visa_ext_price",
    ];

    
}
