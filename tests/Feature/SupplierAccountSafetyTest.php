<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{AccountStatement, Supplier, User};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\UpdateSupplierRequest;
use Tests\TestCase;

/**
 * Suppliers page safety (phase C): admin-only access, no deleting accounts with
 * activity, decimal / negative opening balances. Runs against the dev database;
 * every test is wrapped in a transaction and rolled back.
 */
class SupplierAccountSafetyTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->admin = User::where('account_type', 2)->where('status', 1)->firstOrFail();
        $this->employee = User::where('account_type', 1)->where('status', 1)->firstOrFail();
    }

    private function payload(array $over = []): array
    {
        return array_merge([
            'name' => 'TEST-SUPPLIER-' . uniqid(),
            'email' => null, 'address' => null,
            'phone_1' => '01000000000', 'phone_2' => null,
            'limit_balance' => '0',
            'debit_opening_balance' => '0', 'opening_credit_balance' => '0',
            'status' => '1', 'type' => '2', 'in_index' => '0', 'in_stat' => '0',
        ], $over);
    }

    /** Creates an account through the real form (as admin) and returns it. */
    private function makeAccount(array $over = []): Supplier
    {
        $p = $this->payload($over);
        $this->actingAs($this->admin)->post(route('site.suppliers_save'), $p)->assertRedirect();
        return Supplier::where('name', $p['name'])->firstOrFail();
    }

    /** Inserts a copy of an existing row of $table (all NOT NULL columns filled) with $over applied. */
    private function cloneRow(string $table, array $over, array $where = []): void
    {
        $row = (array) DB::table($table)->where($where)->orderByDesc('id')->first();
        $this->assertNotEmpty($row, "no $table row to copy");
        unset($row['id']);
        DB::table($table)->insert(array_merge($row, $over));
    }

    private function openingRow(Supplier $s): AccountStatement
    {
        return AccountStatement::where('supp_client_id', $s->id)->where('is_supp_account', 1)->firstOrFail();
    }

    // ---------------------------------------------------------------- access

    public function test_employee_cannot_open_or_change_supplier_pages(): void
    {
        $acc = $this->makeAccount();

        $this->actingAs($this->employee);
        $this->get(route('site.suppliers'))->assertForbidden();
        $this->get(route('site.suppliers_create'))->assertForbidden();
        $this->get(route('site.suppliers_edit', $acc->id))->assertForbidden();

        $p = $this->payload();
        $this->post(route('site.suppliers_save'), $p)->assertForbidden();
        $this->assertFalse(Supplier::where('name', $p['name'])->exists());

        $this->post(route('site.suppliers_update'), $this->payload(['id' => $acc->id, 'name' => 'HACKED', 'acc_type' => 2]))->assertForbidden();
        $this->assertSame($acc->name, $acc->fresh()->name);

        $this->post(route('site.suppliers_delete', $acc->id))->assertForbidden();
        $this->assertNotNull($acc->fresh());
    }

    public function test_admin_can_open_supplier_pages(): void
    {
        $acc = $this->makeAccount();
        $this->actingAs($this->admin);
        $this->get(route('site.suppliers'))->assertOk();
        $this->get(route('site.suppliers_create'))->assertOk();
        $this->get(route('site.suppliers_edit', $acc->id))->assertOk();
    }

    // ---------------------------------------------------------------- delete guard

    public function test_account_with_only_a_zero_opening_balance_can_be_deleted(): void
    {
        $acc = $this->makeAccount();
        $this->assertSame([], $acc->deletionBlockers());

        $this->actingAs($this->admin)->post(route('site.suppliers_delete', $acc->id))
            ->assertRedirect()->assertSessionHasErrors('msg');
        $this->assertNull($acc->fresh());
    }

    public function test_non_zero_opening_balance_blocks_delete(): void
    {
        $acc = $this->makeAccount(['debit_opening_balance' => '250.50']);
        $this->actingAs($this->admin)->post(route('site.suppliers_delete', $acc->id))
            ->assertRedirect()->assertSessionHasErrors('delete');
        $this->assertNotNull($acc->fresh());
        $this->assertStringContainsString('كشف الحساب', session('errors')->first('delete'));
    }

    public function test_ledger_row_blocks_delete_but_storage_row_with_same_id_does_not(): void
    {
        $acc = $this->makeAccount();
        // a storage row whose storage id equals this account's id is not the account's activity
        $this->cloneRow('account_statements', ['supp_client_id' => $acc->id, 'trans_storage' => 1, 'is_storage' => 1,
            'is_supp_account' => 0, 'invoice_type' => 9, 'es_id' => 'FLY-BD-TEST', 'debit_balance' => 0, 'credit_balance' => 100,
            'ledger_net_effect' => -100, 'transaction_type' => 2]);
        $this->assertSame([], $acc->deletionBlockers());

        $this->cloneRow('account_statements', ['supp_client_id' => $acc->id, 'trans_storage' => 1, 'is_storage' => 0,
            'is_supp_account' => 0, 'invoice_type' => 9, 'es_id' => 'FLY-BD-TEST', 'debit_balance' => 100, 'credit_balance' => 0,
            'ledger_net_effect' => 100, 'transaction_type' => 2]);
        $this->assertCount(1, $acc->deletionBlockers());
    }

    public function test_invoice_as_customer_blocks_delete(): void
    {
        $acc = $this->makeAccount();
        $this->cloneRow('invoices', ['es_id' => 'FLY-TEST-C', 'ticket_system_id' => 'TSTC' . uniqid(),
            'invoice_beneficiaries' => $acc->id]);

        $this->actingAs($this->admin)->post(route('site.suppliers_delete', $acc->id))->assertSessionHasErrors('delete');
        $this->assertNotNull($acc->fresh());
        $this->assertStringContainsString('فاتورة كعميل', session('errors')->first('delete'));
    }

    public function test_invoice_as_supplier_blocks_delete_but_unlinked_vendor_row_does_not(): void
    {
        $acc = $this->makeAccount();
        // ticket_vendors row left by an unsaved invoice form: not an invoice
        DB::table('ticket_vendors')->insert(['ticket_system_id' => 'TSTU' . uniqid(), 'vendor_id' => $acc->id, 'price' => 10]);
        $this->assertSame([], $acc->deletionBlockers());

        $sys = 'TSTV' . uniqid();
        DB::table('ticket_vendors')->insert(['ticket_system_id' => $sys, 'vendor_id' => $acc->id, 'price' => 10]);
        $this->cloneRow('invoices', ['es_id' => 'FLY-TEST-V', 'ticket_system_id' => $sys,
            'invoice_beneficiaries' => $this->makeAccount()->id]);

        $this->actingAs($this->admin)->post(route('site.suppliers_delete', $acc->id))->assertSessionHasErrors('delete');
        $this->assertNotNull($acc->fresh());
        $this->assertStringContainsString('فاتورة كمورد', session('errors')->first('delete'));
    }

    public function test_voucher_blocks_delete_in_both_directions(): void
    {
        foreach (['from', 'to'] as $side) {
            $acc = $this->makeAccount();
            $other = $side === 'from' ? 'to' : 'from';
            $this->cloneRow('bonds', ['es_id' => 'FLY-TEST-B',
                "{$side}_account" => $acc->id, "{$side}_type" => 'supplier',
                "{$other}_account" => 1, "{$other}_type" => 'storage', 'amount' => 50]);
            $blockers = $acc->deletionBlockers();
            $this->assertCount(1, $blockers, $side);
            $this->assertStringContainsString('سند', $blockers[0]);
        }
    }

    public function test_customers_and_expenses_delete_routes_use_the_same_guard(): void
    {
        foreach (['site.customers_delete', 'site.expenses_delete'] as $route) {
            $acc = $this->makeAccount(['opening_credit_balance' => '75']);
            $this->actingAs($this->admin)->post(route($route, $acc->id))->assertSessionHasErrors('delete');
            $this->assertNotNull($acc->fresh(), $route);
        }
    }

    public function test_real_accounts_with_activity_are_all_blocked(): void
    {
        $busy = DB::table('suppliers as s')->whereExists(fn ($q) => $q->from('invoices')->whereColumn('invoice_beneficiaries', 's.id'))
            ->orWhereExists(fn ($q) => $q->from('ticket_vendors as t')->join('invoices as i', 'i.ticket_system_id', '=', 't.ticket_system_id')->whereColumn('t.vendor_id', 's.id'))
            ->orWhereExists(fn ($q) => $q->from('bonds as b')->where(fn ($w) => $w->where(fn ($x) => $x->where('b.from_type', 'supplier')->whereColumn('b.from_account', 's.id'))
                ->orWhere(fn ($x) => $x->where('b.to_type', 'supplier')->whereColumn('b.to_account', 's.id'))))
            ->pluck('s.id');
        $this->assertGreaterThan(200, $busy->count());

        $unblocked = Supplier::whereIn('id', $busy)->get()->filter(fn ($s) => $s->deletionBlockers() === []);
        $this->assertCount(0, $unblocked, 'busy accounts not blocked: ' . $unblocked->pluck('id')->implode(','));

        fwrite(STDERR, sprintf("\n[info] accounts: %d, with activity (blocked): %d, deletable now: %d\n",
            Supplier::count(), Supplier::get()->filter(fn ($s) => $s->deletionBlockers() !== [])->count(),
            Supplier::get()->filter(fn ($s) => $s->deletionBlockers() === [])->count()));
    }

    // ---------------------------------------------------------------- opening balance

    public function test_decimal_opening_balance_is_saved_to_account_and_ledger(): void
    {
        $acc = $this->makeAccount(['debit_opening_balance' => '1500.50', 'opening_credit_balance' => '0']);
        $this->assertSame('1500.50', (string) $acc->debit_opening_balance);
        $row = $this->openingRow($acc);
        $this->assertEquals(1500.50, (float) $row->debit_balance);
        $this->assertEquals(1500.50, (float) $row->ledger_net_effect);

        $this->actingAs($this->admin)->post(route('site.suppliers_update'), $this->payload([
            'id' => $acc->id, 'name' => $acc->name, 'acc_type' => 2,
            'debit_opening_balance' => '200.25', 'opening_credit_balance' => '10.5',
        ]))->assertSessionHasErrors('msg');
        $row = $this->openingRow($acc);
        $this->assertEquals(200.25, (float) $row->debit_balance);
        $this->assertEquals(10.50, (float) $row->credit_balance);
        $this->assertEquals(189.75, (float) $row->ledger_net_effect);
    }

    public function test_invalid_opening_balances_are_rejected_with_a_message(): void
    {
        foreach (['-200' => 'سالب', '1.234' => 'عشريان', 'abc' => 'رقماً', '1e3' => 'عشريان'] as $bad => $expect) {
            foreach (['debit_opening_balance', 'opening_credit_balance'] as $field) {
                $p = $this->payload([$field => $bad]);
                $res = $this->actingAs($this->admin)->post(route('site.suppliers_save'), $p);
                $res->assertSessionHasErrors($field);
                $this->assertStringContainsString($expect, session('errors')->first($field), "$field=$bad");
                $this->assertFalse(Supplier::where('name', $p['name'])->exists(), "$field=$bad saved");
            }
        }

        $acc = $this->makeAccount(['debit_opening_balance' => '100']);
        $this->actingAs($this->admin)->post(route('site.suppliers_update'), $this->payload([
            'id' => $acc->id, 'name' => $acc->name, 'acc_type' => 2, 'debit_opening_balance' => '-100',
        ]))->assertSessionHasErrors('debit_opening_balance');
        $this->assertEquals(100, (float) $this->openingRow($acc)->debit_balance);
    }

    public function test_every_existing_account_still_passes_the_new_opening_balance_rules(): void
    {
        $rules = array_intersect_key((new UpdateSupplierRequest)->rules(), array_flip(['debit_opening_balance', 'opening_credit_balance']));
        $bad = Supplier::get()->filter(fn ($s) => Validator::make([
            'debit_opening_balance' => $s->debit_opening_balance, 'opening_credit_balance' => $s->opening_credit_balance,
        ], $rules)->fails());
        $this->assertCount(0, $bad, 'accounts that could no longer be edited: ' . $bad->pluck('id')->implode(','));
    }
}
