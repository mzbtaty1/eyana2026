<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{CommissionPayout, CommissionPeriod, User};
use App\Services\CommissionDashboard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Employees step F: the commission dashboard (admin), its print / Excel, the period detail and
 * the employee's commission statement. Figures come from Step D (current commission) and Step E
 * (periods, payouts); nothing is recalculated another way. Test data dated 2031, rolled back.
 */
class CommissionDashboardTest extends TestCase
{
    use DatabaseTransactions;

    const VENDOR = 990000001;
    const CLIENT = 990000002;

    private User $admin;
    private int $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->travelTo('2031-06-15 10:00:00');
        $this->admin = User::where('account_type', 2)->where('status', 1)->orderBy('id')->firstOrFail();
        $this->storage = (int) DB::table('storages')->orderBy('id')->value('id');
    }

    // ------------------------------------------------------------------ fixtures

    private function emp(string $commission = '10'): User
    {
        return User::create(['name' => 'TEST EMP ' . uniqid(), 'email' => 'emp-' . uniqid() . '@test.local', 'password' => Hash::make('secret-123'),
            'user_id' => 'FX_TEST', 'status' => '1', 'account_type' => 1, 'commission' => $commission])->fresh();
    }

    private function invoice(User $owner, string $date, float $sale, float $cost): void
    {
        $sys = 'TESTF-' . str_replace('.', '', uniqid('', true));
        DB::table('invoices')->insert(['es_id' => 'FLY-A1-T' . substr($sys, -10), 'ticket_system_id' => $sys, 'invoice_date' => $date,
            'invoice_travel_date' => $date, 'invoice_airline' => 'TEST-AIR', 'from_location' => 'CAI', 'to_location' => 'DXB', 'invoice_section' => 1,
            'invoice_currency' => 'جنية مصري', 'invoice_ticket_file' => '', 'invoice_beneficiaries' => self::CLIENT,
            'invoice_create_by' => (string) $owner->id, 'invoice_shared' => 0]);
        DB::table('ticket_users')->insert(['ticket_system_id' => $sys, 'client_name' => 'TEST PAX', 'client_type' => 3, 'client_net_pice' => $cost,
            'client_bought_price' => $sale, 'client_booking_id' => 'PNR', 'client_ticket_id' => 'TKT', 'crt_at' => $date]);
        DB::table('ticket_vendors')->insert(['ticket_system_id' => $sys, 'vendor_id' => self::VENDOR]);
    }

    private function open(User $e, string $from, string $to): CommissionPeriod
    {
        $this->actingAs($this->admin)->post(route('site.commission_payouts_store'), ['action' => 'open', 'employee_id' => $e->id, 'from' => $from, 'to' => $to])
            ->assertSessionHasNoErrors();
        return CommissionPeriod::where('employee_id', $e->id)->where('period_from', $from)->where('period_to', $to)->firstOrFail();
    }

    private function pay(CommissionPeriod $p, $amount, string $date = '2031-04-05'): CommissionPayout
    {
        $this->actingAs($this->admin)->post(route('site.commission_payouts_store'), ['action' => 'pay', 'period_id' => $p->id, 'amount' => $amount,
            'storage_id' => $this->storage, 'money_way' => 1, 'paid_date' => $date, 'request_token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        return CommissionPayout::where('period_id', $p->id)->orderByDesc('id')->firstOrFail();
    }

    /**
     * A: March 5,000 partly paid (2,000); B: March 3,000 fully paid (closed);
     * A: April 1,000 opened, unpaid.
     */
    private function scenario(): array
    {
        [$a, $b] = [$this->emp('10'), $this->emp('10')];
        $this->invoice($a, '2031-03-10', 60000, 10000);
        $this->invoice($b, '2031-03-11', 40000, 10000);
        $this->invoice($a, '2031-04-10', 20000, 10000);
        $pa = $this->open($a, '2031-03-01', '2031-03-31');
        $pb = $this->open($b, '2031-03-01', '2031-03-31');
        $pa4 = $this->open($a, '2031-04-01', '2031-04-30');
        $this->pay($pa, 2000, '2031-04-03');
        $this->pay($pb, 3000, '2031-04-04');
        return [$a, $b, $pa, $pb, $pa4];
    }

    private function rows(array $filters = [], ?User $viewer = null): array
    {
        return (new CommissionDashboard($viewer ?? $this->admin, $filters))->rows()->keyBy(fn ($r) => $r['employee'] . '|' . $r['from'])->all();
    }

    // ------------------------------------------------------------------ dashboard

    public function test_the_dashboard_shows_every_periods_figures(): void
    {
        [$a, $b] = $this->scenario();
        $rows = $this->rows();
        $ra = $rows[$a->name . '|2031-03-01'];
        $this->assertEquals([5000, 5000, 0, 2000, 3000, 'open', 'مفتوحة (صرف جزئي)', '2031-04-03', 50000],
            [$ra['commission'], $ra['current'], $ra['difference'], $ra['paid'], $ra['remaining'], $ra['status'], $ra['status_label'], $ra['last_paid_date'], $ra['profit']]);
        $rb = $rows[$b->name . '|2031-03-01'];
        $this->assertEquals([3000, 3000, 0, 'closed', 'مغلقة'], [$rb['paid'], $rb['commission'], $rb['remaining'], $rb['status'], $rb['status_label']]);
        $ra4 = $rows[$a->name . '|2031-04-01'];
        $this->assertEquals([1000, 0, 1000, 'مفتوحة', null], [$ra4['commission'], $ra4['paid'], $ra4['remaining'], $ra4['status_label'], $ra4['last_paid_date']]);

        $this->actingAs($this->admin)->get(route('site.commission_payouts'))->assertOk()
            ->assertSee('لوحة عمولات الموظفين')->assertSee($a->name)->assertSee($b->name)
            ->assertSee('مفتوحة (صرف جزئي)')->assertSee('2031-04-03')->assertSee('3,000.00')
            ->assertSee(e(route('site.commission_payouts_preview', ['employee_id' => $a->id, 'from' => '2031-03-01', 'to' => '2031-03-31'])), false);   // the detail link
    }

    public function test_the_dashboard_filters(): void
    {
        [$a, $b] = $this->scenario();
        $names = fn (array $f) => collect($this->rows($f))->map(fn ($r) => $r['employee'] . '|' . $r['from'])->filter(fn ($k) => str_contains($k, $a->name) || str_contains($k, $b->name))->sort()->values()->all();
        $this->assertSame([$a->name . '|2031-03-01', $a->name . '|2031-04-01'], $names(['employee_id' => $a->id]));
        $this->assertSame([$b->name . '|2031-03-01'], $names(['status' => 'closed', 'employee_id' => $b->id]));
        $this->assertSame([$a->name . '|2031-04-01'], $names(['from' => '2031-04-01', 'employee_id' => $a->id]));
        $this->assertSame([$a->name . '|2031-03-01'], $names(['to' => '2031-03-31', 'employee_id' => $a->id]));
        $this->assertSame([$a->name . '|2031-03-01', $a->name . '|2031-04-01'], $names(['status' => 'open', 'employee_id' => $a->id]));
        $this->actingAs($this->admin)->get(route('site.commission_payouts', ['employee_id' => $b->id]))->assertOk()->assertSee($b->name)->assertDontSee('<td>' . $a->name . '</td>', false);
    }

    public function test_a_later_change_shows_the_difference_everywhere_without_changing_payments(): void
    {
        [$a, , $pa] = $this->scenario();
        $this->invoice($a, '2031-03-20', 11000, 1000);                     // March now 60,000 -> 6,000
        $row = $this->rows()[$a->name . '|2031-03-01'];
        $this->assertEquals([5000, 6000, 1000, true, 2000, 3000], [$row['commission'], $row['current'], $row['difference'], $row['differs'], $row['paid'], $row['remaining']]);
        $this->assertSame(['5000.00', 2000.0], [$pa->fresh()->commission_amount, $pa->fresh()->paid()], 'nothing adjusted');

        $this->actingAs($this->admin)->get(route('site.commission_payouts'))->assertSee('1,000.00')->assertSee('6,000.00');
        $q = ['employee_id' => $a->id, 'from' => '2031-03-01', 'to' => '2031-03-31'];
        $this->get(route('site.commission_payouts_preview', $q))->assertOk()
            ->assertSee('عمولة وقت الدفع')->assertSee('العمولة الحالية')->assertSee('الفرق')->assertSee('commission-difference', false);
        $this->actingAs($a)->get(route('site.commission_payouts_mine'))->assertOk()
            ->assertSee('عمولة وقت الدفع: 5,000.00')->assertSee('العمولة الحالية: 6,000.00')->assertSee('الفرق: 1,000.00');
    }

    public function test_payout_and_reversal_history_is_shown(): void
    {
        [$a, , $pa] = $this->scenario();
        $payout = CommissionPayout::where('period_id', $pa->id)->firstOrFail();
        $this->actingAs($this->admin)->post(route('site.commission_payouts_reverse', $payout->id))->assertSessionHasNoErrors();
        $this->pay($pa->fresh(), 1500, '2031-04-06');

        $row = $this->rows()[$a->name . '|2031-03-01'];
        $this->assertEquals([1500, 3500, 1, '2031-04-06'], [$row['paid'], $row['remaining'], $row['reversals'], $row['last_paid_date']]);
        $this->get(route('site.commission_payouts'))->assertSee('1 عكس');
        $this->get(route('site.commission_payouts_preview', ['employee_id' => $a->id, 'from' => '2031-03-01', 'to' => '2031-03-31']))->assertOk()
            ->assertSee('معكوس')->assertSee($payout->fresh()->reversal_bond_note)->assertSee($payout->bond->es_id);
        $this->actingAs($a)->get(route('site.commission_payouts_mine'))->assertOk()
            ->assertSee('معكوس في ' . $payout->fresh()->reversed_at->format('Y-m-d'))->assertSee('1,500.00')->assertSee('2031-04-06');
    }

    // ------------------------------------------------------------------ statement / access

    public function test_an_employee_statement_shows_only_their_own_periods(): void
    {
        [$a, $b] = $this->scenario();
        $this->actingAs($a)->get(route('site.commission_payouts_mine', ['employee_id' => $b->id]))->assertOk()   // forged employee_id: ignored
            ->assertSee('كشف العمولات: ' . $a->name)->assertSee('2031-04-01 : 2031-04-30')->assertSee('50,000.00')
            ->assertDontSee($b->name)->assertDontSee('name="employee_id"', false);
        $rows = (new CommissionDashboard($a, ['employee_id' => $a->id]))->rows();
        $this->assertSame([$a->id], $rows->pluck('period.employee_id')->unique()->values()->all());

        // an admin may view any employee's statement
        $this->actingAs($this->admin)->get(route('site.commission_payouts_mine', ['employee_id' => $b->id]))->assertOk()
            ->assertSee('كشف العمولات: ' . $b->name)->assertSee('3,000.00');
    }

    public function test_employees_cannot_use_the_admin_pages(): void
    {
        [$a] = $this->scenario();
        $this->actingAs($a);
        foreach (['site.commission_payouts', 'site.commission_payouts_print', 'site.commission_payouts_excel'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->get(route('site.commission_payouts_preview', ['employee_id' => $a->id, 'from' => '2031-03-01', 'to' => '2031-03-31']))->assertForbidden();
        $this->post(route('site.commission_payouts_reverse', CommissionPayout::value('id')))->assertForbidden();
        $this->get(route('site.commission_payouts_mine'))->assertOk()->assertDontSee('fr-print', false)->assertDontSee('عكس</button>', false);
        auth()->logout();
        $this->get(route('site.commission_payouts_mine'))->assertRedirect(route('login'));
        $this->get(route('site.commission_payouts_excel'))->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------ print / Excel

    public function test_print_and_excel_carry_the_dashboard_figures(): void
    {
        [$a] = $this->scenario();
        $this->actingAs($this->admin)->get(route('site.commission_payouts_print', ['employee_id' => $a->id]))->assertOk()
            ->assertSee('تقرير عمولات الموظفين')->assertSee($a->name)->assertSee('6,000.00')->assertSee('2,000.00')->assertSee('4,000.00');   // commission 5,000 + 1,000; paid; remaining

        $table = (new CommissionDashboard($this->admin, ['employee_id' => $a->id]))->table();
        $this->assertSame('الموظف', $table[0][0]);
        $this->assertCount(4, $table);                                          // header, 2 periods, totals
        $this->assertEquals(['الإجمالي', 6000, 6000, 0, 2000, 4000], [end($table)[0], end($table)[4], end($table)[5], end($table)[6], end($table)[7], end($table)[8]]);

        $res = $this->get(route('site.commission_payouts_excel', ['employee_id' => $a->id]));
        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $res->headers->get('content-type'));
    }

    // ------------------------------------------------------------------ efficiency / safety

    public function test_the_current_commission_is_computed_once_per_period_not_per_row(): void
    {
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            (new CommissionDashboard($this->admin, ['from' => '2031-01-01']))->rows();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();
            return $n;
        };
        $add = function () {
            $e = $this->emp();
            $this->invoice($e, '2031-03-10', 3000, 1000);
            $this->pay($this->open($e, '2031-03-01', '2031-03-31'), 50, '2031-04-02');
        };
        $add();
        $add();
        $few = $count();
        for ($i = 0; $i < 6; $i++) {
            $add();
        }
        $this->assertSame($few, $count(), 'same number of queries for 2 and 8 employees in the same period');
    }

    public function test_the_pages_change_no_data(): void
    {
        [$a] = $this->scenario();
        $tables = ['invoices', 'ticket_users', 'account_statements', 'bonds', 'storages', 'storage_statements', 'users', 'commission_periods', 'commission_payouts'];
        $snapshot = fn () => collect($tables)->mapWithKeys(fn ($t) => [$t => [DB::select("CHECKSUM TABLE `$t`")[0]->Checksum, DB::table($t)->count()]])->all();
        $before = $snapshot();
        $this->actingAs($this->admin);
        $this->get(route('site.commission_payouts'))->assertOk();
        $this->get(route('site.commission_payouts_print'))->assertOk();
        $this->get(route('site.commission_payouts_excel'))->assertOk();
        $this->get(route('site.commission_payouts_mine', ['employee_id' => $a->id]))->assertOk();
        $this->get(route('site.commission_payouts_preview', ['employee_id' => $a->id, 'from' => '2031-03-01', 'to' => '2031-03-31']))->assertOk();
        $this->actingAs($a)->get(route('site.commission_payouts_mine'))->assertOk();
        $this->assertSame($before, $snapshot());
    }
}
