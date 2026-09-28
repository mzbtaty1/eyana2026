<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A reusable commission tier table («جدول شرائح العمولات», Employees step B), managed by
 * the admin. Its tiers are profit ranges with a commission rate; tiered employees are
 * assigned one (step C), and a later step applies it to an employee's total profit.
 * status: 1 = active (offered when assigning), 0 = inactive (kept, never deleted while used).
 */
class CommissionTierTable extends Model
{
    protected $fillable = [
        'name',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    public function tiers()
    {
        return $this->hasMany(CommissionTier::class, 'table_id')->orderBy('sort_order')->orderBy('id');
    }

    public function isActive(): bool
    {
        return (int) $this->status === 1;
    }

    /**
     * The ONE tier for a total profit (Employees step D), by the step B boundary rule:
     * previous upper limit < profit <= upper limit, the first tier from its own lower limit
     * (1-10,000 / 10,001-50,000: 10,000 -> first, 10,000.50 and 10,001 -> second). Above the
     * last upper limit: the last tier. Profit <= 0 or below the first lower limit: none.
     * Compared in cents, as the tiers were validated.
     */
    public function tierFor(float $profit): ?CommissionTier
    {
        $tiers = $this->tiers;
        $cents = (int) round($profit * 100);
        if ($cents <= 0 || $tiers->isEmpty() || $cents < (int) round((float) $tiers->first()->from_amount * 100)) {
            return null;
        }
        foreach ($tiers as $tier) {
            if ($tier->to_amount === null || $cents <= (int) round((float) $tier->to_amount * 100)) {
                return $tier;
            }
        }
        return $tiers->last();
    }

    /** Employees assigned this table (users.commission_tier_table_id, Employees step C). */
    public function employees()
    {
        return $this->hasMany(User::class, 'commission_tier_table_id');
    }

    /**
     * Employees using this table, counted from users.commission_tier_table_id: a used table
     * is never deleted -- only deactivated (the foreign key restricts the delete as well).
     */
    public function usageCount(): int
    {
        return $this->employees()->count();
    }
}
