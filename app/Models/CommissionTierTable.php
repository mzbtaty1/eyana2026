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
