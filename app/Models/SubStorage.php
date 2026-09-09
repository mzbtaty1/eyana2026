<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubStorage extends Model
{
    use HasFactory;
    
     protected $fillable = [
"main_storage",
"name",
"type",
"bank_id",
"bank_number",
"balance",
      ];
}
