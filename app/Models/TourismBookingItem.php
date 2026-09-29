<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One service of a booking with its own supplier (an existing account).
 *
 * Amounts (calculated on the server, calculate()):
 *   hotel / village  quantity = rooms x nights; unit cost / price per room per night;
 *                    nights = check-out - check-in
 *   other services   quantity as entered (persons, trips, vehicles, ...)
 *   total_cost = quantity x unit_cost, total_sale = quantity x unit_price
 *
 * A cancelled service (status cancelled) is kept with its penalty: cancel_cost is still owed
 * to the supplier and cancel_fee still charged to the customer (TourismLedger books them
 * instead of the totals), so the booking's profit stays right.
 */
class TourismBookingItem extends Model
{
    const ACTIVE = 'active';
    const CANCELLED = 'cancelled';

    const HOTEL = 'hotel';
    const TRANSPORT = 'transport';

    /** Service types (a fixed list, no lookup table). */
    const TYPES = [
        self::HOTEL => 'فندق / قرية',
        self::TRANSPORT => 'انتقالات',
        'activity' => 'رحلة / نشاط',
        'day_use' => 'داي يوز',
        'package' => 'باكدج كامل',
        'other' => 'أخرى',
    ];

    protected $fillable = ['booking_id', 'source_program_item_id', 'service_type', 'description', 'supplier_id',
        'start_date', 'end_date', 'nights', 'rooms', 'room_type', 'adults', 'children', 'quantity', 'unit_cost',
        'unit_price', 'total_cost', 'total_sale', 'notes', 'sort'];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'cancelled_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(TourismBooking::class, 'booking_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function isHotel(): bool
    {
        return $this->service_type === self::HOTEL;
    }

    public static function typeLabel(?string $type): string
    {
        return self::TYPES[$type] ?? (string) $type;
    }

    /**
     * The server-side amounts of one service from its entered values:
     * [nights, quantity, total_cost, total_sale].
     */
    public static function calculate(array $i): array
    {
        $nights = isset($i['nights']) && $i['nights'] !== '' ? (int) $i['nights'] : null;
        if (($i['service_type'] ?? '') === self::HOTEL) {
            if (!empty($i['start_date']) && !empty($i['end_date'])) {
                $nights = (int) Carbon::parse($i['start_date'])->diffInDays(Carbon::parse($i['end_date']));
            }
            $quantity = (int) ($i['rooms'] ?? 0) * (int) $nights;
        } else {
            $quantity = round((float) ($i['quantity'] ?? 0), 2);
        }
        return [
            'nights' => $nights,
            'quantity' => $quantity,
            'total_cost' => round($quantity * (float) ($i['unit_cost'] ?? 0), 2),
            'total_sale' => round($quantity * (float) ($i['unit_price'] ?? 0), 2),
        ];
    }

    /** The service as one line of text: type, description and (hotel) rooms x nights. */
    public function title(): string
    {
        $t = self::typeLabel($this->service_type) . ': ' . $this->description;
        if ($this->isHotel()) {
            $t .= ' (' . (int) $this->rooms . ' غرفة × ' . (int) $this->nights . ' ليلة' . ($this->room_type ? ' - ' . $this->room_type : '') . ')';
        }
        return $t;
    }

    /** What the ledger books for it now: the totals, or the penalty once cancelled. */
    public function bookedSale(): float
    {
        return $this->isActive() ? (float) $this->total_sale : (float) $this->cancel_fee;
    }

    public function bookedCost(): float
    {
        return $this->isActive() ? (float) $this->total_cost : (float) $this->cancel_cost;
    }
}
