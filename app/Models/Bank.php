<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    use HasFactory;
     protected $fillable = [
         "bank_name",
         "bank_balance",
     ];

    public function statements()
    {
        return $this->hasMany(BankStatement::class);
    }
}
