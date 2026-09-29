<?php

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * An internal tourism booking (TRB-00001): a customer (an existing account), its services
 * (TourismBookingItem, each with its own supplier) and people. A draft books nothing; a
 * confirmed booking is on the ledger (account_statements, es_id = booking_no -- see
 * TourismLedger). created_by is the owner / sales employee and never changes after creation.
 */
class TourismBooking extends Model
{
    const DRAFT = 'draft';
    const CONFIRMED = 'confirmed';
    const CANCELLED = 'cancelled';

    const STATUSES = [self::DRAFT => 'مسودة', self::CONFIRMED => 'مؤكد', self::CANCELLED => 'ملغي'];

    /** created_by / confirmed_* / cancelled_* are set explicitly (never mass assigned from a form). */
    protected $fillable = ['program_id', 'customer_id', 'contact_name', 'contact_phone', 'adults', 'children',
        'start_date', 'end_date', 'notes'];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public static function numberFor(int $id): string
    {
        return 'TRB-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    public function items()
    {
        return $this->hasMany(TourismBookingItem::class, 'booking_id')->orderBy('sort')->orderBy('id');
    }

    public function passengers()
    {
        return $this->hasMany(TourismBookingPassenger::class, 'booking_id')->orderBy('sort')->orderBy('id');
    }

    public function program()
    {
        return $this->belongsTo(TourismProgram::class, 'program_id');
    }

    public function customer()
    {
        return $this->belongsTo(Supplier::class, 'customer_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bonds()
    {
        return $this->hasMany(Bond::class, 'tourism_booking_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::CANCELLED;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function people(): int
    {
        return (int) $this->adults + (int) $this->children;
    }

    /** The bookings $user may see: every booking with tourism.view_all, else their own. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return Gate::forUser($user)->allows(Permissions::TOURISM_VIEW_ALL) ? $q : $q->where('created_by', $user->id);
    }

    public function isVisibleTo(User $user): bool
    {
        return Gate::forUser($user)->allows(Permissions::TOURISM_VIEW_ALL) || (int) $this->created_by === (int) $user->id;
    }
}
