<?php

namespace Tests\Feature;

use App\Exports\InvoiceFullReportExport;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{CommissionTierTable, User};
use App\Services\{EmployeeCommissionReport, InvoiceFullReport, InvoiceFullReportWithCommission};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{DB, Hash};
use Tests\TestCase;

/**
 * Employees step D: «التقرير التفصيلي للفواتير» shows the commission by the employee
 * commission rules (InvoiceFullReportWithCommission) -- each employee's effective rate for the
 * selected period applied to their share of every ticket row; no commission without a period.
 * Every financial figure stays InvoiceFullReport's. Test data dated 2031, rolled back.
 */
class InvoiceFullReportCommissionTest extends TestCase
{
    use DatabaseTransactions;

    const FROM = '2031-03-01';
    const TO = '2031-03-31';
    const VENDOR = 990000001;
    const CLIENT = 990000002;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->admin = User::where('account_type', 2)->where('status', 1)->orderBy('id')->firstOrFail();
    }

    // ------------------------------------------------------------------ fixtures (as EmployeeCommissionReportTest)

    private function emp(array $attrs = []): User
    {
        return User::create(array_merge(['name' => 'TEST EMP ' . uniqid(), 'email' => 'emp-' . uniqid() . '@test.local',
            'password' => Hash::make('secret-123'), 'user_id' => 'FX_TEST', 'status' => '1', 'account_type' => 1, 'commission' => '10'], $attrs))->fresh();
    }

    /** 1-10,000 = 10% / 10,001-50,000 = 20% / 50,001-100,000 = 25%. */
    private function tiered(): User
    {
        $t = CommissionTierTable::create(['name' => 'TEST STANDARD ' . uniqid(), 'status' => 1]);
        $t->tiers()->createMany([
            ['from_amount' => 1, 'to_amount' => 10000, 'rate' => 10, 'sort_order' => 1],
            ['from_amount' => 10001, 'to_amount' => 50000, 'rate' => 20, 'sort_order' => 2],
            ['from_amount' => 50001, 'to_amount' => 100000, 'rate' => 25, 'sort_order' => 3],
        ]);
        return $this->emp(['commission_method' => 'tiered', 'commission_tier_table_id' => $t->id]);
    }

    private function invoice($owner, string $date, float $sale, float $cost, array $o = []): string
    {
        $sys = 'TESTF-' . str_replace('.', '', uniqid('', true));
        $shared = $o['shared'] ?? null;
        $es = ($o['prefix'] ?? 'FLY-A1') . '-T' . substr($sys, -10);
        DB::table('invoices')->insert([
            'es_id' => $es, 'ticket_system_id' => $sys, 'invoice_date' => $date, 'invoice_travel_date' => $date,
            'invoice_airline' => $o['airline'] ?? 'TEST-AIR', 'from_location' => 'CAI', 'to_location' => 'DXB', 'invoice_section' => 1,
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
            foreach ([[self::VENDOR, $o['refund'][0], 0], [self::CLIENT, 0, $o['refund'][1]]] as [$acc, $debit, $credit]) {
                DB::table('account_statements')->insert(['supp_client_id' => $acc, 'invoice_type' => 1, 'es_id' => $es, 'invoice_date' => $date,
                    'transaction_txt' => 'TEST REFUND', 'transaction_type' => 1, 'debit_balance' => $debit, 'credit_balance' => $credit]);
            }
        }
        return $es;
    }

    private function report(array $filters = [], ?User $viewer = null): InvoiceFullReportWithCommission
    {
        return new InvoiceFullReportWithCommission(array_merge(['date_from' => self::FROM, 'date_to' => self::TO], $filters), $viewer ?? $this->admin);
    }

    /** The report's row of an invoice (first ticket). */
    private function row(InvoiceFullReportWithCommission $report, string $es): array
    {
        return $report->rows()->firstWhere('es_id', $es);
    }

    private function near(float $expected, $actual, string $msg = ''): void
    {
        $this->assertNotNull($actual, $msg);
        $this->assertEqualsWithDelta($expected, $actual, 0.001, $msg);
    }

    // ------------------------------------------------------------------ fixed

    public function test_fixed_10_percent(): void
    {
        $e = $this->emp(['commission' => '10']);
        $a = $this->invoice($e, '2031-03-10', 40000, 5000);
        $b = $this->invoice($e, '2031-03-20', 20000, 5000);
        $r = $this->report(['employee_id' => $e->id]);
        $this->near(3500, $this->row($r, $a)['commission']);
        $this->near(1500, $this->row($r, $b)['commission']);
        $this->assertSame('10%', $this->row($r, $a)['rate_label']);
        $this->near(5000, $r->summary()['commission']);
    }

    public function test_fixed_12_5_percent_keeps_its_decimals(): void
    {
        $e = $this->emp(['commission' => '12.5']);
        $a = $this->invoice($e, '2031-03-10', 15000, 5000);
        $r = $this->report(['employee_id' => $e->id]);
        $this->near(1250, $this->row($r, $a)['commission']);       // the old report: 1,200 (12.5 read as 12)
        $this->assertSame('12.5%', $this->row($r, $a)['rate_label']);
    }

    public function test_legacy_10_percent_text(): void
    {
        $e = $this->emp(['commission' => '10%']);
        $a = $this->invoice($e, '2031-03-10', 15000, 5000);
        $r = $this->report(['employee_id' => $e->id]);
        $this->near(1000, $this->row($r, $a)['commission']);
        $this->assertSame('10%', $this->row($r, $a)['rate_label']);
    }

    // ------------------------------------------------------------------ tiered (one rate from the period total)

    public function test_tiered_rate_comes_from_the_period_total_not_the_row(): void
    {
        $e = $this->tiered();
        $a = $this->invoice($e, '2031-03-05', 6000, 1000);    // 5,000 alone would be the first tier
        $b = $this->invoice($e, '2031-03-25', 16000, 1000);   // total 20,000 -> 20% on every row
        $r = $this->report(['employee_id' => $e->id]);
        $this->near(1000, $this->row($r, $a)['commission']);
        $this->near(3000, $this->row($r, $b)['commission']);
        $this->assertStringContainsString('شرائح', $this->row($r, $a)['rate_label']);
        $this->assertStringContainsString('20%', $this->row($r, $a)['rate_label']);
        $this->near(4000, $r->summary()['commission']);
    }

    public function test_tier_boundaries_follow_the_step_b_rule(): void
    {
        foreach ([10000 => 10, 10001 => 20, 50000 => 20, 50001 => 25, 150000 => 25] as $profit => $rate) {
            $e = $this->tiered();
            $es = $this->invoice($e, '2031-03-05', $profit + 1000, 1000);
            $row = $this->row($this->report(['employee_id' => $e->id]), $es);
            $this->near(round($profit * $rate / 100, 2), round($row['commission'], 2), "profit $profit");
            $this->assertStringContainsString($rate . '%', $row['rate_label']);
        }
    }

    public function test_no_commission_when_the_period_profit_is_zero_or_less(): void
    {
        foreach ([$this->emp(), $this->tiered()] as $e) {
            $win = $this->invoice($e, '2031-03-05', 3000, 1000);      // +2,000
            $loss = $this->invoice($e, '2031-03-06', 1000, 4000);     // -3,000 -> period -1,000
            $r = $this->report(['employee_id' => $e->id]);
            $this->near(0, $this->row($r, $win)['commission']);
            $this->near(0, $this->row($r, $loss)['commission']);
            $this->assertStringContainsString('لا ربح في الفترة', $this->row($r, $win)['rate_label']);
        }
    }

    // ------------------------------------------------------------------ shared (split by participation)

    public function test_shared_7_3_is_split_70_30_then_each_employees_rate(): void
    {
        [$a, $b] = [$this->emp(['commission' => '10']), $this->emp(['commission' => '20'])];
        $es = $this->invoice($this->admin, '2031-03-10', 110000, 10000, ['shared' => [$a->id, 7, $b->id, 3]]);
        // every employee: 100,000 x (70% x 10% + 30% x 20%) = 7,000 + 6,000
        $row = $this->row($this->report(), $es);
        $this->near(13000, $row['commission']);
        $this->assertSame('70% × 10% + 30% × 20%', $row['rate_label']);
        // one employee: only their part
        $this->near(6000, $this->row($this->report(['employee_id' => $b->id]), $es)['commission']);
        $this->near(7000, $this->row($this->report(['employee_id' => $a->id]), $es)['commission']);
        // financial figures unchanged (the whole ticket)
        $this->near(100000, $row['profit']);
    }

    public function test_shared_5_5_is_split_50_50(): void
    {
        [$a, $b] = [$this->emp(['commission' => '10']), $this->emp(['commission' => '20'])];
        $es = $this->invoice($a, '2031-03-10', 30000, 10000, ['shared' => [$a->id, 5, $b->id, 5]]);
        $row = $this->row($this->report(), $es);
        $this->near(3000, $row['commission']);                  // 10,000 x 10% + 10,000 x 20%
        $this->assertSame('50% × 10% + 50% × 20%', $row['rate_label']);
    }

    // ------------------------------------------------------------------ refund / re-issue

    public function test_normal_refund_and_reissue_count_for_the_performer(): void
    {
        [$seller, $performer] = [$this->emp(), $this->emp(['commission' => '15'])];
        $this->invoice($seller, '2031-02-20', 12000, 10000);
        $rs = $this->invoice($performer, '2031-03-10', 14000, 11000, ['prefix' => 'FLY-RS']);              // +3,000
        $rd = $this->invoice($performer, '2031-03-12', 0, 0, ['prefix' => 'FLY-RD', 'pax' => [[1000, 1000]], 'refund' => [800, 1000]]);  // -200
        $r = $this->report();
        $this->near(450, $this->row($r, $rs)['commission']);
        $this->near(-30, $this->row($r, $rd)['commission']);    // the refund's part of the period commission
        $this->assertSame('15%', $this->row($r, $rd)['rate_label']);
        $this->near(420, collect($r->groups('employee'))->firstWhere('label', $performer->name)['commission']);  // (3,000 - 200) x 15%
    }

    public function test_shared_refund_and_reissue_keep_the_shared_split(): void
    {
        [$a, $b, $performer] = [$this->emp(['commission' => '10']), $this->emp(['commission' => '20']), $this->emp()];
        $rs = $this->invoice($performer, '2031-03-10', 20000, 10000, ['prefix' => 'FLY-RS', 'shared' => [$a->id, 7, $b->id, 3]]);
        $rd = $this->invoice($performer, '2031-03-11', 0, 0, ['prefix' => 'FLY-RD', 'shared' => [$a->id, 7, $b->id, 3], 'pax' => [[2000, 2000]], 'refund' => [1500, 2000]]);
        $r = $this->report();
        $this->near(10000 * (0.7 * 10 + 0.3 * 20) / 100, $this->row($r, $rs)['commission']);   // 1,300
        $this->near(-500 * (0.7 * 10 + 0.3 * 20) / 100, $this->row($r, $rd)['commission']);    // -65
        $this->assertNull(collect($r->groups('employee'))->firstWhere('label', $performer->name));
    }

    // ------------------------------------------------------------------ period

    public function test_no_commission_without_a_period(): void
    {
        $e = $this->emp();
        $es = $this->invoice($e, '2031-03-10', 3000, 1000);
        foreach ([[], ['date_from' => self::FROM], ['date_to' => self::TO]] as $dates) {
            $r = new InvoiceFullReportWithCommission($dates + ['employee_id' => $e->id], $this->admin);
            $this->assertFalse($r->commissionAvailable());
            $this->assertNull($this->row($r, $es)['commission']);
            $this->assertNull($r->summary()['commission']);
            $this->assertNull(collect($r->groups('employee'))->first()['commission']);
            $this->assertSame('برجاء تحديد الفترة لعرض العمولة', $r->commissionNote());
            $this->near(2000, $r->summary()['net_profit'], 'the financial report still works');
        }
        $this->actingAs($this->admin);
        $this->get(route('site.invoices_full_report'))->assertOk()->assertSee('برجاء تحديد الفترة لعرض العمولة');
        $json = $this->getJson(route('site.invoices_full_report_data', ['employee_id' => $e->id]))->assertOk()->json();
        $this->assertNull($json['summary']['commission']);
        $this->assertNull($json['data'][0]['commission']);
        $this->assertSame('برجاء تحديد الفترة لعرض العمولة', $json['commission_note']);
        $this->get(route('site.invoices_full_report_print', ['employee_id' => $e->id]))->assertOk()->assertSee('برجاء تحديد الفترة لعرض العمولة');
    }

    public function test_date_only_total_equals_the_employee_commission_report(): void
    {
        [$f, $t, $a, $b] = [$this->emp(['commission' => '12.5']), $this->tiered(), $this->emp(['commission' => '10']), $this->emp(['commission' => '20'])];
        $this->invoice($f, '2031-03-02', 12345.67, 1000);
        $this->invoice($t, '2031-03-03', 30000, 5000);
        $this->invoice($t, '2031-03-04', 0, 0, ['prefix' => 'FLY-RD', 'pax' => [[900, 900]], 'refund' => [700, 950]]);
        $this->invoice($f, '2031-03-05', 8000, 1000, ['shared' => [$a->id, 7, $b->id, 3]]);
        $this->invoice($a, '2031-03-06', 5000, 1000, ['shared' => [$a->id, 5, $b->id, 5], 'prefix' => 'FLY-RS']);
        $this->invoice($b, '2031-03-07', 500, 2500);

        $engine = new EmployeeCommissionReport($this->admin, self::FROM, self::TO);
        $r = $this->report();
        $this->assertEqualsWithDelta($engine->totals()['commission'], $r->summary()['commission'], 0.01);
        // per employee (groups) = the engine's commission of each one
        $groups = collect($r->groups('employee'))->keyBy('label');
        foreach ($engine->results() as $e) {
            $this->assertEqualsWithDelta($e['commission'], $groups[$e['employee']]['commission'], 0.01, $e['employee']);
        }
        // with an extra filter: the filtered rows carry their part
        $this->invoice($f, '2031-03-08', 2000, 1000, ['airline' => 'OTHER-AIR']);
        $filtered = $this->report(['airline' => 'OTHER-AIR']);
        $this->assertStringContainsString('الجزء الخاص بها', $filtered->commissionNote());
        $this->near(1000 * 12.5 / 100, $filtered->summary()['commission']);
    }

    public function test_date_only_total_equals_the_engine_on_real_data(): void
    {
        foreach ([['2026-01-01', '2026-03-31'], ['2026-04-01', '2026-09-30']] as [$from, $to]) {
            $engine = (new EmployeeCommissionReport($this->admin, $from, $to))->totals()['commission'];
            $report = (new InvoiceFullReportWithCommission(['date_from' => $from, 'date_to' => $to], $this->admin))->summary()['commission'];
            $this->assertEqualsWithDelta($engine, $report, 0.01, "$from : $to");
        }
    }

    // ------------------------------------------------------------------ security

    public function test_an_employee_only_sees_their_own_commission(): void
    {
        [$me, $other] = [$this->emp(['commission' => '10']), $this->emp(['commission' => '30'])];
        $mine = $this->invoice($me, '2031-03-10', 3000, 1000);
        $this->invoice($other, '2031-03-10', 9000, 1000);
        $shared = $this->invoice($other, '2031-03-11', 11000, 1000, ['shared' => [$me->id, 7, $other->id, 3]]);

        $this->actingAs($me);
        $json = $this->getJson(route('site.invoices_full_report_data', ['date_from' => self::FROM, 'date_to' => self::TO, 'employee_id' => $other->id]))->json();
        $this->assertEqualsCanonicalizing([$mine, $shared], array_column($json['data'], 'es_id'));
        $rows = collect($json['data'])->keyBy('es_id');
        $this->near(200, $rows[$mine]['commission']);
        $this->near(700, $rows[$shared]['commission']);           // 70% x 10,000 x 10% -- never the other's 30%
        $this->assertSame('70% × 10%', $rows[$shared]['rate_label']);
        $this->near(900, $json['summary']['commission']);
    }

    // ------------------------------------------------------------------ print / Excel / page

    public function test_print_and_page_show_the_new_commission(): void
    {
        $e = $this->emp(['commission' => '12.5']);
        $this->invoice($e, '2031-03-10', 15000, 5000);
        $q = ['date_from' => self::FROM, 'date_to' => self::TO, 'employee_id' => $e->id];
        $this->actingAs($this->admin);
        $this->get(route('site.invoices_full_report_print', $q))->assertOk()->assertSee('1,250.00')->assertSee('12.5%')->assertDontSee('1,200.00');
        $this->get(route('site.invoices_full_report_print', $q + ['by' => 'employee']))->assertOk()->assertSee('1,250.00');
        $this->get(route('site.invoices_full_report', $q))->assertOk()->assertSee('الجزء الخاص بها');
        $groups = $this->getJson(route('site.invoices_full_report_groups', $q + ['by' => 'employee']))->json('groups');
        $this->near(1250, $groups[0]['commission']);
    }

    public function test_excel_shows_the_new_commission(): void
    {
        $e = $this->emp(['commission' => '12.5']);
        $this->invoice($e, '2031-03-10', 15000, 5000);
        $sheets = (new InvoiceFullReportExport($this->report(['employee_id' => $e->id])))->sheets();
        $summary = collect($sheets[0]->array())->mapWithKeys(fn ($r) => [$r[0] ?? '' => $r[1] ?? null]);
        $this->near(1250, $summary['إجمالي العمولة']);
        $details = $sheets[1]->array();
        $col = array_search('commission', array_keys(InvoiceFullReportExport::DETAIL_COLUMNS));
        $this->near(1250, $details[1][$col]);
        $this->assertSame('12.5%', $details[1][array_search('rate_label', array_keys(InvoiceFullReportExport::DETAIL_COLUMNS))]);
        $this->near(1250, $sheets[2]->array()[1][array_search('commission', array_keys(InvoiceFullReportExport::TOTAL_COLUMNS)) + 1]);  // «حسب الموظف»

        $none = (new InvoiceFullReportExport(new InvoiceFullReportWithCommission(['employee_id' => $e->id], $this->admin)))->sheets();
        $summary = collect($none[0]->array())->mapWithKeys(fn ($r) => [$r[0] ?? '' => $r[1] ?? null]);
        $this->assertSame('برجاء تحديد الفترة لعرض العمولة', $summary['إجمالي العمولة']);
    }

    // ------------------------------------------------------------------ unchanged figures and data, efficiency

    public function test_every_financial_figure_is_unchanged(): void
    {
        [$a, $b] = [$this->emp(), $this->tiered()];
        $this->invoice($a, '2031-03-02', 5000, 4000, ['pax' => [[2000, 1500], [3000, 2500]]]);
        $this->invoice($b, '2031-03-03', 9000, 7000, ['prefix' => 'FLY-RS', 'shared' => [$a->id, 7, $b->id, 3]]);
        $this->invoice($a, '2031-03-04', 0, 0, ['prefix' => 'FLY-RD', 'pax' => [[500, 500]], 'refund' => [300, 450]]);
        foreach ([[], ['employee_id' => $a->id], ['date_from' => null]] as $extra) {
            $input = array_merge(['date_from' => self::FROM, 'date_to' => self::TO], $extra);
            $old = new InvoiceFullReport($input, $this->admin);
            $new = new InvoiceFullReportWithCommission($input, $this->admin);
            $strip = fn ($rows) => collect($rows)->map(fn ($r) => array_diff_key($r, array_flip(['commission', 'rate', 'rate_label'])))->all();
            $this->assertSame($strip($old->rows()), $strip($new->rows()));
            $this->assertSame(array_diff_key($old->summary(), ['commission' => 1]), array_diff_key($new->summary(), ['commission' => 1]));
            foreach (array_keys(InvoiceFullReport::GROUPS) as $by) {
                $this->assertSame(collect($old->groups($by))->map(fn ($g) => array_diff_key($g, ['commission' => 1]))->all(),
                    collect($new->groups($by))->map(fn ($g) => array_diff_key($g, ['commission' => 1]))->all(), $by);
            }
        }
    }

    public function test_the_report_changes_no_data(): void
    {
        $tables = ['invoices', 'ticket_users', 'ticket_vendors', 'account_statements', 'bonds', 'users', 'commission_tier_tables', 'commission_tiers'];
        $snapshot = fn () => collect($tables)->mapWithKeys(fn ($t) => [$t => [DB::select("CHECKSUM TABLE `$t`")[0]->Checksum, DB::table($t)->count()]])->all();
        $before = $snapshot();
        $q = ['date_from' => '2026-01-01', 'date_to' => '2026-09-30'];
        $this->actingAs($this->admin);
        $this->get(route('site.invoices_full_report', $q))->assertOk();
        $this->getJson(route('site.invoices_full_report_data', $q))->assertOk();
        $this->getJson(route('site.invoices_full_report_groups', $q + ['by' => 'employee']))->assertOk();
        $this->get(route('site.invoices_full_report_print', $q + ['by' => 'employee']))->assertOk();
        $this->get(route('site.invoices_full_report_excel', $q))->assertOk();
        $this->assertSame($before, $snapshot());
    }

    public function test_the_number_of_queries_does_not_grow_with_invoices_or_employees(): void
    {
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $r = $this->report();
            $r->rows();
            $r->summary();
            $r->groups('employee');
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();
            return $n;
        };
        $this->invoice($this->tiered(), '2031-03-10', 2000, 1000);
        $this->invoice($this->emp(), '2031-03-10', 2000, 1000, ['prefix' => 'FLY-RD', 'refund' => [10, 20]]);
        $few = $count();
        for ($i = 0; $i < 25; $i++) {
            $e = $i % 2 ? $this->tiered() : $this->emp();
            $this->invoice($e, '2031-03-1' . ($i % 9), 2000, 1000, $i % 5 ? ($i % 3 ? [] : ['shared' => [$e->id, 7, $this->admin->id, 3]]) : ['prefix' => 'FLY-RD', 'refund' => [10, 20]]);
        }
        $this->assertSame($few, $count());
    }
}
