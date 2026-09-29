<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An internal tourism program («برنامج سياحي», e.g. "Hurghada 4D/3N"): a reusable template.
 * A booking made from it copies its items (TourismBookings::itemsFromProgram) and is
 * independent from then on. status: 1 active (offered for new bookings), 0 inactive.
 */
class TourismProgram extends Model
{
    protected $fillable = ['name', 'destination', 'days', 'nights', 'description', 'status', 'created_by'];

    public function items()
    {
        return $this->hasMany(TourismProgramItem::class, 'program_id')->orderBy('sort')->orderBy('id');
    }

    public function bookings()
    {
        return $this->hasMany(TourismBooking::class, 'program_id');
    }

    public function isActive(): bool
    {
        return (int) $this->status === 1;
    }
}
