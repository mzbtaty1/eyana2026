<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One service of a program with its defaults (supplier, cost, price). pricing:
 * per_unit (quantity as entered) or per_person (quantity = the booking's adults + children).
 * Hotel / village items give rooms and nights instead (rooms x nights).
 */
class TourismProgramItem extends Model
{
    const PRICING = ['per_unit' => 'للوحدة', 'per_person' => 'للفرد'];

    protected $fillable = ['program_id', 'service_type', 'description', 'supplier_id', 'day_no', 'nights', 'rooms',
        'room_type', 'route_from', 'route_to', 'pricing', 'quantity', 'unit_cost', 'unit_price', 'notes', 'sort'];

    public function program()
    {
        return $this->belongsTo(TourismProgram::class, 'program_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
