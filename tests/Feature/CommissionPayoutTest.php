<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{Bond, CommissionPayout, CommissionPeriod, CommissionTierTable, User};
use App\Services\{CommissionPayouts, EmployeeCommissionReport, PaymentBond};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Employees step E: commission periods and payouts. The commission is always the Step D
 * result; a payout is a payment voucher (PaymentBond) from a treasury (and bank) to expense
 * account #81; partial payments, automatic closing, no overpayment / duplicate / overlap,
 * reversal by offsetting entries. Test data dated 2031 (and one 2026-09 employee), rolled back.
 */
class CommissionPayoutTest extends TestCase
{
    use DatabaseTransactions;

    const FROM = '2031-03-01';
    const TO = '2031-03-31';
    const VENDOR = 990000001;
    const CLIENT = 990000002;

    private User $admin;
    private int $storage;
    private int $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->travelTo('2031-06-15 10:00:00');            // the 2031-03 test periods have ended (payment needs a completed period)
        $this->admin = User::where('account_type', 2)->where('status', 1)->orderBy('id')->firstOrFail();
        $this->storage = (int) DB::table('storages')->orderBy('id')->value('id');
        $this->bank = (int) DB::table('banks')->orderBy('id')->value('id');
    }

    // ------------------------------------------------------------------ fixtures

    private function emp(array $attrs = []): User
    {
        return User::create(array_merge(['name' => 'TEST EMP ' . uniqid(), 'email' => 'emp-' . uniqid() . '@test.local',
            'password' => Hash::make('secret-123'), 'user_id' => 'FX_TEST', 'status' => '1', 'account_type' => 1, 'commission' => '10'], $attrs))->fresh();
    }

    private function tiered(): User
    {
        $t = CommissionTierTable::create(['name' => 'TEST STANDARD ' . uniqid(), 'status' => 1]);
        $t->tiers()->createMany([
            ['from_amount' => 1, 'to_amount' => 10000, 'rate' => 10, 'sort_order' => 1],
            ['from_amount' => 10001, 'to_amount' => 50000, 'rate' => 20, 'sort_order' => 2],
            ['from_amount' => 50001, 'to_amount' => null, 'rate' => 25, 'sort_order' => 3],
        ]);
        return $this->emp(['commission_method' => 'tiered', 'commission_tier_table_id' => $t->id]);
    }

    private function invoice($owner, string $date, float $sale, float $cost, array $o = []): void
    {
        $sys = 'TESTP-' . str_replace('.', '', uniqid('', true));
        $shared = $o['shared'] ?? null;
        $es = ($o['prefix'] ?? 'FLY-A1') . '-T' . substr($sys, -10);
        DB::table('invoices')->insert([
            'es_id' => $es, 'ticket_system_id' => $sys, 'invoice_date' => $date, 'invoice_travel_date' => $date,
            'invoice_airline' => 'TEST-AIR', 'from_location' => 'CAI', 'to_location' => 'DXB', 'invoice_section' => 1,
            'invoice_currency' => 'جنية مصري', 'invoice_ticket_file' => '', 'invoice_beneficiaries' => self::CLIENT,
            'invoice_create_by' => (string) ($owner instanceof User ? $owner->id : $owner), 'invoice_shared' => $shared ? 1 : 0,
            'invoice_account_1' => $shared ? (string) $shared[0] : null, 'invoice_account_1_comm' => $shared ? (string) $shared[1] : null,
            'invoice_account_2' => $shared ? (string) $shared[2] : null, 'invoice_account_2_comm' => $shared ? (string) $shared[3] : null,
        ]);
        foreach ($o['pax'] ?? [[$sale, $cost]] as $i => [$s, $c]) {
            DB::table('ticket_users')->insert(['ticket_system_id' => $sys, 'client_name' => 'TEST PAX', 'client_type' => 3,
                'client_net_pice' => $c, 'client_bought_price' => $s, 'client_booking_id' => 'PNR', 'client_ticket_id' => 'TKT' . $i, 'crt_at' => $date]);
        }
        DB::table('ticket_vendors')->insert(['ticket_system_id' => $sys, 'vendor_id' => self::VENDOR]);
        if (isset($o['refund'])) {
            foreach ([[self::VENDOR, $o['refund'][0], 0], [self::CLIENT, 0, $o['refund'][1]]] as [$acc, $debit, $credit]) {
                DB::table('account_statements')->insert(['supp_client_id' => $acc, 'invoice_type' => 1, 'es_id' => $es, 'invoice_date' => $date,
                    'transaction_txt' => 'TEST REFUND', 'transaction_type' => 1, 'debit_balance' => $debit, 'credit_balance' => $credit]);
            }
        }
    }

    /** An employee with a period profit of 50,000 at fixed 10% -> commission 5,000. */
    private function fivethousand(): User
    {
        $e = $this->emp(['commission' => '10']);
        $this->invoice($e, '2031-03-10', 60000, 10000);
        return $e;
    }

    private function open(User $e, string $from = self::FROM, string $to = self::TO, array $extra = [])
    {
        return $this->actingAs($this->admin)->post(route('site.commission_payouts_store'), ['action' => 'open', 'employee_id' => $e->id, 'from' => $from, 'to' => $to] + $extra);
    }

    private function period(User $e, string $from = self::FROM, string $to = self::TO): ?CommissionPeriod
    {
        return CommissionPeriod::where('employee_id', $e->id)->where('period_from', $from)->where('period_to', $to)->first();
    }

    private function openPeriod(User $e): CommissionPeriod
    {
        $this->open($e)->assertSessionHasNoErrors();
        return $this->period($e);
    }

    private function pay(CommissionPeriod $p, $amount, array $over = [])
    {
        return $this->actingAs($this->admin)->post(route('site.commission_payouts_store'), array_merge(['action' => 'pay', 'period_id' => $p->id,
            'amount' => $amount, 'storage_id' => $this->storage, 'money_way' => 1, 'paid_date' => '2031-04-02', 'reference' => 'TEST',
            'request_token' => (string) Str::uuid()], $over));
    }

    /** Rows written for a voucher: bond, its account statements, storage / bank ledger entries. */
    private function voucherRows(int $bondId): array
    {
        $bond = Bond::findOrFail($bondId);
        return [
            'bond' => $bond,
            'statements' => DB::table('account_statements')->where('es_id', $bond->es_id)->orderBy('id')->get(),
            'storage' => DB::table('storage_statements')->where('bond_id', $bondId)->orderBy('id')->get(),
            'bank' => DB::table('bank_statements')->where('bond_id', $bondId)->orderBy('id')->get(),
        ];
    }

    private function balances(): array
    {
        return [(float) DB::table('storages')->where('id', $this->storage)->value('balance'), (float) DB::table('banks')->where('id', $this->bank)->value('bank_balance')];
    }

    // ------------------------------------------------------------------ periods and the Step D snapshot

    public function test_opening_a_period_stores_the_step_d_snapshot(): void
    {
        $e = $this->fivethousand();
        $this->actingAs($this->admin)->get(route('site.commission_payouts_preview', ['employee_id' => $e->id, 'from' => self::FROM, 'to' => self::TO]))
            ->assertOk()->assertSee('5,000.00')->assertSee('فتح الفترة واعتماد العمولة');
        $this->open($e, self::FROM, self::TO, ['commission_amount' => 999999])->assertSessionHasNoErrors();   // a posted amount is ignored

        $p = $this->period($e);
        $this->assertSame(['5000.00', 'open', $this->admin->id], [$p->commission_amount, $p->status, (int) $p->created_by]);
        $c = $p->calculation;
        foreach (['employee' => $e->name, 'from' => self::FROM, 'to' => self::TO, 'sales' => 60000, 'cost' => 10000, 'refund_net' => 0,
                  'profit' => 50000, 'method' => 'fixed', 'tier_table' => null, 'tier' => null, 'rate' => 10, 'commission' => 5000] as $k => $v) {
            $this->assertEquals($v, $c[$k], $k);
        }
        // opening it again is the same period
        $this->open($e)->assertSessionHasNoErrors();
        $this->assertSame(1, CommissionPeriod::where('employee_id', $e->id)->count());
    }

    public function test_fixed_12_5_percent_and_tiered_and_shared_come_from_step_d(): void
    {
        $f = $this->emp(['commission' => '12.5']);
        $this->invoice($f, '2031-03-10', 11000, 1000);                      // 10,000 x 12.5%
        $t = $this->tiered();
        $this->invoice($t, '2031-03-05', 16000, 1000);
        $this->invoice($t, '2031-03-06', 6000, 1000);                       // 20,000 -> 20%
        [$a, $b] = [$this->emp(['commission' => '10']), $this->emp(['commission' => '20'])];
        $this->invoice($this->admin, '2031-03-07', 110000, 10000, ['shared' => [$a->id, 7, $b->id, 3]]);   // 70,000 / 30,000

        foreach ([[$f, 1250, 12.5], [$t, 4000, 20], [$a, 7000, 10], [$b, 6000, 20]] as [$e, $commission, $rate]) {
            $p = $this->openPeriod($e);
            $this->assertEqualsWithDelta($commission, (float) $p->commission_amount, 0.001, $e->name);
            $this->assertEqualsWithDelta($rate, $p->calculation['rate'], 0.001);
            $step = (new EmployeeCommissionReport($this->admin, self::FROM, self::TO, $e->id))->results()[0];
            $this->assertEqualsWithDelta($step['commission'], (float) $p->commission_amount, 0.001, 'the same engine');
        }
        $this->assertStringContainsString('TEST STANDARD', $this->period($t)->calculation['tier_table']);
        $this->assertSame(2, $this->period($t)->calculation['tier']['n']);
    }

    public function test_refunds_and_reissues_count_as_in_step_d(): void
    {
        $e = $this->emp(['commission' => '10']);
        $this->invoice($e, '2031-03-05', 14000, 11000, ['prefix' => 'FLY-RS']);                                            // +3,000
        $this->invoice($e, '2031-03-06', 0, 0, ['prefix' => 'FLY-RD', 'pax' => [[1000, 1000]], 'refund' => [800, 1000]]);  // -200
        $p = $this->openPeriod($e);
        $this->assertEqualsWithDelta(280, (float) $p->commission_amount, 0.001);
        $this->assertEqualsWithDelta(-200, $p->calculation['refund_net'], 0.001);
    }

    public function test_the_system_starts_on_2026_09_01(): void
    {
        $e = $this->emp();
        $this->invoice($e, '2026-08-31', 3000, 1000);
        $this->invoice($e, '2026-09-01', 5000, 1000);
        $this->open($e, '2026-08-31', '2026-09-30')->assertSessionHasErrors('period');
        $this->assertSame(0, CommissionPeriod::where('employee_id', $e->id)->count());
        $this->actingAs($this->admin)->get(route('site.commission_payouts_preview', ['employee_id' => $e->id, 'from' => '2026-08-01', 'to' => '2026-08-31']))
            ->assertOk()->assertSee('يبدأ من 2026-09-01')->assertDontSee('فتح الفترة واعتماد العمولة');
        $this->open($e, '2026-09-01', '2026-09-30')->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(400, (float) $this->period($e, '2026-09-01', '2026-09-30')->commission_amount, 0.001);
    }

    public function test_no_period_without_commission_and_invalid_input(): void
    {
        $e = $this->emp();
        $this->invoice($e, '2031-03-05', 1000, 3000);                        // a loss
        $this->open($e)->assertSessionHasErrors('period');
        $this->assertNull($this->period($e));
        $this->actingAs($this->admin);
        $this->post(route('site.commission_payouts_store'), ['action' => 'open', 'employee_id' => 999999999, 'from' => self::FROM, 'to' => self::TO])->assertSessionHasErrors('employee_id');
        $this->post(route('site.commission_payouts_store'), ['action' => 'open', 'employee_id' => $e->id, 'from' => self::TO, 'to' => self::FROM])->assertSessionHasErrors('to');
        $this->get(route('site.commission_payouts_preview', ['employee_id' => $e->id, 'from' => 'abc', 'to' => self::TO]))->assertSessionHasErrors('from');
    }

    public function test_the_same_employees_periods_cannot_overlap(): void
    {
        $e = $this->fivethousand();
        $this->openPeriod($e);
        $this->open($e, '2031-03-15', '2031-04-15')->assertSessionHasErrors('period');
        $this->open($e, '2031-02-01', '2031-03-01')->assertSessionHasErrors('period');
        $this->assertSame(1, CommissionPeriod::where('employee_id', $e->id)->count());
        $this->invoice($e, '2031-04-10', 2000, 1000);
        $this->open($e, '2031-04-01', '2031-04-30')->assertSessionHasNoErrors();     // the next month
        $other = $this->emp();
        $this->invoice($other, '2031-03-20', 3000, 1000);
        $this->open($other, '2031-03-15', '2031-04-15')->assertSessionHasNoErrors();   // another employee
    }

    // ------------------------------------------------------------------ paying

    public function test_a_period_is_paid_only_once_it_has_ended(): void
    {
        $this->travelTo('2026-09-28 12:00:00');
        [$future, $today, $ended] = [$this->emp(), $this->emp(), $this->emp()];
        foreach ([$future, $today, $ended] as $e) {
            $this->invoice($e, '2026-09-10', 5000, 1000);                    // 4,000 x 10% = 400
        }
        $bonds = Bond::count();
        foreach ([[$future, '2026-09-30'], [$today, '2026-09-28']] as [$e, $to]) {
            $this->open($e, '2026-09-01', $to)->assertSessionHasNoErrors();   // may be opened ...
            $p = $this->period($e, '2026-09-01', $to);
            $this->pay($p, 400)->assertSessionHasErrors('payout');           // ... but not paid before it has ended
            $this->actingAs($this->admin)->get(route('site.commission_payouts_preview', ['employee_id' => $e->id, 'from' => '2026-09-01', 'to' => $to]))
                ->assertOk()->assertSee('commission-not-ended', false)->assertDontSee('commission-pay-form', false);
            $this->assertSame([0.0, 'open'], [$p->fresh()->paid(), $p->fresh()->status], "to $to");
        }
        // service call (not only the screen) refuses as well
        [$payout, $error] = CommissionPayouts::pay($this->period($future, '2026-09-01', '2026-09-30')->id, ['amount' => 400, 'storage_id' => $this->storage,
            'money_way' => 1, 'bank_id' => null, 'paid_date' => '2026-09-28', 'reference' => null, 'request_token' => (string) Str::uuid()]);
        $this->assertNull($payout);
        $this->assertStringContainsString('لم تنته بعد', $error);
        $this->assertSame($bonds, Bond::count());

        // ended yesterday: payable
        $this->open($ended, '2026-09-01', '2026-09-27')->assertSessionHasNoErrors();
        $this->pay($this->period($ended, '2026-09-01', '2026-09-27'), 400, ['paid_date' => '2026-09-28'])->assertSessionHasNoErrors();
        $this->assertSame('closed', $this->period($ended, '2026-09-01', '2026-09-27')->status);
        // an inverted period is invalid
        $this->open($this->emp(), '2026-09-01', '2026-08-31')->assertSessionHasErrors('to');

        // the same period becomes payable once it has ended
        $this->travelTo('2026-10-01 09:00:00');
        $this->pay($this->period($future, '2026-09-01', '2026-09-30'), 400, ['paid_date' => '2026-10-01'])->assertSessionHasNoErrors();
    }

    public function test_partial_then_full_payment_closes_the_period(): void
    {
        $p = $this->openPeriod($this->fivethousand());
        $this->pay($p, 2000)->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame([2000.0, 3000.0, 'open'], [$p->paid(), $p->remaining(), $p->status]);
        $this->actingAs($this->admin)->get(route('site.commission_payouts'))->assertOk()->assertSee('3,000.00')->assertSee('صرف جزئي');

        $this->pay($p, '3000')->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame([5000.0, 0.0, 'closed', $this->admin->id], [$p->paid(), $p->remaining(), $p->status, (int) $p->closed_by]);
        $this->assertNotNull($p->closed_at);
        $this->assertSame(2, $p->payouts()->count());
    }

    public function test_overpayment_and_invalid_amounts_are_refused(): void
    {
        $p = $this->openPeriod($this->fivethousand());
        $bonds = Bond::count();
        $this->pay($p, '5000.01')->assertSessionHasErrors('payout');
        $this->pay($p, '0')->assertSessionHasErrors('amount');
        $this->pay($p, '-10')->assertSessionHasErrors('amount');
        $this->pay($p, 'abc')->assertSessionHasErrors('amount');
        $this->pay($p, '3000')->assertSessionHasNoErrors();
        $this->pay($p, '2000.01')->assertSessionHasErrors('payout');
        $this->assertSame($bonds + 1, Bond::count());
        $this->assertEqualsWithDelta(2000, $p->fresh()->remaining(), 0.001);
    }

    public function test_a_closed_period_cannot_be_paid_again(): void
    {
        $p = $this->openPeriod($this->fivethousand());
        $this->pay($p, 5000)->assertSessionHasNoErrors();
        $bonds = Bond::count();
        $this->pay($p, 1)->assertSessionHasErrors('payout');
        $this->assertSame($bonds, Bond::count());
    }

    public function test_a_duplicate_request_token_pays_once(): void
    {
        $p = $this->openPeriod($this->fivethousand());
        $token = (string) Str::uuid();
        $this->pay($p, 1000, ['request_token' => $token])->assertSessionHasNoErrors();
        $bonds = Bond::count();
        $this->pay($p, 1000, ['request_token' => $token])->assertSessionHasErrors('payout');
        $this->assertSame([$bonds, 1, 1000.0], [Bond::count(), CommissionPayout::where('request_token', $token)->count(), $p->fresh()->paid()]);
    }

    public function test_concurrent_payouts_are_serialized_by_the_period_lock(): void
    {
        $p = $this->openPeriod($this->fivethousand());
        $this->actingAs($this->admin);
        DB::flushQueryLog();
        DB::enableQueryLog();
        [$first] = CommissionPayouts::pay($p->id, ['amount' => 5000, 'storage_id' => $this->storage, 'money_way' => 1,
            'bank_id' => null, 'paid_date' => '2031-04-02', 'reference' => null, 'request_token' => (string) Str::uuid()]);
        $log = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();
        $this->assertNotNull($first);
        // the period row is locked first, before the treasury
        $periodLock = $log->search(fn ($q) => str_contains($q, 'commission_periods') && str_contains($q, 'for update'));
        $storageLock = $log->search(fn ($q) => str_contains($q, '`storages`') && str_contains($q, 'for update'));
        $this->assertNotFalse($periodLock);
        $this->assertNotFalse($storageLock);
        $this->assertLessThan($storageLock, $periodLock);
        // a second attempt for the same money (another token) waits for the lock, then finds nothing left
        [$second, $error] = CommissionPayouts::pay($p->id, ['amount' => 5000, 'storage_id' => $this->storage, 'money_way' => 1,
            'bank_id' => null, 'paid_date' => '2031-04-02', 'reference' => null, 'request_token' => (string) Str::uuid()]);
        $this->assertNull($second);
        $this->assertNotNull($error);
        $this->assertSame(1, $p->payouts()->count());
    }

    // ------------------------------------------------------------------ the voucher and the ledgers

    public function test_a_cash_payout_books_one_payment_voucher_to_account_81(): void
    {
        $e = $this->fivethousand();
        $p = $this->openPeriod($e);
        $before = $this->balances();
        [$bonds, $statements, $storageRows, $bankRows] = [Bond::count(), DB::table('account_statements')->count(), DB::table('storage_statements')->count(), DB::table('bank_statements')->count()];
        $this->pay($p, 1234.5)->assertSessionHasNoErrors();
        $payout = CommissionPayout::where('period_id', $p->id)->firstOrFail();

        // exactly one voucher and its entries, nothing else
        $this->assertSame([$bonds + 1, $statements + 2, $storageRows + 1, $bankRows],
            [Bond::count(), DB::table('account_statements')->count(), DB::table('storage_statements')->count(), DB::table('bank_statements')->count()]);
        $v = $this->voucherRows($payout->bond_id);
        $this->assertSame(['1', (string) $this->storage, 'storage', '81', 'supplier', '1234.50', 1, '2031-04-02'],
            [(string) $v['bond']->type, (string) $v['bond']->from_account, $v['bond']->from_type, (string) $v['bond']->to_account, $v['bond']->to_type,
             (string) $v['bond']->amount, (int) $v['bond']->money_way, (string) $v['bond']->crt_date]);
        $this->assertStringContainsString($e->name, $v['bond']->info);
        [$treasury, $account] = $v['statements']->all();
        $this->assertEquals([$this->storage, 1, 0, 1234.5, -1234.5], [(int) $treasury->supp_client_id, (int) $treasury->is_storage, (float) $treasury->debit_balance, (float) $treasury->credit_balance, (float) $treasury->ledger_net_effect]);
        $this->assertEquals([81, 1234.5, 0, 1234.5], [(int) $account->supp_client_id, (float) $account->debit_balance, (float) $account->credit_balance, (float) $account->ledger_net_effect]);
        $this->assertStringContainsString('عمولة الموظف ' . $e->name, $account->transaction_txt);
        $this->assertEquals([1234.5, 0], [(float) $v['storage'][0]->debit, (float) $v['storage'][0]->credit]);
        $this->assertEqualsWithDelta($before[0] - 1234.5, $this->balances()[0], 0.001);
        $this->assertEqualsWithDelta($before[1], $this->balances()[1], 0.001, 'no bank movement');
        $this->actingAs($this->admin)->get(route('site.commission_payouts_receipt', $payout->id))->assertOk()->assertSee('1,234.50')->assertSee($v['bond']->es_id);
    }

    public function test_a_bank_payout_moves_the_bank_as_well(): void
    {
        $p = $this->openPeriod($this->fivethousand());
        $before = $this->balances();
        $this->pay($p, 700, ['money_way' => 2, 'bank_id' => $this->bank])->assertSessionHasNoErrors();
        $payout = CommissionPayout::where('period_id', $p->id)->firstOrFail();
        $v = $this->voucherRows($payout->bond_id);
        $this->assertSame([2, $this->bank], [(int) $v['bond']->money_way, (int) $v['bond']->bank_id]);
        $this->assertCount(1, $v['bank']);
        $this->assertEquals([700, 0], [(float) $v['bank'][0]->debit, (float) $v['bank'][0]->credit]);
        $this->assertEqualsWithDelta($before[0] - 700, $this->balances()[0], 0.001);
        $this->assertEqualsWithDelta($before[1] - 700, $this->balances()[1], 0.001);
        $this->pay($p, 1, ['money_way' => 2, 'bank_id' => 999999999])->assertSessionHasErrors('bank_id');
    }

    public function test_payment_bond_books_exactly_what_the_voucher_screen_books(): void
    {
        $supplier = (int) DB::table('suppliers')->where('acc_type', 2)->orderBy('id')->value('id');
        $normalize = function (int $bondId) {
            $v = $this->voucherRows($bondId);
            $strip = fn ($rows) => collect($rows)->map(fn ($r) => collect((array) $r)->except(['id', 'bond_id', 'created_at', 'updated_at', 'system_id', 'running_balance'])
                ->map(fn ($x) => is_string($x) ? str_replace((string) $bondId, '{B}', $x) : $x)->all())->all();
            return ['bond' => $strip([$v['bond']->getAttributes()]), 'statements' => $strip($v['statements']), 'storage' => $strip($v['storage']), 'bank' => $strip($v['bank'])];
        };
        foreach ([[81, 1, null, 0], [$supplier, 2, $this->bank, 7.5]] as [$to, $way, $bank, $fee]) {
            $input = ['type_slctd' => 1, 'storage_id' => $this->storage, 'sub_id' => '0', 'supp_id' => $to, 'amount' => '321.5', 'money_way' => $way,
                'bank_id' => $bank, 'commission' => $fee, 'crt_date' => '2031-04-03', 'transaction_info' => 'TEST INFO', 'collector_info' => 'TEST C'];
            $max = (int) Bond::max('id');
            $this->actingAs($this->admin)->post(route('site.bonds_save'), $input)->assertSessionHasNoErrors();
            $screen = (int) Bond::where('id', '>', $max)->value('id');
            $service = PaymentBond::create(['storage_id' => $this->storage, 'sub_id' => '0', 'supp_id' => $to, 'amount' => '321.5', 'commission' => $way === 2 ? 7.5 : 0,
                'money_way' => $way, 'bank_id' => $bank, 'collector_info' => 'TEST C', 'info' => 'TEST INFO', 'date' => '2031-04-03', 'file_path' => ''])->id;
            $this->assertSame($normalize($screen), $normalize($service), "money_way $way");
        }
    }

    // ------------------------------------------------------------------ reversal

    public function test_reversal_offsets_the_voucher_and_reopens_the_period(): void
    {
        $p = $this->openPeriod($this->fivethousand());
        $before = $this->balances();
        $this->pay($p, 5000, ['money_way' => 2, 'bank_id' => $this->bank])->assertSessionHasNoErrors();
        $payout = CommissionPayout::where('period_id', $p->id)->firstOrFail();
        $this->assertSame('closed', $p->fresh()->status);
        $bondRow = Bond::findOrFail($payout->bond_id)->getAttributes();
        $original = $this->voucherRows($payout->bond_id);
        $originalStatements = $original['statements']->map(fn ($r) => (array) $r)->all();
        $originalLedger = [$original['storage'][0]->id, $original['storage'][0]->debit, $original['bank'][0]->id, $original['bank'][0]->debit];

        $this->actingAs($this->admin)->post(route('site.commission_payouts_reverse', $payout->id))->assertSessionHasNoErrors();
        $payout->refresh();
        $p->refresh();
        $this->assertNotNull($payout->reversed_at);
        $this->assertSame([$this->admin->id, 'open', 0.0, 5000.0], [(int) $payout->reversed_by, $p->status, $p->paid(), $p->remaining()]);
        $this->assertNull($p->closed_at);

        // nothing deleted: the voucher and its rows stay; offsetting rows net them to zero
        $this->assertSame($bondRow, Bond::findOrFail($payout->bond_id)->getAttributes());
        $v = $this->voucherRows($payout->bond_id);
        $this->assertSame($originalStatements, $v['statements']->take(2)->map(fn ($r) => (array) $r)->values()->all(), 'the original statement rows are kept unchanged');
        $this->assertSame($originalLedger, [$v['storage'][0]->id, $v['storage'][0]->debit, $v['bank'][0]->id, $v['bank'][0]->debit], 'the original ledger entries are kept');
        $this->assertCount(4, $v['statements']);
        $this->assertEqualsWithDelta(0, $v['statements']->sum(fn ($r) => (float) $r->debit_balance - (float) $r->credit_balance), 0.001);
        $this->assertCount(2, $v['storage']);
        $this->assertSame(1, (int) $v['storage'][0]->is_voided);
        $this->assertEquals([0, 5000], [(float) $v['storage'][1]->debit, (float) $v['storage'][1]->credit]);
        $this->assertCount(2, $v['bank']);
        $this->assertEqualsWithDelta($before[0], $this->balances()[0], 0.001, 'treasury restored');
        $this->assertEqualsWithDelta($before[1], $this->balances()[1], 0.001, 'bank restored');

        // reversing twice is refused; the period can be paid again
        $this->post(route('site.commission_payouts_reverse', $payout->id))->assertSessionHasErrors('payout');
        $this->pay($p, 5000)->assertSessionHasNoErrors();
        $this->assertSame('closed', $p->fresh()->status);
    }

    public function test_a_payout_voucher_cannot_be_edited_or_deleted_from_the_voucher_screens(): void
    {
        $p = $this->openPeriod($this->fivethousand());
        $this->pay($p, 1000)->assertSessionHasNoErrors();
        $bond = Bond::findOrFail(CommissionPayout::where('period_id', $p->id)->value('bond_id'));
        $row = $bond->getAttributes();
        $this->actingAs($this->admin);
        $this->get(route('site.bonds_edit', $bond->id))->assertSessionHasErrors('bond');
        $this->post(route('site.bonds_delete', $bond->id))->assertSessionHasErrors('bond');
        $this->post(route('site.bonds_save_update'), ['bond_id' => $bond->id, 'amount' => 1])->assertSessionHasErrors();
        $this->assertSame($row, Bond::findOrFail($bond->id)->getAttributes());
        $this->assertCount(2, $this->voucherRows($bond->id)['statements']);
    }

    // ------------------------------------------------------------------ changes after payment

    public function test_a_later_change_is_shown_and_blocks_further_payment(): void
    {
        $e = $this->fivethousand();
        $p = $this->openPeriod($e);
        $this->pay($p, 1000)->assertSessionHasNoErrors();
        $this->invoice($e, '2031-03-20', 11000, 1000);                       // now 60,000 -> 6,000
        $q = ['employee_id' => $e->id, 'from' => self::FROM, 'to' => self::TO];
        $this->actingAs($this->admin)->get(route('site.commission_payouts_preview', $q))->assertOk()
            ->assertSee('العمولة الحالية')->assertSee('العمولة المعتمدة عند فتح الفترة')->assertSee('6,000.00')->assertSee('5,000.00')
            ->assertSee('commission-difference', false)->assertDontSee('commission-pay-form', false)->assertDontSee('اعتماد العمولة الحالية');
        $bonds = Bond::count();
        $this->pay($p, 1000)->assertSessionHasErrors('payout');
        $this->post(route('site.commission_payouts_store'), ['action' => 'refresh', 'period_id' => $p->id])->assertSessionHasErrors('period');
        $this->assertSame([$bonds, '5000.00'], [Bond::count(), $p->fresh()->commission_amount], 'no automatic adjustment');
    }

    public function test_an_unpaid_period_can_take_the_current_figures(): void
    {
        $e = $this->fivethousand();
        $p = $this->openPeriod($e);
        $this->invoice($e, '2031-03-20', 11000, 1000);
        $this->actingAs($this->admin)->get(route('site.commission_payouts_preview', ['employee_id' => $e->id, 'from' => self::FROM, 'to' => self::TO]))
            ->assertSee('اعتماد العمولة الحالية');
        $this->post(route('site.commission_payouts_store'), ['action' => 'refresh', 'period_id' => $p->id])->assertSessionHasNoErrors();
        $this->assertSame('6000.00', $p->fresh()->commission_amount);
        $this->assertEquals(60000, $p->fresh()->calculation['profit']);
        $this->pay($p, 6000)->assertSessionHasNoErrors();
    }

    // ------------------------------------------------------------------ access

    public function test_only_finance_can_manage_payouts(): void
    {
        $e = $this->fivethousand();
        $p = $this->openPeriod($e);
        $this->pay($p, 1000)->assertSessionHasNoErrors();
        $payout = CommissionPayout::where('period_id', $p->id)->firstOrFail();
        $count = [CommissionPeriod::count(), CommissionPayout::count(), Bond::count()];

        $this->actingAs($e);
        $this->get(route('site.commission_payouts'))->assertForbidden();
        $this->get(route('site.commission_payouts_preview', ['employee_id' => $e->id, 'from' => self::FROM, 'to' => self::TO]))->assertForbidden();
        $this->get(route('site.commission_payouts_receipt', $payout->id))->assertForbidden();
        $this->post(route('site.commission_payouts_store'), ['action' => 'open', 'employee_id' => $e->id, 'from' => '2031-05-01', 'to' => '2031-05-31'])->assertForbidden();
        $this->post(route('site.commission_payouts_store'), ['action' => 'pay', 'period_id' => $p->id, 'amount' => 100, 'storage_id' => $this->storage,
            'money_way' => 1, 'paid_date' => '2031-04-02', 'request_token' => 'x'])->assertForbidden();
        $this->post(route('site.commission_payouts_reverse', $payout->id))->assertForbidden();
        $this->get(route('site.index'))->assertDontSee(route('site.commission_payouts'), false)->assertSee(route('site.commission_payouts_mine'), false);
        $this->assertSame($count, [CommissionPeriod::count(), CommissionPayout::count(), Bond::count()]);

        auth()->logout();
        $this->get(route('site.commission_payouts'))->assertRedirect(route('login'));
    }

    public function test_an_employee_sees_only_their_own_payout_history(): void
    {
        [$me, $other] = [$this->fivethousand(), $this->fivethousand()];
        $this->pay($this->openPeriod($me), 1200)->assertSessionHasNoErrors();
        $this->pay($this->openPeriod($other), 3400)->assertSessionHasNoErrors();
        $this->actingAs($me)->get(route('site.commission_payouts_mine', ['employee_id' => $other->id]))->assertOk()
            ->assertSee('1,200.00')->assertDontSee('3,400.00');
    }

    public function test_forged_ids_are_refused(): void
    {
        $p = $this->openPeriod($this->fivethousand());
        $this->actingAs($this->admin);
        $this->pay($p, 100, ['period_id' => 999999999])->assertSessionHasErrors('period_id');
        $this->pay($p, 100, ['storage_id' => 999999999])->assertSessionHasErrors('storage_id');
        $this->post(route('site.commission_payouts_store'), ['action' => 'something', 'period_id' => $p->id])->assertStatus(422);
        $this->post(route('site.commission_payouts_reverse', 999999999))->assertNotFound();
        $this->get(route('site.commission_payouts_receipt', 999999999))->assertNotFound();
        $this->assertSame(0, $p->payouts()->count());
    }

    // ------------------------------------------------------------------ data safety

    public function test_history_is_unchanged(): void
    {
        $tables = ['invoices', 'ticket_users', 'ticket_vendors', 'users', 'commission_tier_tables', 'commission_tiers'];
        $snapshot = fn () => collect($tables)->mapWithKeys(fn ($t) => [$t => [DB::select("CHECKSUM TABLE `$t`")[0]->Checksum, DB::table($t)->count()]])->all();
        $maxBond = (int) Bond::max('id');
        $maxStatement = (int) DB::table('account_statements')->max('id');
        $old = fn () => md5(json_encode([DB::table('bonds')->where('id', '<=', $maxBond)->orderBy('id')->get(),
            DB::table('account_statements')->where('id', '<=', $maxStatement)->orderBy('id')->get()]));
        $manual81 = fn () => md5(json_encode(DB::table('bonds')->where('to_account', 81)->where('id', '<=', $maxBond)->orderBy('id')->get()));
        [$before, $oldRows, $account81] = [$snapshot(), $old(), $manual81()];

        $e = $this->emp();
        $this->invoice($e, '2026-09-10', 5000, 1000);                           // (the only changes: this test invoice ...)
        $invoices = $snapshot();
        $this->open($e, '2026-09-01', '2026-09-30')->assertSessionHasNoErrors();
        $p = $this->period($e, '2026-09-01', '2026-09-30');
        $this->pay($p, 200)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('site.commission_payouts_reverse', CommissionPayout::where('period_id', $p->id)->value('id')))->assertSessionHasNoErrors();
        $this->pay($p, 400)->assertSessionHasNoErrors();

        $this->assertSame($invoices, $snapshot(), 'no invoice / user / tier change by the payouts');
        $this->assertSame($oldRows, $old(), 'existing vouchers and statement rows untouched');
        $this->assertSame($account81, $manual81(), 'the manual #81 vouchers untouched');
        $this->assertNotSame($before['invoices'], $invoices['invoices']);          // (sanity: the snapshot sees changes)
    }
}
