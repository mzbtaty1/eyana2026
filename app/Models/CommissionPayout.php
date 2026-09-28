<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One commission payment of a period (Employees step E), made through its own payment voucher
 * (bond_id, App\Services\PaymentBond). Never deleted: a reversal offsets the voucher's entries
 * and marks the payout reversed (reversed_at / reversed_by).
 */
class CommissionPayout extends Model
{
    protected $fillable = [
        'period_id',
        'amount',
        'paid_date',
        'bond_id',
        'storage_id',
        'money_way',
        'bank_id',
        'reference',
        'request_token',
        'created_by',
        'reversed_at',
        'reversed_by',
        'reversal_bond_note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_date' => 'date:Y-m-d',
        'money_way' => 'integer',
        'reversed_at' => 'datetime',
    ];

    public function period()
    {
        return $this->belongsTo(CommissionPeriod::class, 'period_id');
    }

    public function bond()
    {
        return $this->belongsTo(Bond::class, 'bond_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }
}
