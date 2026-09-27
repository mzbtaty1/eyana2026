<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One tier of a commission tier table: profit from_amount .. to_amount (NULL = no upper
 * limit) at rate %. The tiers of a table form one continuous ordered range (see
 * App\Http\Requests\CommissionTierTableRequest).
 */
class CommissionTier extends Model
{
    protected $fillable = [
        'table_id',
        'from_amount',
        'to_amount',
        'rate',
        'sort_order',
    ];

    protected $casts = [
        'from_amount' => 'decimal:2',
        'to_amount' => 'decimal:2',
        'rate' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function table()
    {
        return $this->belongsTo(CommissionTierTable::class, 'table_id');
    }
}
