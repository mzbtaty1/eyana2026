<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{AccountStatement, Invoice, Supplier, TicketVendor, User};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Suppliers list (/suppliers, phase 1): role, balance, last activity and invoice
 * counts from grouped queries; expense accounts left out; placeholder phones hidden;
 * Arabic-keyboard money input. Every value is checked against an independent
 * per-account calculation and the account statement, on the dev database (rolled back).
 */
class SupplierListTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->admin = User::where('account_type', 2)->where('status', 1)->firstOrFail();
        $this->actingAs($this->admin);
    }

    private function listRows()
    {
        return $this->get(route('site.suppliers'))->assertOk()->viewData('rows');
    }

    public function test_list_has_every_account_except_expense_accounts_with_constant_queries(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $res = $this->get(route('site.suppliers'))->assertOk();
        $queries = count(DB::getQueryLog());
        fwrite(STDERR, "\n[info] /suppliers queries: $queries\n");
        $this->assertLessThanOrEqual(8, $queries);

        $ids = $res->viewData('rows')->map(fn ($r) => $r->account->id)->sort()->values()->all();
        $this->assertSame(Supplier::where('acc_type', '!=', 3)->orderBy('id')->pluck('id')->all(), $ids);
        $this->assertSame(Supplier::where('acc_type', 3)->count(), $res->viewData('expenseAccounts'));
        foreach (Supplier::where('acc_type', 3)->pluck('id') as $id) {
            $this->assertStringNotContainsString('data-id="' . $id . '"', $res->getContent(), "expense account $id listed");
        }
    }

    public function test_every_row_matches_per_account_values_and_the_account_statement(): void
    {
        foreach ($this->listRows() as $row) {
            $id = $row->account->id;
            $ctx = "account $id";

            $ledger = AccountStatement::where('supp_client_id', $id)->where('is_storage', '!=', 1);
            $sums = (clone $ledger)->selectRaw('COALESCE(SUM(debit_balance),0) d, COALESCE(SUM(credit_balance),0) c')->first();
            $this->assertSame(number_format($sums->d - $sums->c, 2), number_format($row->balance, 2), "$ctx balance");

            $last = (clone $ledger)->where(fn ($q) => $q->where('es_id', '!=', 'FLY-OPEN-BALANCE')
                ->orWhere('debit_balance', '!=', 0)->orWhere('credit_balance', '!=', 0))->max('crt_date');
            $this->assertSame($last, $row->last_activity, "$ctx last activity");

            $asSup = Invoice::whereIn('ticket_system_id', TicketVendor::where('vendor_id', $id)->select('ticket_system_id'))->count();
            $asCus = Invoice::where('invoice_beneficiaries', $id)->count();
            $this->assertSame($asSup, $row->invoices_as_supplier, "$ctx as supplier");
            $this->assertSame($asCus, $row->invoices_as_customer, "$ctx as customer");
            $this->assertSame($asSup && $asCus ? 'both' : ($asSup ? 'supplier' : ($asCus ? 'customer' : null)), $row->role, "$ctx role");

            // the account statement shows the same balance
            $st = $this->post(route('site.accounts_statement_search'), ['invoice_beneficiaries' => $id]);
            $closing = $st->viewData('total_debit_balance') - $st->viewData('total_credit_balance');
            $this->assertSame(number_format($closing, 2), number_format($row->balance, 2), "$ctx statement");
        }
    }

    public function test_page_is_arabic_hides_placeholders_and_labels_balances(): void
    {
        $res = $this->get(route('site.suppliers'))->assertOk();
        $html = $res->getContent();
        $rows = $res->viewData('rows');

        $this->assertStringContainsString('بحث بالاسم أو الهاتف', $html);
        $this->assertStringContainsString("next: 'التالي'", $html);
        $this->assertStringContainsString('layout: { topEnd: null }', $html, 'English built-in search box removed');
        $this->assertStringNotContainsString('رقم الباسبور', $html);

        foreach ($rows as $row) {
            foreach ([$row->account->phone_1, $row->account->phone_2] as $p) {
                if (trim((string) $p) === '' || preg_match('/^0+$/', trim((string) $p))) {
                    $this->assertNotContains($p, $row->phones, "account {$row->account->id} placeholder phone shown");
                }
            }
        }
        $this->assertGreaterThan(0, $rows->filter(fn ($r) => $r->phones === [])->count());

        $pos = $rows->first(fn ($r) => $r->balance > 0);
        $neg = $rows->first(fn ($r) => $r->balance < 0);
        $this->assertMatchesRegularExpression('/' . preg_quote(number_format($pos->balance, 2), '/') . '<\/span>\s*<span class="badge bg-success-subtle text-success">لنا</', $html);
        $this->assertMatchesRegularExpression('/' . preg_quote(number_format(-$neg->balance, 2), '/') . '<\/span>\s*<span class="badge bg-danger-subtle text-danger">علينا</', $html);
        foreach (['supplier', 'customer', 'both'] as $role) {
            $this->assertTrue($rows->contains(fn ($r) => $r->role === $role), "some $role in the data");
        }
    }

    /** Phase 2: every row carries the values the filters use, and the right action buttons. */
    public function test_rows_carry_filter_data_and_action_buttons(): void
    {
        $res = $this->get(route('site.suppliers'))->assertOk();
        $html = $res->getContent();
        $this->assertStringContainsString('id="statementForm" action="' . route('site.accounts_statement_search') . '" method="POST" target="_blank"', $html);

        preg_match_all('/<tr[^>]*data-id="(\d+)"[^>]*>(.*?)<\/tr>/s', $html, $m, PREG_SET_ORDER);
        $trs = [];
        foreach ($m as $t) {
            $trs[(int) $t[1]] = $t[0];
        }
        $rows = $res->viewData('rows');
        $this->assertCount($rows->count(), $trs);

        foreach ($rows as $row) {
            $id = $row->account->id;
            $tr = $trs[$id];
            $attr = fn ($a) => preg_match('/data-' . $a . '="([^"]*)"/', $tr, $x) ? html_entity_decode($x[1], ENT_QUOTES) : null;
            $sign = $row->balance > 0 ? 'pos' : ($row->balance < 0 ? 'neg' : 'zero');

            $this->assertSame($row->role ?? 'none', $attr('role'), "$id role");
            $this->assertSame($sign, $attr('sign'), "$id sign");
            $this->assertSame($row->account->status == 1 ? '1' : '0', $attr('status'), "$id status");
            $this->assertSame((string) $row->last_activity, $attr('last'), "$id last");
            $this->assertStringContainsString(mb_strtolower($row->account->name), $attr('search'), "$id search text");
            foreach ($row->phones as $p) {
                $this->assertStringContainsString($p, $attr('search'), "$id phone searchable");
            }

            $this->assertStringContainsString('class="btn btn-soft-info btn-sm js-statement" data-id="' . $id . '"', $tr, "$id statement");
            $sup = e(route('site.invoices_full_report', ['supplier_id' => $id]));
            $cus = e(route('site.invoices_full_report', ['customer_id' => $id]));
            $this->assertSame($row->invoices_as_supplier > 0, str_contains($tr, $sup), "$id supplier invoices link");
            $this->assertSame($row->invoices_as_customer > 0, str_contains($tr, $cus), "$id customer invoices link");
            $this->assertSame($row->role === 'both', str_contains($tr, 'data-bs-toggle="dropdown"'), "$id invoices menu");

            $remind = $row->balance > 0 && ! $row->system;
            $this->assertSame($remind, str_contains($tr, 'js-remind'), "$id reminder");
            if ($remind) {
                $this->assertStringContainsString('data-amount="' . number_format($row->balance, 2) . '"', $tr);
            }
            $this->assertSame(! $row->system, str_contains($tr, 'id="delete-form-' . $id . '"'), "$id delete");
        }
    }

    // ---------------------------------------------------------------- money input

    private function saveAccount(array $over): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('site.suppliers_save'), array_merge([
            'name' => 'TEST-MONEY-' . uniqid(), 'phone_1' => '01000000000', 'limit_balance' => '0',
            'debit_opening_balance' => '0', 'opening_credit_balance' => '0',
            'status' => '1', 'type' => '2', 'in_index' => '0', 'in_stat' => '0',
        ], $over));
    }

    public function test_arabic_digits_and_arabic_decimal_separator_are_saved_as_typed(): void
    {
        foreach (['١٥٠٠' => 1500, '١٢٫٥' => 12.5, '12٫5' => 12.5, '۱۲۵' => 125, '١٢٣٤٫٥٦' => 1234.56, ' 12.5 ' => 12.5] as $typed => $value) {
            $name = 'TEST-MONEY-' . uniqid();
            $this->saveAccount(['name' => $name, 'debit_opening_balance' => $typed, 'limit_balance' => '١٠٠٠'])
                ->assertRedirect()->assertSessionHasNoErrors();
            $acc = Supplier::where('name', $name)->firstOrFail();
            $this->assertEquals($value, (float) $acc->debit_opening_balance, "typed $typed");
            $this->assertEquals(1000, (float) $acc->limit_balance);
            $row = AccountStatement::where('supp_client_id', $acc->id)->where('is_supp_account', 1)->firstOrFail();
            $this->assertEquals($value, (float) $row->debit_balance, "ledger for $typed");
            $this->assertEquals($value, (float) $row->ledger_net_effect);
        }
    }

    public function test_commas_are_rejected_not_turned_into_another_number(): void
    {
        foreach (['12,5', '1,500', '١٬٥٠٠', '12 5'] as $typed) {
            foreach (['debit_opening_balance', 'opening_credit_balance'] as $field) {
                $name = 'TEST-MONEY-' . uniqid();
                $this->saveAccount(['name' => $name, $field => $typed])->assertSessionHasErrors($field);
                $this->assertStringContainsString('بدون فاصلة', session('errors')->first($field), "$field=$typed");
                $this->assertFalse(Supplier::where('name', $name)->exists(), "$field=$typed saved");
            }
        }
    }

    /** The fields the real edit page submits, with their current values (read from its HTML). */
    private function editFormFields(Supplier $acc): array
    {
        $html = $this->get(route('site.suppliers_edit', $acc->id))->assertOk()->getContent();
        $form = substr($html, strpos($html, route('site.suppliers_update')));
        $form = substr($form, 0, strpos($form, '</form>'));
        $fields = [];
        preg_match_all('/<input[^>]*>/', $form, $m);
        foreach ($m[0] as $tag) {
            if (preg_match('/\bname="([^"]+)"/', $tag, $n)) {
                $fields[$n[1]] = preg_match('/\bvalue="([^"]*)"/', $tag, $v) ? html_entity_decode($v[1], ENT_QUOTES) : '';
            }
        }
        preg_match_all('/<select name="([^"]+)".*?<\/select>/s', $form, $m, PREG_SET_ORDER);
        foreach ($m as $s) {
            preg_match('/<option value="([^"]*)"[^>]*selected/', $s[0], $sel);
            $fields[$s[1]] = $sel[1];
        }
        return $fields;
    }

    public function test_saving_the_real_edit_form_keeps_the_credit_limit_and_accepts_arabic_input(): void
    {
        $name = 'TEST-MONEY-' . uniqid();
        $this->saveAccount(['name' => $name, 'limit_balance' => '5000'])->assertRedirect();
        $acc = Supplier::where('name', $name)->firstOrFail();

        $fields = $this->editFormFields($acc);
        $this->assertArrayNotHasKey('limit_balance', $fields, 'the edit form has no limit field');
        $this->assertArrayHasKey('in_stat', $fields);

        // exactly what the page submits, with the opening balances typed on an Arabic keyboard
        $this->post(route('site.suppliers_update'), array_merge($fields, [
            'debit_opening_balance' => '٢٠٠٫٢٥', 'opening_credit_balance' => '١٠٫٥',
        ]))->assertSessionDoesntHaveErrors(['debit_opening_balance', 'opening_credit_balance', 'limit_balance'])
            ->assertSessionHasErrors('msg'); // the success message channel

        $this->assertEquals(5000, (float) $acc->fresh()->limit_balance, 'limit kept');
        $row = AccountStatement::where('supp_client_id', $acc->id)->where('is_supp_account', 1)->firstOrFail();
        $this->assertEquals(200.25, (float) $row->debit_balance);
        $this->assertEquals(10.5, (float) $row->credit_balance);
        $this->assertEquals(189.75, (float) $row->ledger_net_effect);

        // a limit that is sent is still saved
        $this->post(route('site.suppliers_update'), array_merge($this->editFormFields($acc), ['limit_balance' => '7500']))
            ->assertSessionHasErrors('msg');
        $this->assertEquals(7500, (float) $acc->fresh()->limit_balance, 'limit updated');
    }
}
