<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{Supplier, User};
use App\Services\{AccountInvoices, AccountMovements, InvoiceFullReport, SupplierDirectory};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Account overview, invoice analysis (Phase 3 step 4, loaded after the page opens from
 * /suppliers/{id}/overview/invoices): per role, the detailed invoice
 * report's totals and breakdowns (airline / employee / operation type), shown as the
 * report gives them; checked against the report itself, the list's invoice counts and
 * the account's ledger. Dev DB, rolled back.
 */
class SupplierOverviewInvoicesTest extends TestCase
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

    private function cents($v): int
    {
        return (int) round($v * 100);
    }

    public function test_every_account_matches_the_report_the_list_and_the_ledger(): void
    {
        $edits = DB::table('account_statements')->where('description', 'like', '%"kind":"edit"%')->count();
        $this->assertSame(0, $edits, 'no edit rows on dev (edit adjustments are not in the ledger comparison below)');

        $checked = ['supplier' => 0, 'customer' => 0];
        foreach (Supplier::orderBy('id')->get() as $account) {
            $id = $account->id;
            $row = SupplierDirectory::row($account);
            $inv = AccountInvoices::forAccount($row, $this->admin);
            $kinds = AccountMovements::forAccount($id)['kinds'];
            $k = fn ($kind, $side) => isset($kinds[$kind]) ? $this->cents($kinds[$kind]->$side) : 0;

            foreach (AccountInvoices::ROLES as $role => [$filter, $amountKey, $refundKey]) {
                $count = $row->{'invoices_as_' . $role};
                $this->assertSame($count > 0, isset($inv[$role]), "$id $role shown only with invoices");
                if (! $count) {
                    continue;
                }
                $x = $inv[$role];
                $report = new InvoiceFullReport([$filter => $id], $this->admin);
                $s = $report->summary();

                // exactly the report's figures
                $this->assertSame($s['invoices'], $x->invoices, "$id $role invoices");
                $this->assertSame($s['tickets'], $x->tickets, "$id $role tickets");
                $this->assertSame($this->cents($s[$amountKey]), $this->cents($x->amount), "$id $role amount");
                $this->assertSame($this->cents($s[$refundKey]), $this->cents($x->refund), "$id $role refund");
                $this->assertSame($this->cents($s[$amountKey]) - $this->cents($s[$refundKey]), $this->cents($x->net), "$id $role net");
                foreach (array_keys(AccountInvoices::GROUPS) as $by) {
                    $this->assertSame(array_map(fn ($g) => [$g['label'], $g['invoices'], $g['tickets'], $this->cents($g[$amountKey]), $this->cents($g[$refundKey])], $report->groups($by)),
                        array_map(fn ($g) => [$g->label, $g->invoices, $g->tickets, $this->cents($g->amount), $this->cents($g->refund)], $x->groups[$by]), "$id $role by $by");
                }

                // airline and operation type split the totals exactly (employee may exceed: shared invoices)
                foreach (['airline', 'op'] as $by) {
                    $this->assertSame($this->cents($x->amount), array_sum(array_map(fn ($g) => $this->cents($g->amount), $x->groups[$by])), "$id $role $by amount split");
                    $this->assertSame($this->cents($x->refund), array_sum(array_map(fn ($g) => $this->cents($g->refund), $x->groups[$by])), "$id $role $by refund split");
                }

                // the list's invoice count, and the account's ledger
                $this->assertSame($count, $x->invoices, "$id $role = list count");
                if ($role === 'supplier') {
                    $this->assertSame($k('sale', 'credit') + $k('reissue', 'credit'), $this->cents($x->amount), "$id purchases = ledger");
                    $this->assertSame($k('refund', 'debit'), $this->cents($x->refund), "$id supplier refunds = ledger");
                } else {
                    $this->assertSame($k('sale', 'debit') + $k('reissue', 'debit'), $this->cents($x->amount), "$id sales = ledger");
                    $this->assertSame($k('refund', 'credit'), $this->cents($x->refund), "$id client refunds = ledger");
                }
                $checked[$role]++;
            }
        }
        fwrite(STDERR, "\n[info] roles checked: " . json_encode($checked) . "\n");
        $this->assertGreaterThan(50, $checked['supplier']);
        $this->assertGreaterThan(100, $checked['customer']);
    }


    public function test_the_overview_loads_the_analysis_after_it_opens(): void
    {
        foreach ([4, 16] as $id) {   // both roles
            $html = $this->get(route('site.suppliers_overview', $id))->assertOk()->getContent();
            $this->assertStringContainsString('<div id="ovInvoicesLoader" data-url="' . route('site.suppliers_overview_invoices', $id) . '">', $html);
            $this->assertStringContainsString('جاري تحميل تحليل الفواتير', $html);
            $this->assertStringNotContainsString('id="ovInvoices-', $html, 'the analysis itself is not in the page');
        }
        $none = SupplierDirectory::rows()->first(fn ($r) => $r->role === null);
        $this->assertStringNotContainsString('id="ovInvoicesLoader"', $this->get(route('site.suppliers_overview', $none->account->id))->getContent(), 'nothing to load without invoices');
    }

    public function test_the_loaded_section_shows_each_role_with_its_breakdowns(): void
    {
        foreach ([4, 16] as $id) {   // both roles
            $res = $this->get(route('site.suppliers_overview_invoices', $id))->assertOk();
            $html = $res->getContent();
            $this->assertStringNotContainsString('<html', $html, 'a fragment, not a page');
            foreach ($res->viewData('invoices') as $role => $x) {
                $section = substr($html, strpos($html, 'id="ovInvoices-' . $role . '"'));
                $next = strpos($section, 'id="ovInvoices-', 10);
                $section = $next ? substr($section, 0, $next) : $section;
                foreach (['invoices' => number_format($x->invoices), 'tickets' => number_format($x->tickets),
                             'amount' => number_format($x->amount, 2), 'refund' => number_format($x->refund, 2), 'net' => number_format($x->net, 2)] as $f => $v) {
                    $this->assertStringContainsString('data-f="' . $f . '">' . $v . '<', $section, "$id $role $f");
                }
                $this->assertStringContainsString(e(route('site.invoices_full_report', [$role === 'supplier' ? 'supplier_id' : 'customer_id' => $id])), $section);
                foreach (array_keys(AccountInvoices::GROUPS) as $by) {
                    $table = substr($section, strpos($section, 'data-group="' . $role . '-' . $by . '"'));
                    $table = substr($table, 0, strpos($table, '</table>'));
                    $this->assertSame(count($x->groups[$by]), substr_count($table, '<tr>') - 1, "$id $role $by rows");
                    foreach ($x->groups[$by] as $g) {
                        $this->assertStringContainsString('<td>' . e($g->label) . '</td>', $table, "$id $role $by {$g->label}");
                    }
                }
            }
            $this->assertStringContainsString('تحليل الفواتير كمورد', $html);
            $this->assertStringContainsString('تحليل الفواتير كعميل', $html);
        }

        // one role only / none
        $supplierOnly = SupplierDirectory::rows()->first(fn ($r) => $r->role === 'supplier');
        $html = $this->get(route('site.suppliers_overview_invoices', $supplierOnly->account->id))->getContent();
        $this->assertStringContainsString('id="ovInvoices-supplier"', $html);
        $this->assertStringNotContainsString('id="ovInvoices-customer"', $html);
        $none = SupplierDirectory::rows()->first(fn ($r) => $r->role === null);
        $html = $this->get(route('site.suppliers_overview_invoices', $none->account->id))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="ovInvoices-', $html);
        $this->assertStringContainsString('لا توجد فواتير لهذا الحساب.', $html);
    }

    public function test_query_counts_page_constant_and_analysis_bounded(): void
    {
        $count = function ($route, $id) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route($route, $id))->assertOk();
            return count(DB::getQueryLog());
        };
        $none = SupplierDirectory::rows()->first(fn ($r) => $r->role === null)->account->id;
        $both = SupplierDirectory::rows()->filter(fn ($r) => $r->role === 'both')->sortBy(fn ($r) => $r->invoices_as_supplier + $r->invoices_as_customer);
        $tiny = $both->first()->account->id;     // fewest invoices in both roles
        $ids = ['none' => $none, 'both_tiny' => $tiny, 'both_16' => 16, 'both_4' => 4];
        $page = array_map(fn ($id) => $count('site.suppliers_overview', $id), $ids);
        $analysis = array_map(fn ($id) => $count('site.suppliers_overview_invoices', $id), $ids);
        fwrite(STDERR, "\n[info] overview queries: " . json_encode($page) . " | analysis queries: " . json_encode($analysis) . "\n");

        $this->assertCount(1, array_unique($page), 'the overview itself: same queries for every account');
        $this->assertLessThanOrEqual(16, max($page));
        foreach (['both_tiny', 'both_16', 'both_4'] as $k) {
            $this->assertLessThanOrEqual(8 + 2 * 8, $analysis[$k], "$k: at most 8 report queries per role");
        }
        // 2,396 + 49 invoices cost no more queries than a handful (never one per invoice)
        $this->assertLessThanOrEqual($analysis['both_tiny'] + 2, $analysis['both_16']);
    }

    public function test_the_analysis_url_is_admin_only_read_only_and_404s(): void
    {
        $this->get(route('site.suppliers_overview_invoices', 999999999))->assertNotFound();
        $this->post('/suppliers/16/overview/invoices')->assertStatus(405);
        $employee = User::where('account_type', 1)->where('status', 1)->firstOrFail();
        $this->actingAs($employee)->get(route('site.suppliers_overview_invoices', 16))->assertForbidden();
    }
}
