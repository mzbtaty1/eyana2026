<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An employee's commission period («فترة عمولة», Employees step E): the Step D commission for
 * period_from .. period_to as approved when the period was opened (commission_amount and the
 * calculation snapshot), and its payments (CommissionPayout, several = partial). Closed only
 * once fully paid; a payout reversal reopens it.
 */
class CommissionPeriod extends Model
{
    const OPEN = 'open';
    const CLOSED = 'closed';

    protected $fillable = [
        'employee_id',
        'period_from',
        'period_to',
        'commission_amount',
        'calculation',
        'status',
        'closed_at',
        'closed_by',
        'created_by',
    ];

    protected $casts = [
        'period_from' => 'date:Y-m-d',
        'period_to' => 'date:Y-m-d',
        'commission_amount' => 'decimal:2',
        'calculation' => 'array',
        'closed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function payouts()
    {
        return $this->hasMany(CommissionPayout::class, 'period_id')->orderBy('id');
    }

    /** Payments that count (not reversed). */
    public function activePayouts()
    {
        return $this->hasMany(CommissionPayout::class, 'period_id')->whereNull('reversed_at');
    }

    public function isClosed(): bool
    {
        return $this->status === self::CLOSED;
    }

    /** Paid so far (active payouts); uses a loaded active_payouts_sum_amount when present. */
    public function paid(): float
    {
        $sum = $this->getAttribute('active_payouts_sum_amount') ?? $this->activePayouts()->sum('amount');
        return round((float) $sum, 2);
    }

    /** Approved commission - paid. */
    public function remaining(): float
    {
        return round((float) $this->commission_amount - $this->paid(), 2);
    }

    public function label(): string
    {
        return $this->period_from->format('Y-m-d') . ' : ' . $this->period_to->format('Y-m-d');
    }
}
