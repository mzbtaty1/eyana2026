<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A person on a booking (passenger / rooming lists). type: adult | child. */
class TourismBookingPassenger extends Model
{
    const TYPES = ['adult' => 'بالغ', 'child' => 'طفل'];

    protected $fillable = ['booking_id', 'name', 'type', 'age', 'id_number', 'phone', 'room_ref', 'notes', 'sort'];
}
