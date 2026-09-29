<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{AccountStatement, Supplier, TourismBooking, TourismBookingItem, TourismProgram, User};
use App\Services\TourismLedger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Internal tourism service types: each type keeps only its own fields and has its own quantity
 * (hotel rooms x nights, transport vehicles / trips, activity people / tickets, day use people,
 * package and other a quantity); the server clears the other fields and calculates every
 * amount; validation depends on the type; a type change recalculates (and, once confirmed,
 * books the difference through the unchanged TourismLedger). Dev database, rolled back.
 */
class TourismServiceTypesTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->travelTo('2031-06-15 10:00:00');
        $this->admin = User::where('account_type', 2)->where('status', 1)->orderBy('id')->firstOrFail();
    }

    private function account(): Supplier
    {
        return Supplier::create(['name' => 'TEST TYPES ' . uniqid(), 'phone_1' => '0', 'phone_2' => '0', 'type' => '2', 'passport_id' => '',
            'passport_expiration_date' => '', 'email' => '', 'address' => '', 'debit_opening_balance' => '0', 'opening_credit_balance' => '0',
            'status' => 1, 'acc_type' => 2, 'limit_balance' => '0', 'in_index' => 0, 'in_stat' => 1]);
    }

    private function payload(Supplier $customer, array $items): array
    {
        return ['customer_id' => $customer->id, 'adults' => 2, 'children' => 1, 'items' => $items];
    }

    private function create(array $payload): TourismBooking
    {
        $max = (int) TourismBooking::max('id');
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_store'), $payload)->assertSessionHasNoErrors();
        return TourismBooking::where('id', '>', $max)->firstOrFail();
    }

    /** Hotel fields sent with any type -- the server must ignore / clear them for non-hotel types. */
    private function hotelJunk(): array
    {
        return ['end_date' => '2031-07-09', 'nights' => 5, 'rooms' => 7, 'room_type' => 'Suite', 'adults' => 9, 'children' => 9];
    }

    public function test_each_service_type_is_calculated_on_the_server_with_its_own_fields(): void
    {
        $s = $this->account();
        $b = $this->create($this->payload($this->account(), [
            ['service_type' => 'hotel', 'description' => 'Village', 'supplier_id' => $s->id, 'start_date' => '2031-07-01', 'end_date' => '2031-07-04',
                'rooms' => 2, 'room_type' => 'Double', 'adults' => 2, 'children' => 1, 'quantity' => 99, 'nights' => 99, 'unit_cost' => 1000, 'unit_price' => 1300],
            ['service_type' => 'transport', 'description' => 'Hiace', 'supplier_id' => $s->id, 'start_date' => '2031-07-01', 'route_from' => 'Cairo',
                'route_to' => 'Hurghada', 'quantity' => 2, 'unit_cost' => 1500, 'unit_price' => 1800] + $this->hotelJunk(),
            ['service_type' => 'activity', 'description' => 'Boat', 'supplier_id' => $s->id, 'start_date' => '2031-07-02', 'route_from' => 'X',
                'quantity' => 4, 'unit_cost' => 150, 'unit_price' => 200] + $this->hotelJunk(),
            ['service_type' => 'day_use', 'description' => 'Day use', 'supplier_id' => $s->id, 'start_date' => '2031-07-03', 'quantity' => 3,
                'unit_cost' => 250, 'unit_price' => 300] + $this->hotelJunk(),
            ['service_type' => 'package', 'description' => 'Package', 'supplier_id' => $s->id, 'start_date' => '2031-07-01', 'quantity' => 2,
                'unit_cost' => 5000, 'unit_price' => 6000] + $this->hotelJunk(),
            ['service_type' => 'other', 'description' => 'Visa help', 'supplier_id' => $s->id, 'quantity' => 1.5, 'unit_cost' => 100, 'unit_price' => 150],
        ]));
        $i = $b->items()->get()->keyBy('service_type');

        // hotel: rooms x nights (the posted quantity / nights are ignored)
        $this->assertSame([3, 2, 'Double', 6.0, 6000.0, 7800.0, '2031-07-04'], [(int) $i['hotel']->nights, (int) $i['hotel']->rooms, $i['hotel']->room_type,
            (float) $i['hotel']->quantity, (float) $i['hotel']->total_cost, (float) $i['hotel']->total_sale, $i['hotel']->end_date->format('Y-m-d')]);
        // transport: vehicles x unit, its route kept, no hotel field
        $this->assertSame([2.0, 3000.0, 3600.0, 'Cairo', 'Hurghada'], [(float) $i['transport']->quantity, (float) $i['transport']->total_cost,
            (float) $i['transport']->total_sale, $i['transport']->route_from, $i['transport']->route_to]);
        // activity / day use: people x unit
        $this->assertSame([4.0, 600.0, 800.0], [(float) $i['activity']->quantity, (float) $i['activity']->total_cost, (float) $i['activity']->total_sale]);
        $this->assertSame([3.0, 750.0, 900.0], [(float) $i['day_use']->quantity, (float) $i['day_use']->total_cost, (float) $i['day_use']->total_sale]);
        // package / other: quantity x unit, no date
        $this->assertSame([2.0, 10000.0, 12000.0, null], [(float) $i['package']->quantity, (float) $i['package']->total_cost, (float) $i['package']->total_sale, $i['package']->start_date]);
        $this->assertSame([1.5, 150.0, 225.0], [(float) $i['other']->quantity, (float) $i['other']->total_cost, (float) $i['other']->total_sale]);

        foreach (['transport', 'activity', 'day_use', 'package', 'other'] as $type) {
            $it = $i[$type];
            $this->assertSame([null, null, null, null, null, null], [$it->nights, $it->rooms, $it->room_type, $it->adults, $it->children, $it->end_date], "$type keeps no hotel field");
        }
        $this->assertSame([null, null], [$i['activity']->route_from, $i['activity']->route_to], 'only transport keeps a route');
        $this->assertSame(['2031-07-01', '2031-07-02', '2031-07-03'], [$i['transport']->start_date->format('Y-m-d'), $i['activity']->start_date->format('Y-m-d'), $i['day_use']->start_date->format('Y-m-d')]);

        // booking totals and profit: the ledger books exactly the items' totals (unchanged TourismLedger)
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_confirm', $b->id))->assertSessionHasNoErrors();
        $sale = 7800 + 3600 + 800 + 900 + 12000 + 225;
        $cost = 6000 + 3000 + 600 + 750 + 10000 + 150;
        $t = TourismLedger::totals($b->fresh());
        $this->assertSame([(float) $sale, (float) $cost, (float) ($sale - $cost)], [$t['sale'], $t['cost'], $t['profit']]);
        $this->assertEqualsWithDelta(-$cost, (float) AccountStatement::where('es_id', $b->booking_no)->where('supp_client_id', $s->id)->sum(DB::raw('debit_balance - credit_balance')), 0.001);
        $this->actingAs($this->admin)->get(route('site.tourism_bookings_show', $b->id))->assertOk()
            ->assertSee('Cairo ← Hurghada', false)->assertSee('عدد السيارات / الرحلات')->assertSee('2 غرفة × 3 ليلة Double');
        $this->actingAs($this->admin)->get(route('site.tourism_operations', ['type' => 'transport']))->assertOk()->assertSee('Cairo ← Hurghada', false);
    }

    public function test_validation_depends_on_the_service_type(): void
    {
        [$c, $s] = [$this->account(), $this->account()];
        $post = fn (array $item) => $this->actingAs($this->admin)->post(route('site.tourism_bookings_store'),
            $this->payload($c, [$item + ['description' => 'x', 'supplier_id' => $s->id, 'unit_cost' => 10, 'unit_price' => 12]]));

        // no hotel field is needed by transport / activity / day use; they need their service date and quantity
        foreach (['transport', 'activity', 'day_use'] as $type) {
            $post(['service_type' => $type, 'start_date' => '2031-07-01', 'quantity' => 1])->assertSessionHasNoErrors();
            $post(['service_type' => $type, 'quantity' => 1])->assertSessionHasErrors('items.0.start_date');
            $post(['service_type' => $type, 'start_date' => '2031-07-01'])->assertSessionHasErrors('items.0.quantity');
            $post(['service_type' => $type, 'start_date' => '2031-07-01', 'quantity' => 1])->assertSessionDoesntHaveErrors(['items.0.rooms', 'items.0.end_date', 'items.0.nights']);
        }
        // package / other: no date at all, a quantity
        foreach (['package', 'other'] as $type) {
            $post(['service_type' => $type, 'quantity' => 2])->assertSessionHasNoErrors();
            $post(['service_type' => $type])->assertSessionHasErrors('items.0.quantity');
        }
        // hotel: check-in / check-out and rooms, not a quantity; at least one night
        $post(['service_type' => 'hotel', 'start_date' => '2031-07-01', 'end_date' => '2031-07-02', 'rooms' => 1])->assertSessionHasNoErrors();
        $post(['service_type' => 'hotel', 'start_date' => '2031-07-01', 'rooms' => 1])->assertSessionHasErrors('items.0.end_date');
        $post(['service_type' => 'hotel', 'end_date' => '2031-07-02', 'rooms' => 1])->assertSessionHasErrors('items.0.start_date');
        $post(['service_type' => 'hotel', 'start_date' => '2031-07-01', 'end_date' => '2031-07-02'])->assertSessionHasErrors('items.0.rooms');
        $post(['service_type' => 'hotel', 'start_date' => '2031-07-02', 'end_date' => '2031-07-02', 'rooms' => 1])->assertSessionHasErrors('items.0.end_date');
    }

    public function test_switching_a_service_type_clears_the_old_fields_and_recalculates(): void
    {
        [$c, $s] = [$this->account(), $this->account()];
        $b = $this->create($this->payload($c, [['service_type' => 'hotel', 'description' => 'Village', 'supplier_id' => $s->id,
            'start_date' => '2031-07-01', 'end_date' => '2031-07-04', 'rooms' => 2, 'room_type' => 'Double', 'unit_cost' => 1000, 'unit_price' => 1300]]));
        $item = $b->items()->first();
        $this->assertSame(7800.0, (float) $item->total_sale);

        // hotel -> transport (the old hotel values still posted): cleared, quantity x unit
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_update', $b->id), $this->payload($c, [['id' => $item->id,
            'service_type' => 'transport', 'description' => 'Bus', 'supplier_id' => $s->id, 'start_date' => '2031-07-01', 'quantity' => 2,
            'route_from' => 'Cairo', 'route_to' => 'Sahl Hasheesh', 'unit_cost' => 1000, 'unit_price' => 1300] + $this->hotelJunk()]))->assertSessionHasNoErrors();
        $item->refresh();
        $this->assertSame(['transport', 2.0, 2000.0, 2600.0, null, null, null, null], [$item->service_type, (float) $item->quantity, (float) $item->total_cost,
            (float) $item->total_sale, $item->rooms, $item->nights, $item->room_type, $item->end_date]);

        // once confirmed, a type change books only the difference through the ledger
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_confirm', $b->id))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_update', $b->id), $this->payload($c, [['id' => $item->id,
            'service_type' => 'activity', 'description' => 'Trip', 'supplier_id' => $s->id, 'start_date' => '2031-07-02', 'quantity' => 3,
            'unit_cost' => 1000, 'unit_price' => 1300, 'route_from' => 'Cairo']]))->assertSessionHasNoErrors();
        $item->refresh();
        $this->assertSame(['activity', 3900.0, null], [$item->service_type, (float) $item->total_sale, $item->route_from]);
        $b->refresh();
        $this->assertSame(['sale' => 3900.0, 'cost' => 3000.0, 'profit' => 900.0], array_intersect_key(TourismLedger::totals($b), array_flip(['sale', 'cost', 'profit'])));
        $this->assertSame(4, AccountStatement::where('es_id', $b->booking_no)->count(), 'confirmation rows + one adjustment row per account');
    }

    public function test_the_form_shows_only_the_fields_of_each_service_type(): void
    {
        [$c, $s] = [$this->account(), $this->account()];
        $b = $this->create($this->payload($c, [
            ['service_type' => 'transport', 'description' => 'Bus', 'supplier_id' => $s->id, 'start_date' => '2031-07-01', 'quantity' => 1, 'unit_cost' => 1, 'unit_price' => 2],
            ['service_type' => 'hotel', 'description' => 'Village', 'supplier_id' => $s->id, 'start_date' => '2031-07-01', 'end_date' => '2031-07-03', 'rooms' => 1, 'unit_cost' => 1, 'unit_price' => 2],
        ]));
        $html = $this->actingAs($this->admin)->get(route('site.tourism_bookings_edit', $b->id))->assertOk()->getContent();

        // transport row (0): its hotel fields are hidden and disabled (never sent); its route and date are active
        foreach (['rooms', 'end_date', 'nights', 'room_type', 'adults', 'children'] as $field) {
            $this->assertMatchesRegularExpression('/name="items\[0\]\[' . $field . '\]"[^>]*disabled/', $html, "transport: $field disabled");
        }
        $this->assertDoesNotMatchRegularExpression('/name="items\[0\]\[route_from\]"[^>]*disabled/', $html);
        $this->assertSame(1, preg_match_all('/name="items\[0\]\[start_date\]"(?![^>]*disabled)/', $html), 'one active date field');
        // hotel row (1): hotel fields active, route disabled, quantity read-only (rooms x nights)
        $this->assertDoesNotMatchRegularExpression('/name="items\[1\]\[rooms\]"[^>]*disabled/', $html);
        $this->assertMatchesRegularExpression('/name="items\[1\]\[route_from\]"[^>]*disabled/', $html);
        $this->assertMatchesRegularExpression('/name="items\[1\]\[quantity\]"[^>]*readonly/', $html);
        $this->assertStringContainsString('window.TOURISM_FIELDS', $html);

        // the program form too
        $p = TourismProgram::create(['name' => 'TEST TYPES P', 'status' => 1]);
        $p->items()->create(['service_type' => 'transport', 'description' => 'Bus', 'route_from' => 'A', 'route_to' => 'B', 'pricing' => 'per_person', 'unit_cost' => 1, 'unit_price' => 2]);
        $html = $this->actingAs($this->admin)->get(route('site.tourism_programs_edit', $p->id))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/name="items\[0\]\[rooms\]"[^>]*disabled/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="items\[0\]\[route_from\]"[^>]*disabled/', $html);
    }

    public function test_program_items_keep_their_type_fields_and_carry_them_into_a_booking(): void
    {
        [$c, $hotel, $bus] = [$this->account(), $this->account(), $this->account()];
        $this->actingAs($this->admin)->post(route('site.tourism_programs_store'), ['name' => 'TEST TYPES ROUTE', 'items' => [
            ['service_type' => 'hotel', 'description' => 'Hotel', 'supplier_id' => $hotel->id, 'day_no' => 1, 'nights' => 3, 'rooms' => 2, 'room_type' => 'Double',
                'pricing' => 'per_person', 'quantity' => 9, 'unit_cost' => 900, 'unit_price' => 1200, 'route_from' => 'X'],
            ['service_type' => 'transport', 'description' => 'Bus', 'supplier_id' => $bus->id, 'day_no' => 1, 'route_from' => 'Cairo', 'route_to' => 'Hurghada',
                'pricing' => 'per_person', 'unit_cost' => 300, 'unit_price' => 400, 'nights' => 4, 'rooms' => 3],
        ]])->assertSessionHasNoErrors();

        $program = TourismProgram::where('name', 'TEST TYPES ROUTE')->firstOrFail();
        [$h, $t] = $program->items()->get()->all();
        $this->assertSame(['per_unit', 2, 3, null], [$h->pricing, (int) $h->rooms, (int) $h->nights, $h->route_from], 'a hotel is priced per room-night');
        $this->assertSame(['Cairo', 'Hurghada', null, null], [$t->route_from, $t->route_to, $t->nights, $t->rooms]);

        $items = $this->actingAs($this->admin)->get(route('site.tourism_bookings_create', ['program_id' => $program->id, 'start_date' => '2031-08-01', 'adults' => 2, 'children' => 1]))
            ->assertOk()->viewData('items');
        $this->assertSame([6, '2031-08-04', null], [$items[0]['quantity'], $items[0]['end_date'], $items[0]['route_from']]);
        $this->assertSame(['Cairo', 'Hurghada', 3, null, null], [$items[1]['route_from'], $items[1]['route_to'], $items[1]['quantity'], $items[1]['rooms'], $items[1]['end_date']]);

        // a program hotel needs its rooms
        $this->actingAs($this->admin)->post(route('site.tourism_programs_store'), ['name' => 'TEST TYPES BAD', 'items' => [
            ['service_type' => 'hotel', 'description' => 'Hotel', 'nights' => 3, 'pricing' => 'per_unit', 'unit_cost' => 1, 'unit_price' => 1],
        ]])->assertSessionHasErrors('items.0.rooms');
    }
}
