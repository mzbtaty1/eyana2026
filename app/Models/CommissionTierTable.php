<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A reusable commission tier table («جدول شرائح العمولات», Employees step B), managed by
 * the admin. Its tiers are profit ranges with a commission rate; a later step assigns a
 * table to employees and applies it to an employee's total profit for a period.
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
     * Employees using this table. There is no assignment yet (a later step adds
     * users.commission_tier_table_id); once that column exists it is counted here, so a
     * used table is never deleted -- only deactivated.
     */
    public function usageCount(): int
    {
        return Schema::hasColumn('users', 'commission_tier_table_id')
            ? DB::table('users')->where('commission_tier_table_id', $this->id)->count()
            : 0;
    }
}
