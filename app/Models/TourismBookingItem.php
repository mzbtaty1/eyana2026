<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One service of a booking with its own supplier (an existing account).
 *
 * Each service type has its own fields and its own quantity (FIELDS; the server clears the
 * fields a type does not use -- normalize() -- and calculates the amounts -- calculate()):
 *
 *   hotel / village  check-in / check-out (nights = the difference), rooms, room type,
 *                    adults / children; quantity = rooms x nights (unit = one room-night)
 *   transport        service date, from / to; quantity = vehicles / trips / transfers
 *   activity         service date; quantity = people / tickets / units
 *   day use          service date; quantity = people
 *   full package     quantity = packages / people
 *   other            quantity
 *
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
    const ACTIVITY = 'activity';
    const DAY_USE = 'day_use';
    const PACKAGE = 'package';
    const OTHER = 'other';

    /** Service types (a fixed list, no lookup table). */
    const TYPES = [
        self::HOTEL => 'فندق / قرية',
        self::TRANSPORT => 'انتقالات',
        self::ACTIVITY => 'رحلة / نشاط',
        self::DAY_USE => 'داي يوز',
        self::PACKAGE => 'باكدج كامل',
        self::OTHER => 'أخرى',
    ];

    /**
     * The fields of each type: date (range = check-in / check-out, single = service date,
     * null = none), hotel (rooms / nights / room type / adults / children), route (from / to),
     * and what its quantity counts (quantity: the label; a hotel's is calculated).
     */
    const FIELDS = [
        self::HOTEL => ['date' => 'range', 'hotel' => true, 'route' => false, 'quantity' => 'غرف × ليالي'],
        self::TRANSPORT => ['date' => 'single', 'hotel' => false, 'route' => true, 'quantity' => 'عدد السيارات / الرحلات'],
        self::ACTIVITY => ['date' => 'single', 'hotel' => false, 'route' => false, 'quantity' => 'عدد الأفراد / التذاكر'],
        self::DAY_USE => ['date' => 'single', 'hotel' => false, 'route' => false, 'quantity' => 'عدد الأفراد'],
        self::PACKAGE => ['date' => null, 'hotel' => false, 'route' => false, 'quantity' => 'عدد الباكدجات / الأفراد'],
        self::OTHER => ['date' => null, 'hotel' => false, 'route' => false, 'quantity' => 'الكمية'],
    ];

    /** Types with a service date (single date). */
    const DATED = [self::TRANSPORT, self::ACTIVITY, self::DAY_USE];

    protected $fillable = ['booking_id', 'source_program_item_id', 'service_type', 'description', 'supplier_id',
        'start_date', 'end_date', 'nights', 'rooms', 'room_type', 'route_from', 'route_to', 'adults', 'children',
        'quantity', 'unit_cost', 'unit_price', 'total_cost', 'total_sale', 'notes', 'sort'];

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

    public static function fields(?string $type): array
    {
        return self::FIELDS[$type] ?? self::FIELDS[self::OTHER];
    }

    /** The entered values with the fields its type does not use cleared (null). */
    public static function normalize(array $i): array
    {
        $f = self::fields($i['service_type'] ?? null);
        if (!$f['hotel']) {
            foreach (['nights', 'rooms', 'room_type', 'adults', 'children'] as $k) {
                $i[$k] = null;
            }
        }
        if ($f['date'] !== 'range') {
            $i['end_date'] = null;
        }
        if ($f['date'] === null) {
            $i['start_date'] = null;
        }
        if (!$f['route']) {
            $i['route_from'] = $i['route_to'] = null;
        }
        return $i;
    }

    /**
     * The server-side amounts of one service from its entered values (by its type):
     * [nights, quantity, total_cost, total_sale].
     */
    public static function calculate(array $i): array
    {
        $nights = null;
        if (($i['service_type'] ?? '') === self::HOTEL) {
            $nights = !empty($i['start_date']) && !empty($i['end_date'])
                ? (int) Carbon::parse($i['start_date'])->diffInDays(Carbon::parse($i['end_date']))
                : (int) ($i['nights'] ?? 0);
            $quantity = (int) ($i['rooms'] ?? 0) * $nights;
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

    /** Its date(s) as text: check-in → check-out, the service date, or ''. */
    public function dateText(): string
    {
        $in = $this->start_date?->format('Y-m-d');
        if ($this->isHotel()) {
            return trim(($in ?? '') . ' ← ' . ($this->end_date?->format('Y-m-d') ?? ''), ' ←');
        }
        return (string) $in;
    }

    /** Its type-specific details as text (hotel rooms / nights / people, transport route). */
    public function detailsText(): string
    {
        $parts = [];
        if ($this->isHotel()) {
            $parts[] = (int) $this->rooms . ' غرفة × ' . (int) $this->nights . ' ليلة' . ($this->room_type ? ' ' . $this->room_type : '');
            if ($this->adults !== null || $this->children !== null) {
                $parts[] = (int) $this->adults . ' بالغ + ' . (int) $this->children . ' طفل';
            }
        } elseif ($this->route_from || $this->route_to) {
            $parts[] = trim(($this->route_from ?? '') . ' ← ' . ($this->route_to ?? ''), ' ←');
        }
        return implode(' · ', $parts);
    }

    /** The quantity as text with what it counts (hotel: rooms x nights). */
    public function quantityText(): string
    {
        $q = rtrim(rtrim(number_format((float) $this->quantity, 2, '.', ''), '0'), '.');
        return $q . ' (' . self::fields($this->service_type)['quantity'] . ')';
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
