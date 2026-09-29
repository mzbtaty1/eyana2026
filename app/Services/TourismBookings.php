<?php

namespace App\Services;

use App\Models\{Bond, Log, TourismBooking, TourismBookingItem, TourismBookingPassenger, TourismProgram};
use Illuminate\Support\Carbon;
use DomainException;
use Illuminate\Support\Facades\{Auth, DB};

/**
 * Internal tourism bookings: save (draft or confirmed), confirm, cancel a service, cancel the
 * booking, delete a draft, and the items a program gives a new booking.
 *
 *   draft      free editing; items and people replaced as sent; no ledger rows
 *   confirm    posts the booking (TourismLedger::post 'confirm') -- one transaction
 *   confirmed  editing appends adjustment rows for the differences only ('adjust'); a service
 *              is never deleted any more (it is cancelled, 'cancel'); a cancelled service is
 *              frozen; the customer may change only while no voucher is linked
 *   cancelled  read-only (the penalties stay on the ledger)
 *
 * created_by (the owner / sales employee) is set once, on creation, and never changed.
 * Every change is written to the existing log (logs) with the booking number.
 * Errors are Arabic messages (null = done).
 */
class TourismBookings
{
    /**
     * Create a booking (draft) from validated data: header fields, items[], passengers[].
     * $ownerId: the owner (the signed-in user unless an admin chose an employee).
     */
    public static function create(array $data, int $ownerId): TourismBooking
    {
        return DB::transaction(function () use ($data, $ownerId) {
            $booking = new TourismBooking(self::header($data));
            $booking->status = TourismBooking::DRAFT;
            $booking->created_by = $ownerId;
            $booking->save();
            $booking->booking_no = TourismBooking::numberFor($booking->id);
            $booking->save();

            self::saveItems($booking, $data['items'] ?? []);   // new items only: cannot fail
            self::savePassengers($booking, $data['passengers'] ?? []);
            self::log($booking, 'تم إضافة حجز سياحة ' . $booking->booking_no);
            return $booking;
        });
    }

    /** Save the edit form: a draft is replaced as sent; a confirmed booking gets adjustment rows. */
    public static function update(int $bookingId, array $data): ?string
    {
        return self::tx(function () use ($bookingId, $data) {
            $booking = TourismBooking::whereKey($bookingId)->lockForUpdate()->first();
            if (!$booking) {
                return 'الحجز غير موجود';
            }
            if ($booking->isCancelled()) {
                return 'لا يمكن تعديل حجز ملغي';
            }
            if ($booking->isConfirmed()) {
                if ((int) $data['customer_id'] !== (int) $booking->customer_id && Bond::where('tourism_booking_id', $booking->id)->exists()) {
                    return 'لا يمكن تغيير العميل بعد تسجيل سندات على الحجز';
                }
                $sent = collect($data['items'] ?? [])->pluck('id')->filter()->map(fn ($id) => (int) $id);
                $missing = $booking->items()->where('status', TourismBookingItem::ACTIVE)->whereNotIn('id', $sent)->count();
                if ($missing) {
                    return 'لا يمكن حذف خدمة من حجز مؤكد؛ استخدم «إلغاء الخدمة» لإلغائها مع الاحتفاظ بسجلها';
                }
            }

            $booking->fill(self::header($data))->save();
            if ($error = self::saveItems($booking, $data['items'] ?? [])) {
                throw new DomainException($error);   // roll back the header already saved
            }
            self::savePassengers($booking, $data['passengers'] ?? []);

            $rows = $booking->isConfirmed() ? TourismLedger::post($booking, 'adjust') : [];
            self::log($booking, 'تم تعديل حجز سياحة ' . $booking->booking_no . ($rows ? ' (قيود تعديل: ' . count($rows) . ')' : ''));
            return null;
        });
    }

    /** Confirm a draft: post it to the ledger. */
    public static function confirm(int $bookingId): ?string
    {
        return self::tx(function () use ($bookingId) {
            $booking = TourismBooking::whereKey($bookingId)->lockForUpdate()->first();
            if (!$booking || !$booking->isDraft()) {
                return 'الحجز ليس مسودة';
            }
            if (!$booking->items()->where('status', TourismBookingItem::ACTIVE)->exists()) {
                return 'أضف خدمة واحدة على الأقل قبل التأكيد';
            }
            $booking->status = TourismBooking::CONFIRMED;
            $booking->confirmed_by = Auth::id();
            $booking->confirmed_at = now();
            $booking->save();
            TourismLedger::post($booking, 'confirm');
            self::log($booking, 'تم تأكيد حجز سياحة ' . $booking->booking_no);
            return null;
        });
    }

    /** Cancel one service of a confirmed booking, keeping $cost owed to the supplier and $fee charged to the customer. */
    public static function cancelItem(int $bookingId, int $itemId, float $cost, float $fee): ?string
    {
        return self::tx(function () use ($bookingId, $itemId, $cost, $fee) {
            $booking = TourismBooking::whereKey($bookingId)->lockForUpdate()->first();
            if (!$booking || !$booking->isConfirmed()) {
                return 'إلغاء خدمة متاح على الحجز المؤكد فقط';
            }
            $item = TourismBookingItem::where('booking_id', $booking->id)->find($itemId);
            if (!$item || !$item->isActive()) {
                return 'الخدمة غير موجودة أو ملغاة من قبل';
            }
            if (!$booking->items()->where('status', TourismBookingItem::ACTIVE)->where('id', '!=', $item->id)->exists()) {
                return 'هذه آخر خدمة في الحجز: استخدم «إلغاء الحجز» لإلغاء الحجز بالكامل';
            }
            if ($error = self::cancelOne($item, $cost, $fee)) {
                return $error;   // nothing written yet
            }
            TourismLedger::post($booking, 'cancel');
            self::log($booking, 'تم إلغاء خدمة «' . $item->title() . '» من حجز سياحة ' . $booking->booking_no);
            return null;
        });
    }

    /**
     * Cancel the whole booking: every active service is cancelled with its penalty
     * ($penalties[item id] = ['cost' => .., 'fee' => ..], default none). A draft is just marked cancelled.
     */
    public static function cancel(int $bookingId, string $reason, array $penalties = []): ?string
    {
        return self::tx(function () use ($bookingId, $reason, $penalties) {
            $booking = TourismBooking::whereKey($bookingId)->lockForUpdate()->first();
            if (!$booking || $booking->isCancelled()) {
                return 'الحجز ملغي من قبل';
            }
            $wasConfirmed = $booking->isConfirmed();
            if ($wasConfirmed) {
                foreach ($booking->items()->where('status', TourismBookingItem::ACTIVE)->get() as $item) {
                    $p = $penalties[$item->id] ?? [];
                    if ($error = self::cancelOne($item, (float) ($p['cost'] ?? 0), (float) ($p['fee'] ?? 0))) {
                        throw new DomainException($error);   // roll back the services already cancelled
                    }
                }
            }
            $booking->status = TourismBooking::CANCELLED;
            $booking->cancelled_by = Auth::id();
            $booking->cancelled_at = now();
            $booking->cancel_reason = $reason !== '' ? $reason : null;
            $booking->save();
            if ($wasConfirmed) {
                TourismLedger::post($booking, 'cancel');
            }
            self::log($booking, 'تم إلغاء حجز سياحة ' . $booking->booking_no);
            return null;
        });
    }

    /** Delete a draft (never a confirmed or cancelled booking). */
    public static function deleteDraft(int $bookingId): ?string
    {
        return self::tx(function () use ($bookingId) {
            $booking = TourismBooking::whereKey($bookingId)->lockForUpdate()->first();
            if (!$booking || !$booking->isDraft()) {
                return 'يمكن حذف المسودة فقط؛ الحجز المؤكد يلغى ولا يحذف';
            }
            $no = $booking->booking_no;
            $booking->delete();   // items and people go with it (cascade)
            Log::create(['log_txt' => 'تم حذف مسودة حجز سياحة ' . $no, 'log_ip' => request()->ip(), 'log_by' => Auth::id(), 'log_date' => date('Y-m-d')]);
            return null;
        });
    }

    /**
     * The items a program gives a new booking (form rows, not saved): its services with their
     * defaults; per-person quantities = adults + children; dates from $start (day_no, nights).
     */
    public static function itemsFromProgram(TourismProgram $program, ?string $start, int $adults, int $children): array
    {
        $rows = [];
        foreach ($program->items as $pi) {
            $from = $start ? Carbon::parse($start)->addDays(max(1, (int) ($pi->day_no ?: 1)) - 1) : null;
            $hotel = $pi->service_type === TourismBookingItem::HOTEL;
            $rows[] = TourismBookingItem::normalize([
                'id' => null,
                'source_program_item_id' => $pi->id,
                'service_type' => $pi->service_type,
                'description' => $pi->description,
                'supplier_id' => $pi->supplier_id,
                'start_date' => $from?->format('Y-m-d'),
                'end_date' => $from && $hotel && $pi->nights ? $from->copy()->addDays((int) $pi->nights)->format('Y-m-d') : null,
                'nights' => $pi->nights,
                'rooms' => $pi->rooms,
                'room_type' => $pi->room_type,
                'route_from' => $pi->route_from,
                'route_to' => $pi->route_to,
                'adults' => $adults,
                'children' => $children,
                'quantity' => $hotel ? (int) $pi->rooms * (int) $pi->nights
                    : ($pi->pricing === 'per_person' ? max(1, $adults + $children) : (float) $pi->quantity),
                'unit_cost' => (float) $pi->unit_cost,
                'unit_price' => (float) $pi->unit_price,
                'notes' => $pi->notes,
                'status' => TourismBookingItem::ACTIVE,
            ]);
        }
        return $rows;
    }

    /** Run $fn in one transaction; a DomainException (a refused rule) rolls it back and is returned as the message. */
    private static function tx(callable $fn): ?string
    {
        try {
            return DB::transaction($fn);
        } catch (DomainException $e) {
            return $e->getMessage();
        }
    }

    private static function header(array $d): array
    {
        return [
            'program_id' => $d['program_id'] ?? null,
            'customer_id' => (int) $d['customer_id'],
            'contact_name' => $d['contact_name'] ?? null,
            'contact_phone' => $d['contact_phone'] ?? null,
            'adults' => (int) ($d['adults'] ?? 0),
            'children' => (int) ($d['children'] ?? 0),
            'start_date' => $d['start_date'] ?? null,
            'end_date' => $d['end_date'] ?? null,
            'notes' => $d['notes'] ?? null,
        ];
    }

    /** Items as sent: update the booking's own (never a cancelled one), add new ones, drop the rest of a draft. */
    private static function saveItems(TourismBooking $booking, array $items): ?string
    {
        $keep = [];
        foreach (array_values($items) as $i => $row) {
            // only the fields of its type are kept; the amounts are calculated here (by its type)
            $row = TourismBookingItem::normalize($row);
            $calc = TourismBookingItem::calculate($row);
            $int = fn ($k) => ($row[$k] ?? '') !== '' && $row[$k] !== null ? (int) $row[$k] : null;
            $str = fn ($k) => ($row[$k] ?? '') !== '' ? $row[$k] : null;
            $attrs = [
                'service_type' => $row['service_type'],
                'description' => $row['description'],
                'supplier_id' => (int) $row['supplier_id'],
                'start_date' => $str('start_date'),
                'end_date' => $str('end_date'),
                'nights' => $calc['nights'],
                'rooms' => $int('rooms'),
                'room_type' => $str('room_type'),
                'route_from' => $str('route_from'),
                'route_to' => $str('route_to'),
                'adults' => $int('adults'),
                'children' => $int('children'),
                'quantity' => $calc['quantity'],
                'unit_cost' => round((float) $row['unit_cost'], 2),
                'unit_price' => round((float) $row['unit_price'], 2),
                'total_cost' => $calc['total_cost'],
                'total_sale' => $calc['total_sale'],
                'notes' => $row['notes'] ?? null,
                'sort' => $i,
            ];
            if (!empty($row['id'])) {
                $item = TourismBookingItem::where('booking_id', $booking->id)->find((int) $row['id']);
                if (!$item) {
                    return 'خدمة غير موجودة في هذا الحجز';
                }
                if ($item->isActive()) {
                    $item->update($attrs);
                }
                $keep[] = $item->id;
            } else {
                $attrs['source_program_item_id'] = !empty($row['source_program_item_id']) ? (int) $row['source_program_item_id'] : null;
                $keep[] = $booking->items()->create($attrs)->id;
            }
        }
        if ($booking->isDraft()) {
            $booking->items()->whereNotIn('id', $keep ?: [0])->delete();
        }
        return null;
    }

    private static function savePassengers(TourismBooking $booking, array $passengers): void
    {
        $booking->passengers()->delete();
        foreach (array_values($passengers) as $i => $p) {
            $booking->passengers()->create([
                'name' => $p['name'],
                'type' => $p['type'] ?? 'adult',
                'age' => ($p['age'] ?? '') !== '' ? (int) $p['age'] : null,
                'id_number' => $p['id_number'] ?? null,
                'phone' => $p['phone'] ?? null,
                'room_ref' => $p['room_ref'] ?? null,
                'notes' => $p['notes'] ?? null,
                'sort' => $i,
            ]);
        }
    }

    private static function cancelOne(TourismBookingItem $item, float $cost, float $fee): ?string
    {
        $cost = round($cost, 2);
        $fee = round($fee, 2);
        if ($cost < 0 || $fee < 0 || $cost > (float) $item->total_cost + 0.005 || $fee > (float) $item->total_sale + 0.005) {
            return 'غرامة الإلغاء لا تكون سالبة ولا تزيد عن تكلفة / سعر الخدمة (' . $item->title() . ')';
        }
        $item->status = TourismBookingItem::CANCELLED;
        $item->cancel_cost = $cost;
        $item->cancel_fee = $fee;
        $item->cancelled_at = now();
        $item->cancelled_by = Auth::id();
        $item->save();
        return null;
    }

    private static function log(TourismBooking $booking, string $text): void
    {
        Log::create(['log_txt' => $text, 'log_ip' => request()->ip(), 'log_by' => Auth::id(), 'log_date' => date('Y-m-d')]);
    }
}
