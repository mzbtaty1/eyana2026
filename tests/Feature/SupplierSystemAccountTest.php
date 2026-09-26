<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{AccountStatement, Supplier, User};
use App\Services\{CounterPayments, SupplierDirectory};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Counter Customer as a fixed system account (config eyana.counter_customer_ids):
 * always listed and first, never deleted, account type / type fixed; its id and
 * history untouched. Dev database, every test rolled back.
 */
class SupplierSystemAccountTest extends TestCase
{
    use DatabaseTransactions;

    private Supplier $counter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->actingAs(User::where('account_type', 2)->where('status', 1)->firstOrFail());
        $this->assertSame([4], CounterPayments::counterIds(), 'dev config: Counter Customer is account 4');
        $this->counter = Supplier::findOrFail(4);
    }

    /** Fields the real edit page submits (read from its HTML, like a browser would). */
    private function editFormFields(Supplier $acc): array
    {
        $html = $this->get(route('site.suppliers_edit', $acc->id))->assertOk()->getContent();
        $form = substr($html, strpos($html, route('site.suppliers_update')));
        $form = substr($form, 0, strpos($form, '</form>'));
        $fields = [];
        preg_match_all('/<input[^>]*>/', $form, $m);
        foreach ($m[0] as $tag) {
            if (preg_match('/\bname="([^"]+)"/', $tag, $n) && ! preg_match('/\bdisabled\b/', $tag)) {
                $fields[$n[1]] = preg_match('/\bvalue="([^"]*)"/', $tag, $v) ? html_entity_decode($v[1], ENT_QUOTES) : '';
            }
        }
        preg_match_all('/<select name="([^"]+)"[^>]*>.*?<\/select>/s', $form, $m, PREG_SET_ORDER);
        foreach ($m as $s) {
            if (preg_match('/<select[^>]*\bdisabled\b/', $s[0])) {
                continue; // a disabled select is not submitted
            }
            preg_match('/<option value="([^"]*)"[^>]*selected/', $s[0], $sel);
            $fields[$s[1]] = $sel[1];
        }
        return $fields;
    }

    private function snapshot(): array
    {
        return [
            'account' => (array) DB::table('suppliers')->where('id', 4)->first(),
            'ledger' => DB::table('account_statements')->where('supp_client_id', 4)->where('is_storage', '!=', 1)
                ->selectRaw('COUNT(*) n, SUM(debit_balance) d, SUM(credit_balance) c')->first(),
            'invoices' => DB::table('invoices')->where('invoice_beneficiaries', 4)->count(),
        ];
    }

    // ---------------------------------------------------------------- list

    public function test_counter_customer_is_listed_first_highlighted_and_undeletable_in_the_list(): void
    {
        $res = $this->get(route('site.suppliers'))->assertOk();
        $rows = $res->viewData('rows');
        $this->assertSame(4, $rows->first()->account->id, 'first row');
        $this->assertTrue($rows->first()->system);
        $this->assertSame(1, $rows->where('system', true)->count());

        $html = $res->getContent();
        $body = substr($html, strpos($html, '<tbody>'));
        $this->assertMatchesRegularExpression('/^<tbody>\s*<tr class="ey-system-row"\s+data-id="4"\s+data-system="1"/', $body, 'first <tr> of the table');
        $this->assertStringContainsString("orderFixed: { pre: [[0, 'asc']] }", $html);
        $this->assertStringContainsString("if (d.system === '1') { return true; }", $html);
        $this->assertStringNotContainsString('id="delete-form-4"', $html);
        $this->assertStringContainsString('حساب نظام لا يُحذف', $html);
    }

    public function test_counter_customer_stays_listed_even_if_suspended_or_marked_as_expense(): void
    {
        DB::table('suppliers')->where('id', 4)->update(['status' => 0, 'acc_type' => 3]);
        $rows = SupplierDirectory::rows();
        $this->assertSame(4, $rows->first()->account->id);
        $this->assertTrue($rows->first()->system);
    }

    public function test_system_accounts_follow_the_config(): void
    {
        $other = Supplier::where('id', '!=', 4)->where('acc_type', 2)->firstOrFail();
        config(['eyana.counter_customer_ids' => [$other->id]]);
        $this->assertTrue($other->isSystemAccount());
        $this->assertFalse($this->counter->isSystemAccount());
        $this->assertSame($other->id, SupplierDirectory::rows()->first()->account->id);
    }

    // ---------------------------------------------------------------- delete

    public function test_counter_customer_cannot_be_deleted_from_any_route(): void
    {
        $before = $this->snapshot();
        foreach (['site.suppliers_delete', 'site.customers_delete', 'site.expenses_delete'] as $route) {
            $this->post(route($route, 4))->assertSessionHasErrors('delete');
            $this->assertStringContainsString('حساب نظام', session('errors')->first('delete'), $route);
            $this->assertNotNull(Supplier::find(4), $route);
        }
        $this->assertEquals($before, $this->snapshot());
    }

    public function test_a_system_account_is_protected_even_without_any_activity(): void
    {
        $name = 'TEST-SYSTEM-' . uniqid();
        $this->post(route('site.suppliers_save'), [
            'name' => $name, 'phone_1' => '01000000000', 'limit_balance' => '0',
            'debit_opening_balance' => '0', 'opening_credit_balance' => '0',
            'status' => '1', 'type' => '1', 'in_index' => '0', 'in_stat' => '0',
        ])->assertRedirect();
        $acc = Supplier::where('name', $name)->firstOrFail();
        $this->assertSame([], $acc->deletionBlockers(), 'deletable as an ordinary account');

        config(['eyana.counter_customer_ids' => [$acc->id]]);
        $this->assertSame(['حساب نظام (عميل كونتر) لا يُحذف'], $acc->deletionBlockers());
        $this->post(route('site.suppliers_delete', $acc->id))->assertSessionHasErrors('delete');
        $this->assertNotNull($acc->fresh());
    }

    // ---------------------------------------------------------------- update

    public function test_the_real_edit_form_saves_the_counter_customer_without_changing_its_type(): void
    {
        $fields = $this->editFormFields($this->counter);
        $this->assertSame((string) $this->counter->acc_type, $fields['acc_type'], 'hidden fixed acc_type');
        $this->assertSame((string) $this->counter->type, $fields['type'], 'hidden fixed type');

        $this->post(route('site.suppliers_update'), array_merge($fields, ['phone_2' => '01099999999']))
            ->assertSessionDoesntHaveErrors(['acc_type', 'type'])->assertSessionHasErrors('msg');
        $fresh = $this->counter->fresh();
        $this->assertSame('01099999999', $fresh->phone_2, 'other fields stay editable');
        $this->assertSame((string) $this->counter->acc_type, (string) $fresh->acc_type);
        $this->assertSame((string) $this->counter->type, (string) $fresh->type);
    }

    public function test_changing_the_counter_customer_type_is_refused_on_every_route(): void
    {
        $before = $this->snapshot();
        $fields = $this->editFormFields($this->counter);

        $this->post(route('site.suppliers_update'), array_merge($fields, ['acc_type' => '1']))->assertSessionHasErrors('acc_type');
        $this->post(route('site.suppliers_update'), array_merge($fields, ['type' => '2']))->assertSessionHasErrors('acc_type');
        $this->assertStringContainsString('حساب نظام', session('errors')->first('acc_type'));

        $customer = ['id' => 4, 'name' => $this->counter->name, 'phone_1' => '0', 'debit_opening_balance' => $this->counter->debit_opening_balance,
            'opening_credit_balance' => $this->counter->opening_credit_balance, 'status' => 1, 'type' => $this->counter->type];
        $this->post(route('site.customers_update'), $customer + ['acc_type' => 1])->assertSessionHasErrors('acc_type');

        $this->assertEquals($before, $this->snapshot(), 'nothing saved');

        // the expenses form always saves type 1: refused when that would change it
        DB::table('suppliers')->where('id', 4)->update(['type' => 2]);
        $this->post(route('site.expenses_update'), ['id' => 4, 'name' => $this->counter->name,
            'debit_opening_balance' => $this->counter->debit_opening_balance])->assertSessionHasErrors('acc_type');
        $this->assertSame('2', (string) Supplier::find(4)->type);
    }

    public function test_the_edit_page_locks_the_counter_customer_as_active(): void
    {
        $html = $this->get(route('site.suppliers_edit', 4))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<select name="status"[^>]*\bdisabled\b/', $html, 'status select locked');
        $this->assertStringContainsString('ولا إيقافه', $html);
        $this->assertSame('1', $this->editFormFields($this->counter)['status'], 'the form always submits active');

        // an ordinary account keeps an editable status
        $other = Supplier::where('id', '!=', 4)->where('acc_type', 2)->firstOrFail();
        $this->assertDoesNotMatchRegularExpression('/<select name="status"[^>]*\bdisabled\b/',
            $this->get(route('site.suppliers_edit', $other->id))->assertOk()->getContent());
    }

    public function test_suspending_the_counter_customer_is_refused_on_every_route(): void
    {
        $before = $this->snapshot();

        $this->post(route('site.suppliers_update'), array_merge($this->editFormFields($this->counter), ['status' => '0']))
            ->assertSessionHasErrors('acc_type');
        $this->assertStringContainsString('لا يمكن إيقافه', session('errors')->first('acc_type'));

        $this->post(route('site.customers_update'), ['id' => 4, 'name' => $this->counter->name, 'phone_1' => '0',
            'debit_opening_balance' => $this->counter->debit_opening_balance, 'opening_credit_balance' => $this->counter->opening_credit_balance,
            'status' => 0, 'type' => $this->counter->type, 'acc_type' => $this->counter->acc_type])
            ->assertSessionHasErrors('acc_type');
        $this->assertStringContainsString('لا يمكن إيقافه', session('errors')->first('acc_type'));

        $this->assertSame(1, (int) Supplier::find(4)->status);
        $this->assertEquals($before, $this->snapshot(), 'nothing saved');
    }

    public function test_an_ordinary_account_can_still_be_suspended(): void
    {
        $other = Supplier::where('id', '!=', 4)->where('acc_type', 2)->where('status', 1)->firstOrFail();
        $this->post(route('site.suppliers_update'), array_merge($this->editFormFields($other), ['status' => '0']))
            ->assertSessionDoesntHaveErrors(['acc_type', 'status'])->assertSessionHasErrors('msg');
        $this->assertSame(0, (int) $other->fresh()->status);
    }

    public function test_account_and_every_ledger_row_are_untouched_by_a_refused_save(): void
    {
        $rows = fn () => AccountStatement::where('supp_client_id', 4)->orderBy('id')->get()->toArray();
        $ledger = $rows();
        $account = (array) DB::table('suppliers')->where('id', 4)->first();
        $this->assertNotEmpty($ledger);

        $fields = $this->editFormFields($this->counter);
        $this->post(route('site.suppliers_update'), array_merge($fields, ['acc_type' => '1', 'debit_opening_balance' => '1']))
            ->assertSessionHasErrors('acc_type');

        $this->assertEquals($ledger, $rows(), 'ledger rows');
        $this->assertEquals($account, (array) DB::table('suppliers')->where('id', 4)->first(), 'account row');
    }
}
