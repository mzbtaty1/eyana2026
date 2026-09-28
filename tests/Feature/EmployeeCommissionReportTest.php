<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{CommissionTierTable, Invoice, Supplier, TicketUser, User};
use App\Services\{CounterPayments, EmployeeCommissionReport, InvoiceFullReport};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{DB, Hash};
use Tests\TestCase;

/**
 * Employees step D: the employee commission report -- profit per employee for a period from
 * InvoiceFullReport (shared invoices split by participation), and the commission from each
 * employee's settings (fixed % or one tier of a tier table). Also the flight-edit ownership fix.
 * Dev DB; the test data is dated 2031 (no real operation there) and everything is rolled back.
 */
class EmployeeCommissionReportTest extends TestCase
{
    use DatabaseTransactions;

    const FROM = '2031-03-01';
    const TO = '2031-03-31';
    const VENDOR = 990000001;   // refund ledger accounts (never real supplier ids)
    const CLIENT = 990000002;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->admin = User::where('account_type', 2)->where('status', 1)->orderBy('id')->firstOrFail();
    }

    // ------------------------------------------------------------------ fixtures

    private function emp(array $attrs = []): User
    {
        return User::create(array_merge(['name' => 'TEST EMP ' . uniqid(), 'email' => 'emp-' . uniqid() . '@test.local',
            'password' => Hash::make('secret-123'), 'user_id' => 'FX_TEST', 'status' => '1', 'account_type' => 1, 'commission' => '10'], $attrs))->fresh();
    }

    private function tiered(CommissionTierTable $table, array $attrs = []): User
    {
        return $this->emp(['commission_method' => 'tiered', 'commission_tier_table_id' => $table->id] + $attrs);
    }

    /** 1-10,000 = 10% / 10,001-50,000 = 20% / 50,001-100,000 = 25%. */
    private function standardTable(int $status = 1): CommissionTierTable
    {
        $t = CommissionTierTable::create(['name' => 'TEST STANDARD ' . uniqid(), 'status' => $status]);
        $t->tiers()->createMany([
            ['from_amount' => 1, 'to_amount' => 10000, 'rate' => 10, 'sort_order' => 1],
            ['from_amount' => 10001, 'to_amount' => 50000, 'rate' => 20, 'sort_order' => 2],
            ['from_amount' => 50001, 'to_amount' => 100000, 'rate' => 25, 'sort_order' => 3],
        ]);
        return $t;
    }

    /**
     * An invoice straight in the tables the report reads. $o: prefix (FLY-A1 sale, FLY-RS
     * re-issue, FLY-RD refund), shared [account 1, rate 1, account 2, rate 2], pax [[sale, cost], ..],
     * refund [supplier return, client refund] (booked as the refund's ledger rows).
     */
    private function invoice($owner, string $date, float $sale, float $cost, array $o = []): int
    {
        $sys = 'TESTD-' . str_replace('.', '', uniqid('', true));
        $shared = $o['shared'] ?? null;
        $es = ($o['prefix'] ?? 'FLY-A1') . '-T' . substr($sys, -10);
        $id = DB::table('invoices')->insertGetId([
            'es_id' => $es, 'ticket_system_id' => $sys, 'invoice_date' => $date, 'invoice_travel_date' => $date,
            'invoice_airline' => 'TEST-AIR', 'from_location' => 'CAI', 'to_location' => 'DXB', 'invoice_section' => 1,
            'invoice_currency' => 'جنية مصري', 'invoice_ticket_file' => '', 'invoice_beneficiaries' => self::CLIENT,
            'invoice_create_by' => (string) ($owner instanceof User ? $owner->id : $owner),
            'invoice_shared' => $shared ? 1 : 0,
            'invoice_account_1' => $shared ? (string) $shared[0] : null, 'invoice_account_1_comm' => $shared ? (string) $shared[1] : null,
            'invoice_account_2' => $shared ? (string) $shared[2] : null, 'invoice_account_2_comm' => $shared ? (string) $shared[3] : null,
        ]);
        foreach ($o['pax'] ?? [[$sale, $cost]] as $i => [$s, $c]) {
            DB::table('ticket_users')->insert(['ticket_system_id' => $sys, 'client_name' => 'TEST PAX ' . $i, 'client_type' => 3,
                'client_net_pice' => $c, 'client_bought_price' => $s, 'client_booking_id' => 'PNR', 'client_ticket_id' => 'TKT' . $i, 'crt_at' => $date]);
        }
        DB::table('ticket_vendors')->insert(['ticket_system_id' => $sys, 'vendor_id' => self::VENDOR]);
        if (isset($o['refund'])) {
            [$supplierReturn, $clientRefund] = $o['refund'];
            foreach ([[self::VENDOR, $supplierReturn, 0], [self::CLIENT, 0, $clientRefund]] as [$acc, $debit, $credit]) {
                DB::table('account_statements')->insert(['supp_client_id' => $acc, 'invoice_type' => 1, 'es_id' => $es, 'invoice_date' => $date,
                    'transaction_txt' => 'TEST REFUND', 'transaction_type' => 1, 'debit_balance' => $debit, 'credit_balance' => $credit]);
            }
        }
        return $id;
    }

    /** Results keyed by employee id. */
    private function report(?User $viewer = null, ?User $employee = null, string $from = self::FROM, string $to = self::TO): array
    {
        $r = new EmployeeCommissionReport($viewer ?? $this->admin, $from, $to, $employee?->id);
        return collect($r->results())->keyBy('employee_id')->all();
    }

    private function one(User $employee, string $from = self::FROM, string $to = self::TO): array
    {
        return $this->report(null, $employee, $from, $to)[$employee->id];
    }

    private function assertAmounts(array $expected, array $row): void
    {
        foreach ($expected as $k => $v) {
            $this->assertEqualsWithDelta($v, $row[$k], 0.001, $k);
        }
    }

    // ------------------------------------------------------------------ fixed

    public function test_fixed_10_percent(): void
    {
        $e = $this->emp(['commission' => '10']);
        $this->invoice($e, '2031-03-10', 40000, 5000);
        $this->invoice($e, '2031-03-20', 20000, 5000);   // commission on the period TOTAL, not per invoice
        $r = $this->one($e);
        $this->assertAmounts(['sales' => 60000, 'cost' => 10000, 'refund_net' => 0, 'profit' => 50000, 'rate' => 10,
            'commission' => 5000, 'net_company_profit' => 45000], $r);
        $this->assertSame(['fixed', null, null, 2], [$r['method'], $r['tier_table'], $r['tier'], $r['invoices']]);
    }

    public function test_fixed_decimal_12_5_percent_is_not_truncated(): void
    {
        $e = $this->emp(['commission' => '12.5']);
        $this->invoice($e, '2031-03-10', 15000, 5000);
        $this->assertAmounts(['profit' => 10000, 'rate' => 12.5, 'commission' => 1250, 'net_company_profit' => 8750], $this->one($e));
    }

    public function test_legacy_10_percent_text_is_read_as_10(): void
    {
        $e = $this->emp(['commission' => '10%']);
        $this->invoice($e, '2031-03-10', 55000, 5000);
        $this->assertAmounts(['profit' => 50000, 'rate' => 10, 'commission' => 5000], $this->one($e));
        $this->assertSame('10%', User::find($e->id)->commission, 'stored value untouched');
    }

    public function test_zero_profit_gives_no_commission(): void
    {
        $fixed = $this->emp();
        $tiered = $this->tiered($this->standardTable());
        foreach ([$fixed, $tiered] as $e) {
            $this->invoice($e, '2031-03-10', 5000, 5000);
            $this->assertAmounts(['profit' => 0, 'commission' => 0, 'net_company_profit' => 0], $this->one($e));
        }
        $this->assertNull($this->one($tiered)['tier']);
    }

    public function test_negative_profit_gives_no_commission(): void
    {
        $fixed = $this->emp();
        $tiered = $this->tiered($this->standardTable());
        foreach ([$fixed, $tiered] as $e) {
            $this->invoice($e, '2031-03-10', 1000, 3000);
            $this->assertAmounts(['profit' => -2000, 'commission' => 0, 'net_company_profit' => -2000], $this->one($e));
        }
    }

    // ------------------------------------------------------------------ tiered (one tier from the total profit)

    /** A tiered employee whose period profit is $profit gets tier $n at $rate, applied to the whole profit. */
    private function assertTier(float $profit, ?int $n, float $rate): void
    {
        $table = $this->standardTable();
        $e = $this->tiered($table);
        $this->invoice($e, '2031-03-05', $profit / 2 + 1000, 1000);
        $this->invoice($e, '2031-03-25', $profit / 2 + 500, 500);
        $r = $this->one($e);
        $this->assertEqualsWithDelta($profit, $r['profit'], 0.001);
        $this->assertSame([$n, $rate], [$r['tier']['n'] ?? null, $r['rate']], "profit $profit");
        $this->assertEqualsWithDelta(round($profit * $rate / 100, 2), $r['commission'], 0.001);
        $this->assertSame(['tiered', $table->name, true], [$r['method'], $r['tier_table'], $r['tier_table_active']]);
    }

    public function test_tier_boundary_10000_is_the_first_tier(): void
    {
        $this->assertTier(10000, 1, 10.0);     // 1,000
    }

    public function test_tier_boundary_10001_is_the_second_tier(): void
    {
        $this->assertTier(10001, 2, 20.0);     // 2,000.20
        $this->assertTier(10000.5, 2, 20.0);   // decimals between 10,000 and 10,001: second tier (step B rule)
    }

    public function test_tier_boundary_50000_is_the_second_tier(): void
    {
        $this->assertTier(50000, 2, 20.0);     // 10,000
    }

    public function test_tier_boundary_50001_is_the_third_tier(): void
    {
        $this->assertTier(50001, 3, 25.0);     // 12,500.25
    }

    public function test_profit_above_the_final_tier_uses_the_final_rate(): void
    {
        $this->assertTier(150000, 3, 25.0);    // 37,500
    }

    public function test_profit_below_the_first_tier_gives_no_commission(): void
    {
        $this->assertTier(0.5, null, 0.0);     // below the first lower limit (1)
        $table = CommissionTierTable::create(['name' => 'TEST FROM 5000 ' . uniqid(), 'status' => 1]);
        $table->tiers()->create(['from_amount' => 5000, 'to_amount' => null, 'rate' => 15, 'sort_order' => 1]);
        $e = $this->tiered($table);
        $this->invoice($e, '2031-03-05', 5999, 1000);
        $this->assertAmounts(['profit' => 4999, 'rate' => 0, 'commission' => 0], $this->one($e));
    }

    public function test_a_deactivated_assigned_table_is_still_applied(): void
    {
        $table = $this->standardTable(0);
        $e = $this->tiered($table);
        $this->invoice($e, '2031-03-05', 21000, 1000);
        $r = $this->one($e);
        $this->assertAmounts(['profit' => 20000, 'rate' => 20, 'commission' => 4000], $r);
        $this->assertFalse($r['tier_table_active']);
    }

    // ------------------------------------------------------------------ shared invoices (split by participation)

    public function test_shared_7_3_is_split_70_30_before_the_commission(): void
    {
        [$a, $b] = [$this->emp(['commission' => '10']), $this->emp(['commission' => '20'])];
        $this->invoice($this->admin, '2031-03-10', 110000, 10000, ['shared' => [$a->id, 7, $b->id, 3]]);
        $r = $this->report();
        $this->assertAmounts(['sales' => 77000, 'cost' => 7000, 'profit' => 70000, 'rate' => 10, 'commission' => 7000, 'net_company_profit' => 63000], $r[$a->id]);
        $this->assertAmounts(['sales' => 33000, 'cost' => 3000, 'profit' => 30000, 'rate' => 20, 'commission' => 6000, 'net_company_profit' => 24000], $r[$b->id]);
        $this->assertArrayNotHasKey($this->admin->id, $r, 'the creator is not a participant');
        // one employee's own report: only their part
        $this->assertAmounts(['profit' => 30000], $this->one($b));
    }

    public function test_shared_5_5_and_50_50_and_zero_rates_are_split_equally(): void
    {
        foreach ([[5, 5], [50, 50], [0, 0], ['', '']] as [$r1, $r2]) {
            [$a, $b] = [$this->emp(), $this->emp()];
            $this->invoice($a, '2031-03-10', 30000, 10000, ['shared' => [$a->id, $r1, $b->id, $r2]]);
            $r = $this->report();
            foreach ([$a, $b] as $e) {
                $this->assertAmounts(['sales' => 15000, 'cost' => 5000, 'profit' => 10000, 'commission' => 1000], $r[$e->id]);
            }
        }
    }

    public function test_shared_invoice_with_the_same_employee_on_both_accounts_is_100_percent(): void
    {
        $a = $this->emp();
        $this->invoice($a, '2031-03-10', 30000, 10000, ['shared' => [$a->id, 7, $a->id, 3]]);
        $this->assertAmounts(['sales' => 30000, 'cost' => 10000, 'profit' => 20000, 'commission' => 2000], $this->one($a));
    }

    // ------------------------------------------------------------------ refund / re-issue

    public function test_refund_counts_for_the_employee_who_made_it_on_its_own_date(): void
    {
        [$seller, $refunder] = [$this->emp(), $this->emp()];
        $this->invoice($seller, '2031-02-20', 12000, 10000);                                               // original sale: February
        $this->invoice($refunder, '2031-03-10', 0, 0, ['prefix' => 'FLY-RD', 'pax' => [[1000, 1000]], 'refund' => [800, 1000]]);
        $this->invoice($refunder, '2031-03-12', 5000, 3000);

        $r = $this->one($refunder);
        $this->assertAmounts(['sales' => 5000, 'cost' => 3000, 'refund_net' => -200, 'profit' => 1800, 'commission' => 180], $r);
        $this->assertArrayNotHasKey($seller->id, $this->report(), 'no operation of the seller in March');
        $this->assertAmounts(['profit' => 2000, 'refund_net' => 0], $this->one($seller, '2031-02-01', '2031-02-28'));
    }

    public function test_reissue_is_its_own_sale_for_the_employee_who_made_it(): void
    {
        [$seller, $reissuer] = [$this->emp(), $this->emp()];
        $this->invoice($seller, '2031-02-20', 12000, 10000);
        $this->invoice($reissuer, '2031-03-15', 14000, 11000, ['prefix' => 'FLY-RS']);
        $this->assertAmounts(['sales' => 14000, 'cost' => 11000, 'profit' => 3000, 'commission' => 300], $this->one($reissuer));
        $this->assertArrayNotHasKey($seller->id, $this->report());
    }

    public function test_shared_refund_and_reissue_keep_the_shared_split(): void
    {
        [$a, $b, $performer] = [$this->emp(), $this->emp(), $this->emp()];
        // as SharedInvoices stores them: the original's two accounts at 5/5, made by another employee
        $this->invoice($performer, '2031-03-10', 20000, 12000, ['prefix' => 'FLY-RS', 'shared' => [$a->id, 5, $b->id, 5]]);
        $this->invoice($performer, '2031-03-11', 0, 0, ['prefix' => 'FLY-RD', 'shared' => [$a->id, 5, $b->id, 5], 'pax' => [[2000, 2000]], 'refund' => [1500, 2000]]);
        $r = $this->report();
        foreach ([$a, $b] as $e) {
            $this->assertAmounts(['sales' => 10000, 'cost' => 6000, 'refund_net' => -250, 'profit' => 3750, 'commission' => 375], $r[$e->id]);
        }
        $this->assertArrayNotHasKey($performer->id, $r);
    }

    // ------------------------------------------------------------------ who is included / who can see it

    public function test_an_admin_who_sells_is_included(): void
    {
        $seller = $this->emp(['account_type' => 2, 'commission' => '5']);
        $this->invoice($seller, '2031-03-10', 30000, 10000);
        $this->assertAmounts(['profit' => 20000, 'rate' => 5, 'commission' => 1000], $this->report()[$seller->id]);
    }

    public function test_an_employee_only_ever_sees_their_own_report(): void
    {
        [$me, $other] = [$this->emp(), $this->emp()];
        $this->invoice($me, '2031-03-10', 3000, 1000);
        $this->invoice($other, '2031-03-10', 9000, 1000);
        $this->invoice($other, '2031-03-11', 9000, 1000, ['shared' => [$me->id, 7, $other->id, 3]]);

        // service: a requested employee is ignored for an employee
        $r = (new EmployeeCommissionReport($me, self::FROM, self::TO, $other->id))->results();
        $this->assertSame([$me->id], array_column($r, 'employee_id'));
        $this->assertEqualsWithDelta(2000 + 5600, $r[0]['profit'], 0.001);

        // page: a forged employee_id shows the employee's own row only
        $this->actingAs($me)->get(route('site.employee_commission_report', ['date_from' => self::FROM, 'date_to' => self::TO, 'employee_id' => $other->id]))
            ->assertOk()->assertSee($me->name)->assertDontSee($other->name)->assertDontSee('name="employee_id"', false)->assertSee('7,600.00');
        $this->get(route('site.index'))->assertSee(route('site.employee_commission_report'), false);  // menu entry

        // an admin sees any employee, or all of them
        $this->actingAs($this->admin)->get(route('site.employee_commission_report', ['date_from' => self::FROM, 'date_to' => self::TO]))
            ->assertOk()->assertSee($me->name)->assertSee($other->name)->assertSee('صافي ربح الشركة بعد العمولة');
        $this->get(route('site.employee_commission_report', ['date_from' => self::FROM, 'date_to' => self::TO, 'employee_id' => $other->id]))
            ->assertOk()->assertSee($other->name)->assertDontSee('<td>' . $me->name . '</td>', false);

        auth()->logout();
        $this->get(route('site.employee_commission_report'))->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------ dates

    public function test_the_period_includes_both_boundary_dates(): void
    {
        $e = $this->emp();
        foreach (['2031-02-28' => 1000, '2031-03-01' => 2000, '2031-03-31' => 4000, '2031-04-01' => 8000] as $date => $profit) {
            $this->invoice($e, $date, $profit + 100, 100);
        }
        $this->assertAmounts(['profit' => 6000, 'invoices' => 2], $this->one($e));
        $this->assertAmounts(['profit' => 2000], $this->one($e, '2031-03-01', '2031-03-01'));
    }

    public function test_invalid_or_reversed_dates_are_rejected(): void
    {
        $this->actingAs($this->admin);
        $url = fn (array $q) => route('site.employee_commission_report', $q);
        $this->get($url(['date_from' => '2031-04-01', 'date_to' => '2031-03-01']))->assertSessionHasErrors('date_to');
        $this->get($url(['date_from' => 'abc', 'date_to' => '2031-03-01']))->assertSessionHasErrors('date_from');
        $this->get($url(['date_from' => '2031-02-30', 'date_to' => '2031-03-01']))->assertSessionHasErrors('date_from');
        $this->get($url(['date_from' => '2031-03-01']))->assertSessionHasErrors('date_to');
        $this->get($url(['date_from' => self::FROM, 'date_to' => self::TO, 'employee_id' => 999999999]))->assertSessionHasErrors('employee_id');
        $this->get($url([]))->assertOk()->assertSessionHasNoErrors()->assertSee('عرض التقرير');   // the empty form
    }

    // ------------------------------------------------------------------ consistency / efficiency / safety

    public function test_totals_match_the_detailed_invoice_report(): void
    {
        $e = $this->emp();
        $this->invoice($e, '2031-03-02', 12000, 10000, ['pax' => [[5000, 4000], [7000, 6000]]]);
        $this->invoice($e, '2031-03-09', 9000, 7000, ['prefix' => 'FLY-RS']);
        $this->invoice($e, '2031-03-20', 0, 0, ['prefix' => 'FLY-RD', 'pax' => [[500, 500]], 'refund' => [300, 450]]);
        $full = (new InvoiceFullReport(['date_from' => self::FROM, 'date_to' => self::TO, 'employee_id' => $e->id], $this->admin))->summary();
        $r = $this->one($e);
        $this->assertEqualsWithDelta($full['sale'], $r['sales'], 0.001);
        $this->assertEqualsWithDelta($full['purchase'], $r['cost'], 0.001);
        $this->assertEqualsWithDelta($full['refund_net'], $r['refund_net'], 0.001);
        $this->assertEqualsWithDelta($full['net_profit'], $r['profit'], 0.001);
    }

    public function test_the_number_of_queries_does_not_grow_with_invoices_or_employees(): void
    {
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            (new EmployeeCommissionReport($this->admin, self::FROM, self::TO))->results();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();
            return $n;
        };
        $table = $this->standardTable();
        $this->invoice($this->tiered($table), '2031-03-10', 2000, 1000);
        $this->invoice($this->emp(), '2031-03-10', 2000, 1000, ['prefix' => 'FLY-RD', 'refund' => [10, 20]]);
        $few = $count();
        for ($i = 0; $i < 25; $i++) {
            $this->invoice($i % 2 ? $this->tiered($this->standardTable()) : $this->emp(), '2031-03-1' . ($i % 9), 2000, 1000, $i % 5 ? [] : ['prefix' => 'FLY-RD', 'refund' => [10, 20]]);
        }
        $this->assertSame($few, $count(), 'same number of queries for 2 and 27 invoices / employees');
    }

    public function test_running_the_report_changes_no_data(): void
    {
        $tables = ['invoices', 'ticket_users', 'ticket_vendors', 'account_statements', 'bonds', 'users', 'commission_tier_tables', 'commission_tiers'];
        $snapshot = fn () => collect($tables)->mapWithKeys(fn ($t) => [$t => [DB::select("CHECKSUM TABLE `$t`")[0]->Checksum, DB::table($t)->count()]])->all();
        $before = $snapshot();

        // the real operations of this year, every employee, through the service and the page
        (new EmployeeCommissionReport($this->admin, '2025-01-01', '2026-12-31'))->results();
        $this->actingAs($this->admin)->get(route('site.employee_commission_report', ['date_from' => '2025-01-01', 'date_to' => '2026-12-31']))->assertOk();
        $employee = User::where('account_type', 1)->where('status', 1)->orderBy('id')->firstOrFail();
        $this->actingAs($employee)->get(route('site.employee_commission_report', ['date_from' => '2025-01-01', 'date_to' => '2026-12-31']))->assertOk();

        $this->assertSame($before, $snapshot());
    }

    // ------------------------------------------------------------------ flight edit ownership (fix)

    private function flightPayload(array $over = []): array
    {
        [$customer, $vendor] = Supplier::where('status', 1)->where('acc_type', '!=', 3)->whereNotIn('id', CounterPayments::counterIds())->orderBy('id')->limit(2)->get()->all();
        return array_merge([
            'invoice_date' => '2031-03-10', 'invoice_travel_date' => '2031-03-20', 'return_date' => null,
            'from_location' => 'CAI', 'to_location' => 'DXB', 'invoice_airline' => 'TEST-AIR',
            'invoice_beneficiaries' => $customer->id, 'vendor_id' => $vendor->id,
            'invoice_section' => 1, 'invoice_comments' => null, 'invoice_currency' => 'جنية مصري', 'invoice_draft' => null,
            'ticket_info' => [['name' => 'TEST PAX', 'client_type' => 3, 'net_price' => 1000, 'bought_price' => 1200,
                'book_id' => 'PNR1', 'tikcet_id' => 'TKT1', 'client_phone' => null]],
        ], $over);
    }

    private function editPayload(Invoice $inv, array $over = []): array
    {
        $pax = TicketUser::where('ticket_system_id', $inv->ticket_system_id)->firstOrFail();
        return array_merge($this->flightPayload(), ['id' => $inv->id, 'ticket_info' => [['id' => $pax->id, 'name' => 'TEST PAX', 'client_type' => 3,
            'net_price' => 1000, 'bought_price' => 1300, 'book_id' => 'PNR1', 'tikcet_id' => 'TKT1', 'client_phone' => null]]], $over);
    }

    public function test_an_employee_editing_a_flight_invoice_does_not_become_its_owner(): void
    {
        [$owner, $editor] = [$this->emp(), $this->emp()];
        $this->actingAs($this->admin)->post(route('site.invoices_save'), $this->flightPayload(['added_user' => $owner->id]))->assertRedirect(route('site.invoices'));
        $inv = Invoice::orderByDesc('id')->firstOrFail();
        $this->assertSame((string) $owner->id, (string) $inv->invoice_create_by);

        // another employee edits it (and even posts themselves as the employee): the owner stays
        $this->actingAs($editor)->post(route('site.invoices_save_update'), $this->editPayload($inv, ['added_user' => $editor->id]))
            ->assertRedirect(route('site.invoices_edit', $inv->id));
        $inv->refresh();
        $this->assertSame((string) $owner->id, (string) $inv->invoice_create_by);
        $this->assertSame('1300', (string) TicketUser::where('ticket_system_id', $inv->ticket_system_id)->value('client_bought_price'), 'the edit itself was saved');
        $this->assertArrayNotHasKey($editor->id, $this->report());
        $this->assertAmounts(['sales' => 1300, 'profit' => 300], $this->one($owner));

        // an admin edit keeps the owner preset in the form ...
        $this->actingAs($this->admin)->post(route('site.invoices_save_update'), $this->editPayload($inv, ['added_user' => $owner->id]));
        $this->assertSame((string) $owner->id, (string) $inv->fresh()->invoice_create_by);
        // ... and may reassign it explicitly
        $this->post(route('site.invoices_save_update'), $this->editPayload($inv, ['added_user' => $editor->id]));
        $this->assertSame((string) $editor->id, (string) $inv->fresh()->invoice_create_by);
    }
}
