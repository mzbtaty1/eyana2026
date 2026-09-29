<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{CommissionTierTable, Supplier, TourismBooking, User};
use App\Services\EmployeeCommissionReport;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{DB, Hash};
use Tests\TestCase;

/**
 * Internal tourism profit in the employee commission (Step D engine, unchanged rules): the
 * booking's owner (created_by) gets 100% of its ledger movements, each on its own date
 * (confirmation, adjustment, cancellation); flight results are unchanged. Test data dated
 * 2031, rolled back.
 */
class TourismCommissionTest extends TestCase
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

    private function emp(array $attrs = []): User
    {
        return User::create(array_merge(['name' => 'TEST TC EMP ' . uniqid(), 'email' => 'tc-' . uniqid() . '@test.local',
            'password' => Hash::make('secret-123'), 'user_id' => 'FX_TEST', 'status' => '1', 'account_type' => 1, 'commission' => '10'], $attrs))->fresh();
    }

    private function account(): Supplier
    {
        return Supplier::create(['name' => 'TEST TC ' . uniqid(), 'phone_1' => '0', 'phone_2' => '0', 'type' => '2', 'passport_id' => '',
            'passport_expiration_date' => '', 'email' => '', 'address' => '', 'debit_opening_balance' => '0', 'opening_credit_balance' => '0',
            'status' => 1, 'acc_type' => 2, 'limit_balance' => '0', 'in_index' => 0, 'in_stat' => 1]);
    }

    /** A confirmed booking owned by $owner: one transport service, $sale / $cost. */
    private function booking(User $owner, float $sale, float $cost, ?Supplier $supplier = null): TourismBooking
    {
        $customer = $this->account();
        $supplier ??= $this->account();
        $max = (int) TourismBooking::max('id');
        $this->actingAs($owner)->post(route('site.tourism_bookings_store'), ['customer_id' => $customer->id, 'adults' => 2, 'children' => 0,
            'items' => [['service_type' => 'transport', 'description' => 'TEST BUS', 'supplier_id' => $supplier->id, 'quantity' => 1,
                'unit_cost' => $cost, 'unit_price' => $sale]]])->assertSessionHasNoErrors();
        $b = TourismBooking::where('id', '>', $max)->firstOrFail();
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_confirm', $b->id))->assertSessionHasNoErrors();
        return $b->fresh();
    }

    private function one(User $e, string $from, string $to): array
    {
        return (new EmployeeCommissionReport($this->admin, $from, $to, $e->id))->results()[0];
    }

    private function flight(User $owner, string $date, float $sale, float $cost): void
    {
        $sys = 'TESTTC-' . str_replace('.', '', uniqid('', true));
        DB::table('invoices')->insert(['es_id' => 'FLY-A-TC' . substr($sys, -8), 'ticket_system_id' => $sys, 'invoice_date' => $date, 'invoice_travel_date' => $date,
            'invoice_airline' => 'TEST-AIR', 'from_location' => 'CAI', 'to_location' => 'DXB', 'invoice_section' => 1, 'invoice_currency' => 'جنية مصري',
            'invoice_ticket_file' => '', 'invoice_beneficiaries' => 990000002, 'invoice_create_by' => (string) $owner->id, 'invoice_shared' => 0]);
        DB::table('ticket_users')->insert(['ticket_system_id' => $sys, 'client_name' => 'TEST PAX', 'client_type' => 3, 'client_net_pice' => $cost,
            'client_bought_price' => $sale, 'client_booking_id' => 'PNR', 'client_ticket_id' => 'TKT', 'crt_at' => $date]);
        DB::table('ticket_vendors')->insert(['ticket_system_id' => $sys, 'vendor_id' => 990000001]);
    }

    public function test_tourism_profit_counts_for_the_owner_with_the_fixed_rate(): void
    {
        $e = $this->emp(['commission' => '10']);
        $this->flight($e, '2031-06-05', 3000, 2000);                    // flight profit 1000
        $this->booking($e, 12000, 9500);                                // tourism profit 2500, confirmed 2031-06-15
        $r = $this->one($e, '2031-06-01', '2031-06-30');
        $this->assertSame([15000.0, 11500.0, 3500.0, 350.0], [$r['sales'], $r['cost'], $r['profit'], $r['commission']]);
        $this->assertSame([1, 12000.0, 9500.0, 1], [$r['tourism_bookings'], $r['tourism_sales'], $r['tourism_cost'], $r['invoices']]);

        // someone else's report is not touched; the admin who confirmed gets nothing
        $other = $this->emp();
        $this->assertSame(0.0, $this->one($other, '2031-06-01', '2031-06-30')['profit']);
        $this->assertNotContains((int) $this->admin->id, array_column((new EmployeeCommissionReport($this->admin, '2031-06-01', '2031-06-30'))->results(), 'employee_id'));
    }

    public function test_tiered_commission_uses_the_total_with_tourism(): void
    {
        $t = CommissionTierTable::create(['name' => 'TEST TC TIERS ' . uniqid(), 'status' => 1]);
        $t->tiers()->createMany([
            ['from_amount' => 1, 'to_amount' => 10000, 'rate' => 10, 'sort_order' => 1],
            ['from_amount' => 10001, 'to_amount' => null, 'rate' => 20, 'sort_order' => 2],
        ]);
        $e = $this->emp(['commission_method' => 'tiered', 'commission_tier_table_id' => $t->id]);
        $this->flight($e, '2031-06-05', 10000, 2000);                   // 8000
        $this->booking($e, 10000, 5000);                                // + 5000 = 13000 -> 20%
        $r = $this->one($e, '2031-06-01', '2031-06-30');
        $this->assertSame([13000.0, 20.0, 2600.0], [$r['profit'], (float) $r['rate'], $r['commission']]);
    }

    public function test_adjustments_and_cancellations_count_on_their_own_dates(): void
    {
        $e = $this->emp(['commission' => '10']);
        $b = $this->booking($e, 10000, 8000);                           // June: +2000
        $item = $b->items()->first();

        $this->travelTo('2031-07-10 10:00:00');                         // July: price +1000
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_update', $b->id), ['customer_id' => $b->customer_id, 'adults' => 2, 'children' => 0,
            'items' => [['id' => $item->id, 'service_type' => 'transport', 'description' => 'TEST BUS', 'supplier_id' => $item->supplier_id,
                'quantity' => 1, 'unit_cost' => 8000, 'unit_price' => 11000]]])->assertSessionHasNoErrors();

        $this->travelTo('2031-08-10 10:00:00');                         // August: cancelled, 500 kept from the customer, 300 still owed
        $this->actingAs($this->admin)->post(route('site.tourism_bookings_cancel', $b->id), ['penalties' => [$item->id => ['cost' => 300, 'fee' => 500]]])
            ->assertSessionHasNoErrors();

        $this->assertSame([2000.0, 200.0], [$this->one($e, '2031-06-01', '2031-06-30')['profit'], $this->one($e, '2031-06-01', '2031-06-30')['commission']]);
        $this->assertSame(1000.0, $this->one($e, '2031-07-01', '2031-07-31')['profit']);
        $this->assertSame(-2800.0, $this->one($e, '2031-08-01', '2031-08-31')['profit']);   // sale -10500, cost -7700
        $this->assertSame(0.0, $this->one($e, '2031-08-01', '2031-08-31')['commission']);
        $this->assertSame(200.0, $this->one($e, '2031-06-01', '2031-08-31')['profit'], 'over the whole time: the penalties 500 - 300');
    }

    public function test_flight_results_are_unchanged_by_tourism_outside_the_period(): void
    {
        $e = $this->emp(['commission' => '10']);
        $this->flight($e, '2031-05-05', 5000, 3000);
        $before = $this->one($e, '2031-05-01', '2031-05-31');
        $this->booking($e, 9000, 1000);                                 // June
        $after = $this->one($e, '2031-05-01', '2031-05-31');
        $this->assertSame($before, $after);
        $this->assertSame([2000.0, 200.0, 0], [$after['profit'], $after['commission'], $after['tourism_bookings']]);
    }
}
