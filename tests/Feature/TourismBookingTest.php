<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{AccountStatement, Bond, Supplier, TourismBooking, TourismBookingItem, TourismProgram, User};
use App\Services\{AccountMovements, InvoicePassengerLedger, SupplierDirectory, TourismLedger, TourismPayments};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{DB, Hash};
use Tests\TestCase;

/**
 * Internal tourism bookings (Phase 1): programs, draft / confirmed bookings with several
 * suppliers, hotel rooms x nights, append-only ledger posting and adjustments, cancellation
 * penalties, customer receipts / refunds, supplier payments, reversals, ownership and
 * permissions, account protection and the account statement / movements (R1 / R2).
 * Runs against the dev database; test data dated 2031, every test rolled back.
 */
class TourismBookingTest extends TestCase
{
    use DatabaseTransactions;

    const TODAY = '2031-06-15';

    private User $admin;
    private User $employee;
    private int $storage;
    private int $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->travelTo(self::TODAY . ' 10:00:00');
        $this->admin = User::where('account_type', 2)->where('status', 1)->orderBy('id')->firstOrFail();
        $this->employee = $this->emp();
        $this->storage = (int) DB::table('storages')->orderBy('id')->value('id');
        $this->bank = (int) DB::table('banks')->orderBy('id')->value('id');
    }

    // ------------------------------------------------------------------ fixtures

    private function emp(array $attrs = []): User
    {
        return User::create(array_merge(['name' => 'TEST TOUR EMP ' . uniqid(), 'email' => 'tour-' . uniqid() . '@test.local',
            'password' => Hash::make('secret-123'), 'user_id' => 'FX_TEST', 'status' => '1', 'account_type' => 1, 'commission' => '10'], $attrs))->fresh();
    }

    private function account(string $label = 'ACC'): Supplier
    {
        return Supplier::create(['name' => 'TEST TOUR ' . $label . ' ' . uniqid(), 'phone_1' => '0', 'phone_2' => '0', 'type' => '2',
            'passport_id' => '', 'passport_expiration_date' => '', 'email' => '', 'address' => '', 'debit_opening_balance' => '0',
            'opening_credit_balance' => '0', 'status' => 1, 'acc_type' => 2, 'limit_balance' => '0', 'in_index' => 0, 'in_stat' => 1]);
    }

    private function hotel(Supplier $s, int $rooms = 2, string $in = '2031-07-01', string $out = '2031-07-04', float $cost = 1000, float $price = 1300, array $o = []): array
    {
        return array_merge(['service_type' => 'hotel', 'description' => 'Village X', 'supplier_id' => $s->id, 'start_date' => $in, 'end_date' => $out,
            'rooms' => $rooms, 'room_type' => 'Double', 'adults' => 2, 'children' => 2, 'unit_cost' => $cost, 'unit_price' => $price], $o);
    }

    private function service(Supplier $s, string $type, float $qty, float $cost, float $price, array $o = []): array
    {
        return array_merge(['service_type' => $type, 'description' => 'TEST ' . $type, 'supplier_id' => $s->id, 'start_date' => '2031-07-02',
            'quantity' => $qty, 'unit_cost' => $cost, 'unit_price' => $price], $o);
    }

    private function payload(Supplier $customer, array $items, array $over = []): array
    {
        return array_merge(['customer_id' => $customer->id, 'contact_name' => 'TEST FAMILY', 'contact_phone' => '0100000000',
            'adults' => 2, 'children' => 2, 'start_date' => '2031-07-01', 'end_date' => '2031-07-04', 'notes' => 'TEST',
            'items' => $items,
            'passengers' => [['name' => 'TEST DAD', 'type' => 'adult', 'room_ref' => '1'], ['name' => 'TEST KID', 'type' => 'child', 'age' => 7, 'room_ref' => '1']],
        ], $over);
    }

    private function create(User $as, array $payload): TourismBooking
    {
        $max = (int) TourismBooking::max('id');
        $this->actingAs($as)->post(route('site.tourism_bookings_store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        return TourismBooking::where('id', '>', $max)->firstOrFail();
    }

    /** The family booking: hotel 2 rooms x 3 nights (A), transport (B), activity 4 persons (C). */
    private function family(?User $as = null): array
    {
        [$customer, $a, $b, $c] = [$this->account('CUSTOMER'), $this->account('HOTEL'), $this->account('BUS'), $this->account('BOAT')];
        $booking = $this->create($as ?? $this->admin, $this->payload($customer, [
            $this->hotel($a), $this->service($b, 'transport', 1, 2000, 2500), $this->service($c, 'activity', 4, 150, 200),
        ]));
        return [$booking, $customer, $a, $b, $c];
    }

    private function confirmed(?User $owner = null): array
    {
        $r = $this->family($owner);
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_confirm', $r[0]->id))->assertSessionHasNoErrors();
        $r[0]->refresh();
        return $r;
    }

    /** Net (debit - credit) of the booking's rows on one account. */
    private function net(TourismBooking $b, int $account): float
    {
        return round((float) AccountStatement::where('es_id', $b->booking_no)->where('supp_client_id', $account)
            ->sum(DB::raw('debit_balance - credit_balance')), 2);
    }

    private function money(array $over = []): array
    {
        return array_merge(['amount' => 1000, 'storage_id' => $this->storage, 'money_way' => 1, 'date' => self::TODAY, 'reference' => 'TEST'], $over);
    }

    private function storageBalance(): float
    {
        return (float) DB::table('storages')->where('id', $this->storage)->value('balance');
    }

    // ------------------------------------------------------------------ bookings (draft)

    public function test_family_booking_with_multiple_suppliers_and_hotel_rooms_x_nights(): void
    {
        [$b, $customer, $a, $bus, $c] = $this->family();
        $this->assertSame('draft', $b->status);
        $this->assertMatchesRegularExpression('/^TRB-\d{5,}$/', $b->booking_no);
        $this->assertSame(TourismBooking::numberFor($b->id), $b->booking_no);
        $this->assertSame((int) $this->admin->id, (int) $b->created_by);

        $items = $b->items()->get()->keyBy('service_type');
        $this->assertCount(3, $items);
        $hotel = $items['hotel'];
        $this->assertSame([3, 2, 6.0, 6000.0, 7800.0], [(int) $hotel->nights, (int) $hotel->rooms, (float) $hotel->quantity, (float) $hotel->total_cost, (float) $hotel->total_sale]);
        $this->assertSame([2000.0, 2500.0], [(float) $items['transport']->total_cost, (float) $items['transport']->total_sale]);
        $this->assertSame([4.0, 600.0, 800.0], [(float) $items['activity']->quantity, (float) $items['activity']->total_cost, (float) $items['activity']->total_sale]);
        $this->assertSame([$a->id, $bus->id, $c->id], [(int) $hotel->supplier_id, (int) $items['transport']->supplier_id, (int) $items['activity']->supplier_id]);
        $this->assertCount(2, $b->passengers);

        // a draft books nothing
        $this->assertSame(0, AccountStatement::where('es_id', $b->booking_no)->count());
        $this->actingAs($this->admin)->get(route('site.tourism_bookings_show', $b->id))->assertOk()->assertSee($b->booking_no)->assertSee('تقديري', false);
        $this->actingAs($this->admin)->get(route('site.tourism_bookings'))->assertOk()->assertSee($b->booking_no);
    }

    public function test_hotel_quantity_is_calculated_on_the_server_and_needs_one_night(): void
    {
        $c = $this->account();
        $s = $this->account();
        // a posted quantity for a hotel is ignored: rooms x nights
        $b = $this->create($this->admin, $this->payload($c, [$this->hotel($s, 3, '2031-07-01', '2031-07-03', 500, 600, ['quantity' => 99])]));
        $this->assertSame([2, 6.0, 3000.0, 3600.0], [(int) $b->items[0]->nights, (float) $b->items[0]->quantity, (float) $b->items[0]->total_cost, (float) $b->items[0]->total_sale]);

        $this->actingAs($this->admin)->post(route('site.tourism_bookings_store'), $this->payload($c, [$this->hotel($s, 1, '2031-07-01', '2031-07-01')]))
            ->assertSessionHasErrors('items.0.end_date');
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_store'), $this->payload($c, [$this->hotel($s, 1, '2031-07-01', '2031-07-02', 1, 1, ['rooms' => null])]))
            ->assertSessionHasErrors('items.0.rooms');
        // other services: quantity x unit, quantity required
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_store'), $this->payload($c, [$this->service($s, 'transport', 0, 1, 1, ['quantity' => null])]))
            ->assertSessionHasErrors('items.0.quantity');
        // a service's supplier is never the booking's customer
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_store'), $this->payload($c, [$this->service($c, 'transport', 1, 1, 1)]))
            ->assertSessionHasErrors('items.0.supplier_id');
    }

    public function test_draft_can_be_edited_freely_and_deleted(): void
    {
        [$b, $customer, $a, $bus] = $this->family();
        $items = $b->items()->get();
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_update', $b->id), $this->payload($customer, [
            ['id' => $items[0]->id] + $this->hotel($a, 1),
        ]))->assertSessionHasNoErrors();
        $this->assertSame(1, $b->items()->count(), 'a draft service not sent is removed');
        $this->assertSame(3900.0, (float) $b->items()->first()->total_sale);
        $this->assertSame(0, AccountStatement::where('es_id', $b->booking_no)->count());

        $this->actingAs($this->admin)->post(route('site.tourism_bookings_delete', $b->id))->assertRedirect(route('site.tourism_bookings'));
        $this->assertNull(TourismBooking::find($b->id));
    }

    // ------------------------------------------------------------------ confirmation posting

    public function test_confirmation_posts_the_customer_and_each_supplier(): void
    {
        [$b, $customer, $a, $bus, $c] = $this->confirmed();
        $this->assertSame('confirmed', $b->status);
        $this->assertNotNull($b->confirmed_at);

        $rows = TourismLedger::rows($b);
        $this->assertCount(4, $rows, 'one row per account');
        $this->assertSame(11100.0, $this->net($b, $customer->id));      // 7800 + 2500 + 800
        $this->assertSame(-6000.0, $this->net($b, $a->id));
        $this->assertSame(-2000.0, $this->net($b, $bus->id));
        $this->assertSame(-600.0, $this->net($b, $c->id));
        foreach ($rows as $r) {
            $m = InvoicePassengerLedger::marker($r);
            $this->assertSame(['tourism', 'confirm', $b->id], [$m['kind'], $m['op'], $m['booking_id']]);
            $this->assertSame([1, '3', self::TODAY], [(int) $r->transaction_type, (string) $r->invoice_type, (string) $r->crt_date]);
            $this->assertSame('tourism', InvoicePassengerLedger::rowKind($r));
            $this->assertSame('حجز سياحة داخلية', InvoicePassengerLedger::kindLabel($r));
            $lines = array_sum(array_map(fn ($l) => ($l['debit'] ?? 0) - ($l['credit'] ?? 0), $m['lines']));
            $this->assertEqualsWithDelta((float) $r->debit_balance - (float) $r->credit_balance, $lines, 0.001);
        }
        $this->assertSame(['sale' => 11100.0, 'cost' => 8600.0, 'profit' => 2500.0], array_intersect_key(TourismLedger::totals($b), array_flip(['sale', 'cost', 'profit'])));

        $s = TourismPayments::summary($b);
        $this->assertSame([11100.0, 0.0, 11100.0, 8600.0, 8600.0], [$s['sale'], $s['customer_paid'], $s['customer_remaining'], $s['supplier_payable'], $s['supplier_remaining']]);

        // confirming again is refused; nothing more is posted
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_confirm', $b->id))->assertSessionHasErrors('booking');
        $this->assertCount(4, TourismLedger::rows($b));
        $this->actingAs($this->admin)->get(route('site.tourism_bookings_show', $b->id))->assertOk()->assertSee('11,100.00');
    }

    // ------------------------------------------------------------------ post-confirmation changes

    public function test_adjustments_append_rows_dated_today_and_never_change_old_rows(): void
    {
        [$b, $customer, $a, $bus, $c] = $this->confirmed();
        $before = AccountStatement::where('es_id', $b->booking_no)->orderBy('id')->get()->map->getAttributes()->all();
        $items = $b->items()->get()->keyBy('service_type');

        $this->travelTo('2031-06-20 09:00:00');
        $d = $this->account('NEW BUS');
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_update', $b->id), $this->payload($customer, [
            ['id' => $items['hotel']->id] + $this->hotel($a, 2, '2031-07-01', '2031-07-05'),                    // 4 nights: 8000 / 10400
            ['id' => $items['transport']->id] + $this->service($d, 'transport', 1, 2000, 2500),                 // supplier B -> D
            ['id' => $items['activity']->id] + $this->service($c, 'activity', 4, 150, 200),                     // unchanged
        ]))->assertSessionHasNoErrors();

        $this->assertSame($before, AccountStatement::where('es_id', $b->booking_no)->orderBy('id')->limit(4)->get()->map->getAttributes()->all(), 'confirmation rows unchanged');
        $new = AccountStatement::where('es_id', $b->booking_no)->where('id', '>', max(array_column($before, 'id')))->get();
        $this->assertCount(4, $new, 'customer, hotel, old bus (reversal), new bus');
        $this->assertTrue($new->every(fn ($r) => $r->crt_date === '2031-06-20' && InvoicePassengerLedger::marker($r)['op'] === 'adjust'));
        $this->assertSame(13700.0, $this->net($b, $customer->id));   // +2600
        $this->assertSame(-8000.0, $this->net($b, $a->id));
        $this->assertSame(0.0, $this->net($b, $bus->id), 'the old supplier is reversed');
        $this->assertSame(-2000.0, $this->net($b, $d->id), 'the new supplier is credited');
        $this->assertSame(3100.0, TourismLedger::totals($b)['profit']);

        // saving again without changes appends nothing
        $count = AccountStatement::where('es_id', $b->booking_no)->count();
        $b->refresh()->load('items');
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_update', $b->id), $this->payload($customer, $b->items->map(fn ($i) => [
            'id' => $i->id, 'service_type' => $i->service_type, 'description' => $i->description, 'supplier_id' => $i->supplier_id,
            'start_date' => $i->start_date?->format('Y-m-d'), 'end_date' => $i->end_date?->format('Y-m-d'), 'rooms' => $i->rooms,
            'quantity' => $i->quantity, 'unit_cost' => $i->unit_cost, 'unit_price' => $i->unit_price,
        ])->all()))->assertSessionHasNoErrors();
        $this->assertSame($count, AccountStatement::where('es_id', $b->booking_no)->count());
    }

    public function test_a_confirmed_service_cannot_be_removed_by_an_edit(): void
    {
        [$b, $customer, $a] = $this->confirmed();
        $hotel = $b->items()->where('service_type', 'hotel')->first();
        $count = AccountStatement::where('es_id', $b->booking_no)->count();
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_update', $b->id), $this->payload($customer, [['id' => $hotel->id] + $this->hotel($a)]))
            ->assertSessionHasErrors('booking');
        $this->assertSame(3, $b->items()->where('status', 'active')->count());
        $this->assertSame($count, AccountStatement::where('es_id', $b->booking_no)->count());
    }

    // ------------------------------------------------------------------ cancellation

    public function test_cancelling_one_service_keeps_its_penalty(): void
    {
        [$b, $customer, $a, $bus, $c] = $this->confirmed();
        $activity = $b->items()->where('service_type', 'activity')->first();
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_cancel_item', [$b->id, $activity->id]), ['cancel_cost' => 100, 'cancel_fee' => 150])
            ->assertSessionHasNoErrors();
        $activity->refresh();
        $this->assertSame(['cancelled', 100.0, 150.0], [$activity->status, (float) $activity->cancel_cost, (float) $activity->cancel_fee]);
        $this->assertSame('confirmed', $b->fresh()->status, 'a service cancellation is not a booking cancellation');
        $this->assertSame(10450.0, $this->net($b, $customer->id));   // 11100 - 800 + 150
        $this->assertSame(-100.0, $this->net($b, $c->id));
        $this->assertSame(['sale' => 10450.0, 'cost' => 8100.0, 'profit' => 2350.0], array_intersect_key(TourismLedger::totals($b), array_flip(['sale', 'cost', 'profit'])));
        $cancelRows = AccountStatement::where('es_id', $b->booking_no)->get()->filter(fn ($r) => InvoicePassengerLedger::marker($r)['op'] === 'cancel');
        $this->assertCount(2, $cancelRows);
        $this->assertTrue($cancelRows->every(fn ($r) => InvoicePassengerLedger::kindLabel($r) === 'إلغاء حجز/خدمة سياحة'));

        // a penalty above the service's own amounts is refused
        $bus = $b->items()->where('service_type', 'transport')->first();
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_cancel_item', [$b->id, $bus->id]), ['cancel_cost' => 5000])->assertSessionHasErrors('booking');
        $this->assertTrue($bus->fresh()->isActive());
    }

    public function test_cancelling_the_whole_booking_keeps_history_and_penalties(): void
    {
        [$b, $customer, $a, $bus, $c] = $this->confirmed();
        $items = $b->items()->get()->keyBy('service_type');
        $rowsBefore = AccountStatement::where('es_id', $b->booking_no)->count();
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_cancel', $b->id), [
            'cancel_reason' => 'TEST',
            'penalties' => [$items['hotel']->id => ['cost' => 1000, 'fee' => 1300]],
        ])->assertSessionHasNoErrors();
        $b->refresh();
        $this->assertSame(['cancelled', 'TEST'], [$b->status, $b->cancel_reason]);
        $this->assertSame(3, $b->items()->where('status', 'cancelled')->count(), 'services kept, marked cancelled');
        $this->assertGreaterThan($rowsBefore, AccountStatement::where('es_id', $b->booking_no)->count());
        $this->assertSame([1300.0, -1000.0, 0.0, 0.0], [$this->net($b, $customer->id), $this->net($b, $a->id), $this->net($b, $bus->id), $this->net($b, $c->id)]);
        $this->assertSame(300.0, TourismLedger::totals($b)['profit']);
        // a cancelled booking is read-only
        $this->actingAs($this->admin)->get(route('site.tourism_bookings_edit', $b->id))->assertForbidden();
    }

    // ------------------------------------------------------------------ money

    public function test_customer_receipt_supplier_payment_and_limits(): void
    {
        [$b, $customer, $a] = $this->confirmed();
        $start = $this->storageBalance();

        $this->actingAs($this->admin)->post(route('site.tourism_bookings_receive', $b->id), $this->money(['amount' => 5000]))->assertSessionHasNoErrors();
        $receipt = Bond::where('tourism_booking_id', $b->id)->firstOrFail();
        $this->assertSame([2, $customer->id, 0, '0'], [(int) $receipt->type, (int) $receipt->from_account, (int) $receipt->is_invoice, (string) $receipt->invoice_id]);
        $this->assertEqualsWithDelta($start + 5000, $this->storageBalance(), 0.001);
        $this->assertSame(-5000.0, round((float) AccountStatement::where('es_id', $receipt->es_id)->where('supp_client_id', $customer->id)->sum(DB::raw('debit_balance - credit_balance')), 2));
        $this->assertSame(1, DB::table('storage_statements')->where('bond_id', $receipt->id)->where('entry_type', 'bond')->count());

        $s = TourismPayments::summary($b);
        $this->assertSame([5000.0, 6100.0], [$s['customer_paid'], $s['customer_remaining']]);
        // no more than what the customer owes; no refund while nothing is due to them
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_receive', $b->id), $this->money(['amount' => 6100.01]))->assertSessionHasErrors('booking');
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_refund', $b->id), $this->money(['amount' => 1]))->assertSessionHasErrors('booking');

        // supplier payment, linked to the service, by bank
        $hotel = $b->items()->where('service_type', 'hotel')->first();
        $bankBefore = (float) DB::table('banks')->where('id', $this->bank)->value('bank_balance');
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_pay_supplier', $b->id), $this->money(['amount' => 6000, 'supplier_id' => $a->id,
            'item_id' => $hotel->id, 'money_way' => 2, 'bank_id' => $this->bank]))->assertSessionHasNoErrors();
        $pay = Bond::where('tourism_booking_id', $b->id)->where('type', 1)->firstOrFail();
        $this->assertSame([$a->id, $hotel->id], [(int) $pay->to_account, (int) $pay->tourism_booking_item_id]);
        $this->assertEqualsWithDelta($bankBefore - 6000, (float) DB::table('banks')->where('id', $this->bank)->value('bank_balance'), 0.001);
        $s = TourismPayments::summary($b);
        $this->assertSame([6000.0, 6000.0, 0.0], [$s['suppliers'][$a->id]['payable'], $s['suppliers'][$a->id]['paid'], $s['suppliers'][$a->id]['remaining']]);
        $this->assertSame([8600.0, 6000.0, 2600.0], [$s['supplier_payable'], $s['supplier_paid'], $s['supplier_remaining']]);
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_pay_supplier', $b->id), $this->money(['amount' => 1, 'supplier_id' => $a->id]))->assertSessionHasErrors('booking');
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_pay_supplier', $b->id), $this->money(['amount' => 1, 'supplier_id' => $customer->id]))->assertSessionHasErrors('booking');

        // a draft takes no money
        [$draft] = $this->family();
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_receive', $draft->id), $this->money())->assertSessionHasErrors('booking');
    }

    public function test_refund_after_cancellation_and_reversal_of_a_receipt(): void
    {
        [$b, $customer] = $this->confirmed();
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_receive', $b->id), $this->money(['amount' => 11100]))->assertSessionHasNoErrors();
        $activity = $b->items()->where('service_type', 'activity')->first();
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_cancel_item', [$b->id, $activity->id]), [])->assertSessionHasNoErrors();
        $this->assertSame(-800.0, TourismPayments::summary($b)['customer_remaining'], 'due to the customer');

        $this->actingAs($this->admin)->post(route('site.tourism_bookings_refund', $b->id), $this->money(['amount' => 800.01]))->assertSessionHasErrors('booking');
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_refund', $b->id), $this->money(['amount' => 800]))->assertSessionHasNoErrors();
        $refund = Bond::where('tourism_booking_id', $b->id)->where('type', 1)->firstOrFail();
        $this->assertSame((int) $customer->id, (int) $refund->to_account);
        $s = TourismPayments::summary($b);
        $this->assertSame([11100.0, 800.0, 10300.0, 0.0], [$s['received'], $s['refunded'], $s['customer_paid'], $s['customer_remaining']]);

        // reverse the receipt: nothing deleted, offsetting rows, treasury restored
        $receipt = Bond::where('tourism_booking_id', $b->id)->where('type', 2)->firstOrFail();
        $bondRow = $receipt->getAttributes();
        $stBefore = AccountStatement::where('es_id', $receipt->es_id)->orderBy('id')->get()->map->getAttributes()->all();
        $balance = $this->storageBalance();
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_reverse_voucher', [$b->id, $receipt->id]))->assertSessionHasNoErrors();
        $this->assertSame($bondRow, Bond::findOrFail($receipt->id)->getAttributes());
        $this->assertSame($stBefore, AccountStatement::where('es_id', $receipt->es_id)->orderBy('id')->limit(2)->get()->map->getAttributes()->all());
        $this->assertSame(4, AccountStatement::where('es_id', $receipt->es_id)->count());
        $this->assertEqualsWithDelta(0, (float) AccountStatement::where('es_id', $receipt->es_id)->sum(DB::raw('debit_balance - credit_balance')), 0.001);
        $this->assertEqualsWithDelta($balance - 11100, $this->storageBalance(), 0.001);
        $storage = DB::table('storage_statements')->where('bond_id', $receipt->id)->orderBy('id')->get();
        $this->assertSame([1, 'reversal', 11100.0], [(int) $storage[0]->is_voided, $storage[1]->entry_type, (float) $storage[1]->debit]);
        $s = TourismPayments::summary($b);
        $this->assertSame([0.0, -800.0, 11100.0], [$s['received'], $s['customer_paid'], $s['customer_remaining']], 'sale 10300 + the 800 refunded');
        $this->assertTrue(collect($s['vouchers'])->firstWhere('bond.id', $receipt->id)->reversed);
        // reversing twice is refused
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_reverse_voucher', [$b->id, $receipt->id]))->assertSessionHasErrors('booking');
    }

    public function test_tourism_vouchers_cannot_be_edited_or_deleted_from_the_bonds_screen(): void
    {
        [$b] = $this->confirmed();
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_receive', $b->id), $this->money())->assertSessionHasNoErrors();
        $bond = Bond::where('tourism_booking_id', $b->id)->firstOrFail();
        $this->actingAs($this->admin)->post(route('site.bonds_delete', $bond->id))->assertSessionHasErrors('bond');
        $this->actingAs($this->admin)->get(route('site.bonds_edit', $bond->id))->assertSessionHasErrors('bond');
        $row = $bond->getAttributes();
        $this->actingAs($this->admin)->post(route('site.bonds_save_update'), ['bond_id' => $bond->id, 'amount' => 1])->assertSessionHasErrors();
        $this->assertSame($row, Bond::findOrFail($bond->id)->getAttributes(), 'the voucher is unchanged');
    }

    public function test_receipt_bond_books_exactly_what_the_voucher_screen_books(): void
    {
        $account = $this->account();
        $normalize = function (int $bondId) {
            $bond = Bond::findOrFail($bondId);
            $strip = fn ($rows) => collect($rows)->map(fn ($r) => collect((array) $r)->except(['id', 'bond_id', 'created_at', 'updated_at', 'system_id', 'running_balance'])
                ->map(fn ($x) => is_string($x) ? str_replace((string) $bondId, '{B}', $x) : $x)->all())->all();
            return [
                'bond' => $strip([$bond->getAttributes()]),
                'statements' => $strip(DB::table('account_statements')->where('es_id', $bond->es_id)->orderBy('id')->get()),
                'storage' => $strip(DB::table('storage_statements')->where('bond_id', $bondId)->orderBy('id')->get()),
                'bank' => $strip(DB::table('bank_statements')->where('bond_id', $bondId)->orderBy('id')->get()),
            ];
        };
        foreach ([[1, null], [2, $this->bank]] as [$way, $bank]) {
            $max = (int) Bond::max('id');
            $this->actingAs($this->admin)->post(route('site.bonds_save'), ['type_slctd' => 2, 'storage_id2' => $this->storage, 'sub_id2' => '0',
                'supp_id2' => $account->id, 'amount2' => '321.5', 'money_way2' => $way, 'bank_id2' => $bank, 'crt_date2' => '2031-06-10',
                'transaction_info2' => 'TEST INFO', 'collector_info2' => 'TEST C'])->assertSessionHasNoErrors();
            $screen = Bond::where('id', '>', $max)->firstOrFail();
            // the entries of the voucher screen (unchanged): +treasury, account credit, treasury debit, ledgers
            $st = DB::table('account_statements')->where('es_id', $screen->es_id)->orderBy('id')->get();
            $this->assertSame(['FLY-BD' . $screen->id, 2, 10, 321.5, 0.0, 0.0, 321.5], [$screen->es_id, (int) $st[0]->transaction_type, (int) $st[0]->invoice_type,
                (float) $st[0]->debit_balance, (float) $st[0]->credit_balance, (float) $st[1]->debit_balance, (float) $st[1]->credit_balance]);
            $this->assertSame([1, 0], [(int) $st[0]->is_storage, (int) $st[1]->is_storage]);
            $this->assertSame($way === 2 ? 1 : 0, DB::table('bank_statements')->where('bond_id', $screen->id)->count());

            $service = \App\Services\ReceiptBond::create(['storage_id' => $this->storage, 'sub_id' => '0', 'supp_id' => $account->id, 'amount' => '321.5',
                'money_way' => $way, 'bank_id' => $bank, 'collector_info' => 'TEST C', 'info' => 'TEST INFO', 'date' => '2031-06-10', 'file_path' => ''])->id;
            $this->assertSame($normalize($screen->id), $normalize($service), "money_way $way");
        }
    }

    // ------------------------------------------------------------------ programs

    public function test_program_creation_and_a_booking_from_it_is_an_independent_copy(): void
    {
        [$hotel, $bus, $boat, $customer] = [$this->account('HOTEL'), $this->account('BUS'), $this->account('BOAT'), $this->account('CUSTOMER')];
        $this->actingAs($this->admin)->post(route('site.tourism_programs_store'), [
            'name' => 'TEST Hurghada 4D/3N', 'destination' => 'Hurghada', 'days' => 4, 'nights' => 3,
            'items' => [
                ['service_type' => 'hotel', 'description' => 'Hotel 3 nights', 'supplier_id' => $hotel->id, 'day_no' => 1, 'nights' => 3, 'rooms' => 1, 'pricing' => 'per_unit', 'unit_cost' => 900, 'unit_price' => 1200],
                ['service_type' => 'transport', 'description' => 'Bus', 'supplier_id' => $bus->id, 'day_no' => 1, 'pricing' => 'per_person', 'unit_cost' => 300, 'unit_price' => 400],
                ['service_type' => 'activity', 'description' => 'Boat trip', 'supplier_id' => $boat->id, 'day_no' => 2, 'pricing' => 'per_person', 'unit_cost' => 250, 'unit_price' => 350],
            ],
        ])->assertSessionHasNoErrors();
        $program = TourismProgram::where('name', 'TEST Hurghada 4D/3N')->firstOrFail();
        $this->assertSame(3, $program->items()->count());
        $this->actingAs($this->admin)->get(route('site.tourism_programs'))->assertOk()->assertSee('TEST Hurghada 4D/3N');

        // the create form is filled from the program: dates from the start date, per-person = 3 people
        $res = $this->actingAs($this->admin)->get(route('site.tourism_bookings_create', ['program_id' => $program->id, 'start_date' => '2031-08-01', 'adults' => 2, 'children' => 1]))->assertOk();
        $items = $res->viewData('items');
        $this->assertSame(['2031-08-01', '2031-08-04', 3.0, 3.0, '2031-08-02'], [$items[0]['start_date'], $items[0]['end_date'], (float) $items[1]['quantity'], (float) $items[2]['quantity'], $items[2]['start_date']]);
        $this->assertSame('2031-08-04', $res->viewData('header')['end_date']);

        $items = array_map(fn ($i) => array_intersect_key($i, array_flip(['source_program_item_id', 'service_type', 'description', 'supplier_id', 'start_date', 'end_date', 'rooms', 'quantity', 'unit_cost', 'unit_price'])), $items);
        $b = $this->create($this->admin, $this->payload($customer, $items, ['program_id' => $program->id, 'adults' => 2, 'children' => 1, 'passengers' => []]));
        $this->assertSame((int) $program->id, (int) $b->program_id);
        $this->assertSame([3600.0, 1200.0, 1050.0], $b->items->pluck('total_sale')->map(fn ($v) => (float) $v)->all());
        $this->assertTrue($b->items->every(fn ($i) => $i->source_program_item_id !== null));

        // editing / deactivating the program never touches the booking
        $this->actingAs($this->admin)->post(route('site.tourism_programs_update', $program->id), ['name' => 'TEST Hurghada 4D/3N', 'items' => [
            ['service_type' => 'hotel', 'description' => 'Other hotel', 'pricing' => 'per_unit', 'nights' => 3, 'rooms' => 1, 'unit_cost' => 1, 'unit_price' => 2],
        ]])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('site.tourism_programs_status', $program->id))->assertSessionHasNoErrors();
        $this->assertSame(0, (int) $program->fresh()->status);
        $this->assertSame([3600.0, 1200.0, 1050.0], $b->items()->get()->pluck('total_sale')->map(fn ($v) => (float) $v)->all());
        $this->assertSame('Hotel 3 nights', $b->items()->first()->description);
    }

    // ------------------------------------------------------------------ ownership and permissions

    public function test_employee_permissions_and_ownership(): void
    {
        $e = $this->employee;
        foreach (['tourism.view', 'tourism.create', 'tourism.edit'] as $ability) {
            $this->assertTrue($e->can($ability), $ability);
        }
        foreach (['tourism.view_all', 'tourism.confirm', 'tourism.cancel', 'tourism.programs'] as $ability) {
            $this->assertFalse($e->can($ability), $ability);
            $this->assertTrue($this->admin->can($ability), $ability);
        }

        // the employee's own booking; an admin can not pass it to someone else by an edit
        [$b, $customer, $a, $bus, $c] = $this->family($e);
        $this->assertSame((int) $e->id, (int) $b->created_by);
        $this->actingAs($e)->post(route('site.tourism_bookings_store'), $this->payload($customer, [$this->hotel($a)], ['owner_id' => $this->admin->id]))->assertSessionHasNoErrors();
        $this->assertSame((int) $e->id, (int) TourismBooking::latest('id')->first()->created_by, 'an employee can not choose another owner');

        $other = $this->emp();
        $this->actingAs($other)->get(route('site.tourism_bookings_show', $b->id))->assertForbidden();
        $this->actingAs($other)->get(route('site.tourism_bookings_edit', $b->id))->assertForbidden();
        $this->actingAs($other)->get(route('site.tourism_bookings'))->assertOk()->assertDontSee($b->booking_no);
        $this->actingAs($e)->get(route('site.tourism_bookings'))->assertOk()->assertSee($b->booking_no);

        // confirm / cancel / programs / supplier payments / refunds are not the employee's
        $this->actingAs($e)->post(route('site.tourism_bookings_confirm', $b->id))->assertForbidden();
        $this->actingAs($e)->get(route('site.tourism_programs'))->assertForbidden();
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_confirm', $b->id))->assertSessionHasNoErrors();
        $b->refresh();
        $this->actingAs($e)->post(route('site.tourism_bookings_cancel', $b->id))->assertForbidden();
        $this->actingAs($e)->get(route('site.tourism_bookings_edit', $b->id))->assertForbidden();   // confirmed: tourism.confirm
        $this->actingAs($e)->post(route('site.tourism_bookings_pay_supplier', $b->id), $this->money(['supplier_id' => $a->id]))->assertForbidden();
        $this->actingAs($e)->post(route('site.tourism_bookings_refund', $b->id), $this->money())->assertForbidden();

        // the owner takes the customer's payment; another employee can not
        $this->actingAs($other)->post(route('site.tourism_bookings_receive', $b->id), $this->money())->assertForbidden();
        $this->actingAs($e)->post(route('site.tourism_bookings_receive', $b->id), $this->money(['amount' => 500]))->assertSessionHasNoErrors();
        $this->assertSame(500.0, TourismPayments::summary($b)['customer_paid']);

        // an admin edit keeps the owner
        $items = $b->items()->get();
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_update', $b->id), $this->payload($customer, $items->map(fn ($i) => [
            'id' => $i->id, 'service_type' => $i->service_type, 'description' => $i->description, 'supplier_id' => $i->supplier_id,
            'start_date' => $i->start_date?->format('Y-m-d'), 'end_date' => $i->end_date?->format('Y-m-d'), 'rooms' => $i->rooms,
            'quantity' => $i->quantity, 'unit_cost' => $i->unit_cost, 'unit_price' => $i->unit_price,
        ])->all(), ['created_by' => $this->admin->id, 'owner_id' => $this->admin->id]))->assertSessionHasNoErrors();
        $this->assertSame((int) $e->id, (int) $b->fresh()->created_by);
        // the customer can not change once a voucher is linked
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_update', $b->id), $this->payload($this->account(), [], ['items' => $items->map(fn ($i) => [
            'id' => $i->id, 'service_type' => $i->service_type, 'description' => $i->description, 'supplier_id' => $i->supplier_id,
            'start_date' => $i->start_date?->format('Y-m-d'), 'end_date' => $i->end_date?->format('Y-m-d'), 'rooms' => $i->rooms,
            'quantity' => $i->quantity, 'unit_cost' => $i->unit_cost, 'unit_price' => $i->unit_price,
        ])->all()]))->assertSessionHasErrors();
        $this->assertSame((int) $customer->id, (int) $b->fresh()->customer_id);
    }

    // ------------------------------------------------------------------ R2: accounts

    public function test_accounts_used_by_tourism_can_not_be_deleted(): void
    {
        [$b, $customer, $a] = $this->family();   // a draft: no ledger rows yet
        $this->assertSame(0, AccountStatement::where('supp_client_id', $a->id)->count());
        $this->assertNotEmpty($a->deletionBlockers());
        $this->assertNotEmpty($customer->deletionBlockers());
        $this->actingAs($this->admin)->post(route('site.suppliers_delete', $a->id))->assertSessionHasErrors('delete');
        $this->actingAs($this->admin)->post(route('site.suppliers_delete', $customer->id))->assertSessionHasErrors('delete');
        $this->assertNotNull(Supplier::find($a->id));
        $this->assertNotNull(Supplier::find($customer->id));

        $programSupplier = $this->account();
        TourismProgram::create(['name' => 'TEST P', 'status' => 1])->items()->create(['service_type' => 'other', 'description' => 'x',
            'supplier_id' => $programSupplier->id, 'pricing' => 'per_unit', 'unit_cost' => 1, 'unit_price' => 1]);
        $this->assertNotEmpty($programSupplier->deletionBlockers());

        $rows = SupplierDirectory::rows()->keyBy(fn ($r) => $r->account->id);
        $this->assertSame(['supplier', 'customer'], [$rows[$a->id]->role, $rows[$customer->id]->role]);

        // an unused account is still deletable
        $free = $this->account();
        $this->assertSame([], $free->deletionBlockers());
    }

    // ------------------------------------------------------------------ R1: statements and movements

    public function test_account_statement_and_movements_show_tourism_rows(): void
    {
        [$b, $customer, $a] = $this->confirmed();

        $m = AccountMovements::forAccount($customer->id);
        $this->assertArrayHasKey('tourism', $m['kinds']);
        $this->assertEquals(11100, $m['kinds']['tourism']->debit);
        $this->assertEqualsWithDelta($m['debit'], array_sum(array_map(fn ($k) => $k->debit, $m['kinds'])), 0.001, 'the kinds add up to the statement');
        $latest = $m['latest'][0];
        $this->assertSame(['tourism', 'حجز سياحة داخلية', $b->id, null], [$latest->kind, $latest->label, $latest->booking, $latest->invoice]);
        $this->actingAs($this->admin)->get(route('site.suppliers_overview', $customer->id))->assertOk()->assertSee(route('site.tourism_bookings_show', $b->id), false);

        // the statement: tourism label, one line per service, never a ticket sale / flight block
        $html = $this->actingAs($this->admin)->post(route('site.accounts_statement_search'), ['invoice_beneficiaries' => $a->id])->assertOk()->getContent();
        $this->assertStringContainsString('حجز سياحة داخلية', $html);
        $this->assertStringContainsString('Village X', $html);
        $this->assertStringNotContainsString('بيع تذكرة', $html);
        $this->assertStringContainsString(self::TODAY, $html);

        $print = $this->actingAs($this->admin)->get(route('site.accounts_statement_print', $customer->id))->assertOk()->getContent();
        $this->assertStringContainsString('حجز سياحة داخلية', $print);
        $this->assertStringNotContainsString('خط الطيران', $print);
    }

    public function test_every_tourism_screen_renders_with_the_shared_action_styles(): void
    {
        [$draft] = $this->family();
        [$b, , $a] = $this->confirmed();
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_receive', $b->id), $this->money())->assertSessionHasNoErrors();
        $program = TourismProgram::create(['name' => 'TEST P', 'status' => 1]);
        $program->items()->create(['service_type' => 'hotel', 'description' => 'x', 'supplier_id' => $a->id, 'nights' => 2, 'pricing' => 'per_unit', 'unit_cost' => 1, 'unit_price' => 2]);

        foreach ([
            route('site.tourism_bookings'), route('site.tourism_bookings_create'), route('site.tourism_bookings_create', ['program_id' => $program->id]),
            route('site.tourism_bookings_show', $draft->id), route('site.tourism_bookings_edit', $draft->id),
            route('site.tourism_bookings_show', $b->id), route('site.tourism_bookings_edit', $b->id),
            route('site.tourism_programs'), route('site.tourism_programs_create'), route('site.tourism_programs_edit', $program->id),
            route('site.tourism_report'), route('site.tourism_operations', 'hotel'), route('site.tourism_operations', 'transport'),
        ] as $url) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('ey-action-group', $html, $url);
        }
        // row actions: one compact line (never a flex table cell); triggers vs solid submits
        $show = $this->actingAs($this->admin)->get(route('site.tourism_bookings_show', $b->id))->getContent();
        $this->assertStringContainsString('ey-row-actions', $show);
        $this->assertStringNotContainsString('<td class="d-flex', $this->actingAs($this->admin)->get(route('site.tourism_programs'))->getContent());
    }

    public function test_tourism_report_and_operations_lists(): void
    {
        [$b, $customer, $a] = $this->confirmed();
        $res = $this->actingAs($this->admin)->get(route('site.tourism_report', ['date_from' => self::TODAY, 'date_to' => self::TODAY, 'group_by' => 'service_type']))->assertOk();
        $report = $res->viewData('report');
        $row = collect($report->rows())->firstWhere('booking_no', $b->booking_no);
        $this->assertSame([11100.0, 8600.0, 2500.0, 11100.0, 8600.0], [$row['sale'], $row['cost'], $row['profit'], $row['receivable'], $row['payable']]);
        $groups = $report->groups();
        $this->assertSame([7800.0, 6000.0], [$groups['hotel']['sale'], $groups['hotel']['cost']]);
        $this->actingAs($this->admin)->get(route('site.tourism_report', ['group_by' => 'supplier', 'customer_id' => $customer->id]))->assertOk()->assertSee($a->name);
        $this->actingAs($this->admin)->get(route('site.tourism_report_print', ['customer_id' => $customer->id]))->assertOk()->assertSee($b->booking_no);
        $this->actingAs($this->admin)->get(route('site.tourism_report_excel', ['customer_id' => $customer->id]))->assertOk();

        $this->actingAs($this->admin)->get(route('site.tourism_operations', ['type' => 'hotel', 'supplier_id' => $a->id]))->assertOk()->assertSee($b->booking_no)->assertSee('TEST DAD');
        $this->actingAs($this->admin)->get(route('site.tourism_operations', ['type' => 'transport', 'print' => 1]))->assertOk();
        foreach (['confirmation', 'passengers'] as $doc) {
            $this->actingAs($this->admin)->get(route('site.tourism_bookings_print', [$b->id, $doc]))->assertOk()->assertSee($b->booking_no);
        }
        $this->actingAs($this->admin)->get(route('site.tourism_bookings_print', [$b->id, 'service_order', 'supplier_id' => $a->id]))->assertOk()->assertSee('Village X');

        // an employee's report shows their own bookings only
        $this->actingAs($this->employee)->get(route('site.tourism_report'))->assertOk()->assertDontSee($b->booking_no);
    }
}
