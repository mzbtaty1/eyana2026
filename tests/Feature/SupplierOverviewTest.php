<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{Supplier, User};
use App\Services\SupplierDirectory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Account overview (/suppliers/{id}/overview, Phase 3 step 1): read-only, admin-only;
 * balance and totals from the ledger, the same as the account statement; role, counts
 * and last activity the same as the list. Dev database, rolled back.
 */
class SupplierOverviewTest extends TestCase
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

    public function test_every_account_overview_matches_its_account_statement_and_the_list(): void
    {
        $list = SupplierDirectory::rows()->keyBy(fn ($r) => $r->account->id);
        foreach (Supplier::orderBy('id')->get() as $account) {   // expense accounts too
            $id = $account->id;
            $res = $this->get(route('site.suppliers_overview', $id))->assertOk();
            $row = $res->viewData('row');
            $this->assertSame($id, $row->account->id);

            // ledger figures = the account statement's
            $st = $this->post(route('site.accounts_statement_search'), ['invoice_beneficiaries' => $id]);
            $d = $st->viewData('total_debit_balance');
            $c = $st->viewData('total_credit_balance');
            $this->assertSame(number_format($d, 2), number_format($row->total_debit, 2), "$id debit");
            $this->assertSame(number_format($c, 2), number_format($row->total_credit, 2), "$id credit");
            $this->assertSame(number_format($d - $c, 2), number_format($row->balance, 2), "$id balance");

            // role, counts, last activity = the list row (expense accounts are not on the list)
            if ($list->has($id)) {
                $l = $list->get($id);
                foreach (['role', 'invoices_as_supplier', 'invoices_as_customer', 'last_activity', 'balance', 'total_debit', 'total_credit', 'system', 'phones'] as $f) {
                    $this->assertSame($l->$f, $row->$f, "$id $f");
                }
            }

            // what the page shows
            $html = $res->getContent();
            $this->assertStringContainsString(number_format($row->total_debit, 2), $html);
            $this->assertStringContainsString(number_format($row->total_credit, 2), $html);
            $shown = number_format(abs($row->balance), 2);
            $label = $row->balance > 0 ? 'لنا' : ($row->balance < 0 ? 'علينا' : 'صفر');
            $this->assertMatchesRegularExpression('/id="ovBalance">\s*<span[^>]*>' . preg_quote($shown, '/') . '<\/span>\s*<span[^>]*>' . $label . '</u', $html, "$id balance shown");
        }
    }

    public function test_links_and_statement_form(): void
    {
        foreach ([4, 16] as $id) {   // Counter Customer (both roles), busiest supplier
            $res = $this->get(route('site.suppliers_overview', $id))->assertOk();
            $row = $res->viewData('row');
            $html = $res->getContent();

            $this->assertMatchesRegularExpression('/<form id="statementForm" action="' . preg_quote(route('site.accounts_statement_search'), '/')
                . '" method="POST" target="_blank"[^>]*>\s*<input type="hidden" name="_token"[^>]*>\s*<input type="hidden" name="invoice_beneficiaries" value="' . $id . '">/', $html);
            $this->assertStringContainsString('href="' . route('site.suppliers_edit', $id) . '"', $html);
            $this->assertSame($row->invoices_as_supplier > 0, str_contains($html, e(route('site.invoices_full_report', ['supplier_id' => $id]))), "$id supplier link");
            $this->assertSame($row->invoices_as_customer > 0, str_contains($html, e(route('site.invoices_full_report', ['customer_id' => $id]))), "$id customer link");
        }
        $this->assertStringContainsString('حساب نظام', $this->get(route('site.suppliers_overview', 4))->getContent());
        $this->assertStringNotContainsString('<i class="ri-lock-2-line"></i> حساب نظام', $this->get(route('site.suppliers_overview', 16))->getContent());
    }

    public function test_the_list_links_every_name_to_its_overview(): void
    {
        $html = $this->get(route('site.suppliers'))->assertOk()->getContent();
        foreach (SupplierDirectory::rows() as $r) {
            $this->assertStringContainsString('<a href="' . route('site.suppliers_overview', $r->account->id) . '" class="text-body" title="تفاصيل الحساب">' . e($r->account->name) . '</a>', $html);
        }
    }

    public function test_query_count_does_not_grow_with_the_account(): void
    {
        $counts = [];
        foreach ([16, 4, Supplier::where('acc_type', 3)->value('id'), $this->quietAccountId()] as $id) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('site.suppliers_overview', $id))->assertOk();
            $counts[$id] = count(DB::getQueryLog());
        }
        fwrite(STDERR, "\n[info] overview queries per account: " . json_encode($counts) . "\n");
        // the invoice analysis (step 4) loads afterwards from its own URL -- SupplierOverviewInvoicesTest
        $this->assertCount(1, array_unique($counts), 'same number of queries for every account');
        $this->assertLessThanOrEqual(16, max($counts));
    }

    private function quietAccountId(): int
    {
        // the account with the fewest ledger rows (none, when such an account exists)
        return (int) DB::table('suppliers as s')->where('s.acc_type', 2)
            ->leftJoin('account_statements as a', 'a.supp_client_id', '=', 's.id')
            ->groupBy('s.id')->orderByRaw('COUNT(a.id)')->orderBy('s.id')->value('s.id');
    }

    public function test_access_and_missing_accounts(): void
    {
        $this->get(route('site.suppliers_overview', 999999999))->assertNotFound();
        $this->get('/suppliers/abc/overview')->assertNotFound();

        $employee = User::where('account_type', 1)->where('status', 1)->firstOrFail();
        $this->actingAs($employee)->get(route('site.suppliers_overview', 4))->assertForbidden();
        $this->actingAs($employee)->get(route('site.suppliers_overview', 16))->assertForbidden();
    }

    public function test_the_overview_changes_nothing(): void
    {
        $snap = fn () => [DB::table('suppliers')->count(), DB::table('account_statements')->count(), DB::table('logs')->count(),
            DB::table('suppliers')->where('id', 16)->value('updated_at')];
        $before = $snap();
        $this->get(route('site.suppliers_overview', 16))->assertOk();
        $this->post('/suppliers/16/overview')->assertStatus(405);   // GET only
        $this->assertEquals($before, $snap());
    }
}
