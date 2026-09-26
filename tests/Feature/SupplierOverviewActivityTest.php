<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{AccountStatement, Supplier, User};
use App\Services\{AccountMovements, InvoicePassengerLedger, SupplierDirectory};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Account overview, latest movements and vouchers (Phase 3 step 3): the statement's
 * last rows in its order with the running balance (checked against an independent SQL
 * window sum and the statement page), labels and links from the existing rules, and
 * the account's vouchers. Dev DB, rolled back.
 */
class SupplierOverviewActivityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->actingAs(User::where('account_type', 2)->where('status', 1)->firstOrFail());
    }

    private function cents($v): int
    {
        return (int) round($v * 100);
    }

    public function test_latest_movements_and_running_balance_for_every_account(): void
    {
        // independent running balance: SQL window sum in the statement's order (crt_date, id)
        $running = DB::query()->fromSub(DB::table('account_statements')->where('is_storage', '!=', 1)->selectRaw('
                id, supp_client_id,
                SUM(debit_balance - credit_balance) OVER (PARTITION BY supp_client_id ORDER BY crt_date, id ROWS UNBOUNDED PRECEDING) AS after'), 't')
            ->pluck('after', 'id');
        $invoicesByEs = DB::table('invoices')->pluck('id', 'es_id');
        $bondsByEs = DB::table('bonds')->whereNotNull('es_id')->pluck('id', 'es_id');
        $users = DB::table('users')->pluck('name', 'id');

        foreach (Supplier::orderBy('id')->get() as $account) {
            $id = $account->id;
            $latest = AccountMovements::forAccount($id)['latest'];
            $expectIds = AccountStatement::where('supp_client_id', $id)->where('is_storage', '!=', 1)
                ->orderByDesc('crt_date')->orderByDesc('id')->limit(AccountMovements::LATEST)->pluck('id')->all();
            $this->assertSame($expectIds, array_map(fn ($m) => $m->row->id, $latest), "$id latest rows, newest first");

            if ($latest) {
                $this->assertSame($this->cents(SupplierDirectory::row($account)->balance), $this->cents($latest[0]->balance_after), "$id newest = balance");
            }
            foreach ($latest as $m) {
                $r = $m->row;
                $this->assertSame($this->cents($running[$r->id]), $this->cents($m->balance_after), "$id row {$r->id} balance after");
                $this->assertSame(AccountMovements::kindOf($r), $m->kind);

                $invoiceId = $invoicesByEs[$r->es_id] ?? null;
                $this->assertSame($invoiceId ? (int) $invoiceId : null, $m->invoice, "$id row {$r->id} invoice link");
                $bondId = (int) $r->transaction_type === 2 ? ($bondsByEs[$r->es_id] ?? null) : null;
                $this->assertSame($bondId ? (int) $bondId : null, $m->bond, "$id row {$r->id} voucher link");
                $this->assertSame($users[$r->added_by] ?? null, $m->employee);

                $isTicket = InvoicePassengerLedger::rowKind($r) !== null || (int) $r->transaction_type === 4;
                $invoice = $invoiceId ? \App\Models\Invoice::find($invoiceId) : null;
                $this->assertSame($isTicket ? InvoicePassengerLedger::kindLabel($r, $invoice) : AccountMovements::ROW_LABELS[$m->kind], $m->label, "$id row {$r->id} label");
            }
        }
    }

    public function test_the_statement_page_ends_on_the_same_balance(): void
    {
        foreach ([4, 16, 338] as $id) {
            $newest = AccountMovements::forAccount($id)['latest'][0];
            $html = $this->post(route('site.accounts_statement_search'), ['invoice_beneficiaries' => $id])->assertOk()->getContent();
            $this->assertStringContainsString(number_format($newest->balance_after, 2), $html, "$id statement closing balance");

            // and the newest row's balance-after is the statement's running figure after that row
            $st = $this->post(route('site.accounts_statement_search'), ['invoice_beneficiaries' => $id]);
            $rows = $st->viewData('AccountStatements');
            $this->assertSame($newest->row->id, $rows->last()->id, "$id statement's last row");
        }
    }

    public function test_vouchers_for_every_account(): void
    {
        $withInvoice = 0;
        foreach (Supplier::orderBy('id')->pluck('id') as $id) {
            $mine = fn ($q) => $q->where(fn ($b) => $b->where('from_type', 'supplier')->where('from_account', $id))
                ->orWhere(fn ($b) => $b->where('to_type', 'supplier')->where('to_account', $id));
            $v = AccountMovements::vouchers($id);

            $this->assertSame(DB::table('bonds')->where($mine)->count(), $v['count'], "$id count");
            $expect = DB::table('bonds')->where($mine)->orderByDesc('crt_date')->orderByDesc('id')->limit(AccountMovements::LATEST)->get();
            $this->assertSame($expect->pluck('id')->map(fn ($x) => (int) $x)->all(), array_map(fn ($x) => (int) $x->bond->id, $v['latest']), "$id order");

            foreach ($v['latest'] as $i => $x) {
                $b = $expect[$i];
                $this->assertSame($b->from_type === 'supplier' && (int) $b->from_account === $id ? 'receipt' : 'payment', $x->direction, "$id bond {$b->id} direction");
                $bank = $b->money_way == 2 ? DB::table('banks')->where('id', $b->bank_id)->value('bank_name') : null;
                $collector = ! in_array((int) $b->money_way, [1, 2], true) ? DB::table('collectors')->where('id', $b->collector_info)->value('name') : null;
                $this->assertSame(match ((int) $b->money_way) {
                    1 => 'دفع نقدي', 2 => trim('تحويل بنكي ' . $bank), default => trim('تحصيل من المندوب: ' . $collector),
                }, $x->method, "$id bond {$b->id} method");
                $invoiceEs = $b->invoice_id ? DB::table('invoices')->where('id', $b->invoice_id)->value('es_id') : null;
                $this->assertSame($invoiceEs, $x->invoice?->es_id, "$id bond {$b->id} invoice");
                $this->assertSame($b->invoice_id && $invoiceEs === null ? (int) $b->invoice_id : null, $x->missing_invoice, "$id bond {$b->id} deleted invoice");
                $withInvoice += $x->invoice ? 1 : 0;
            }
        }
        $this->assertGreaterThan(0, $withInvoice, 'some listed vouchers are invoice payments (Counter Customer)');
    }

    public function test_the_page_shows_both_tables_with_links(): void
    {
        foreach ([4, 16] as $id) {
            $res = $this->get(route('site.suppliers_overview', $id))->assertOk();
            $html = $res->getContent();
            $movements = $res->viewData('movements');
            $vouchers = $res->viewData('vouchers');

            foreach ($movements['latest'] as $m) {
                $this->assertMatchesRegularExpression('/<tr data-row="' . $m->row->id . '">\s*<td>' . preg_quote((string) $m->row->crt_date, '/') . '<\/td>/', $html);
                if ($m->invoice) {
                    $this->assertStringContainsString('<a href="' . route('site.invoice_info', $m->invoice) . '" title="عرض الفاتورة">' . e($m->row->es_id) . '</a>', $html);
                }
                if ($m->bond) {
                    $this->assertStringContainsString('<a href="' . route('site.bonds_edit', $m->bond) . '" title="فتح السند (صفحة السند)">' . e($m->row->es_id) . '</a>', $html);
                }
                $shown = number_format(abs($m->balance_after), 2);
                $this->assertMatchesRegularExpression('/<tr data-row="' . $m->row->id . '">.*?<td class="fw-medium">\s*(<span[^>]*>)?' . preg_quote($shown, '/') . '/s', $html, "$id row balance shown");
            }
            foreach ($vouchers['latest'] as $v) {
                $this->assertStringContainsString('<tr data-bond="' . $v->bond->id . '">', $html);
                $this->assertStringContainsString('href="' . route('site.bonds_edit', $v->bond->id) . '" title="فتح السند (صفحة السند)"', $html);
                if ($v->invoice) {
                    $this->assertStringContainsString('<a href="' . route('site.invoice_info', $v->invoice->id) . '" title="عرض الفاتورة">', $html);
                }
            }
            // invoice links open the read-only invoice page, never the edit page (/invoices/{id}/show)
            $this->assertDoesNotMatchRegularExpression('#/invoices/\d+/show#', $html, "$id links to an invoice edit page");
            $this->assertStringContainsString('من ' . number_format($vouchers['count']) . ' سند', $html);
        }

        // dev data: vouchers #11982 / #11983 paid invoice 13249, which was later deleted (with its ledger rows)
        $html = $this->get(route('site.suppliers_overview', 4))->getContent();
        if (DB::table('bonds')->whereIn('id', [11982, 11983])->where('invoice_id', 13249)->count() === 2 && ! DB::table('invoices')->where('id', 13249)->exists()) {
            $this->assertSame(2, substr_count($html, 'فاتورة محذوفة #13249'));
        }
    }

    public function test_empty_account_and_constant_query_count(): void
    {
        $name = 'TEST-ACT-' . uniqid();
        $this->post(route('site.suppliers_save'), ['name' => $name, 'phone_1' => '01000000000', 'limit_balance' => '0',
            'debit_opening_balance' => '0', 'opening_credit_balance' => '0', 'status' => '1', 'type' => '1',
            'in_index' => '0', 'in_stat' => '0'])->assertRedirect();
        $empty = Supplier::where('name', $name)->firstOrFail();
        AccountStatement::where('supp_client_id', $empty->id)->delete();   // (rolled back)

        $html = $this->get(route('site.suppliers_overview', $empty->id))->assertOk()->getContent();
        $this->assertStringContainsString('لا توجد سندات لهذا الحساب.', $html);
        $this->assertStringNotContainsString('id="ovLatest"', $html);

        $counts = [];
        foreach ([16, 4, 338, $empty->id] as $id) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('site.suppliers_overview', $id))->assertOk();
            $counts[$id] = count(DB::getQueryLog());
        }
        fwrite(STDERR, "\n[info] overview queries: " . json_encode($counts) . "\n");
        $this->assertCount(1, array_unique($counts), 'same number of queries for every account');
        $this->assertLessThanOrEqual(16, max($counts));
    }
}
