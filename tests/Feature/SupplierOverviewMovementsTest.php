<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{AccountStatement, Supplier, User};
use App\Services\{AccountMovements, InvoiceFullReport, SupplierDirectory};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Account overview, movements by kind (Phase 3 step 2): every statement row in exactly
 * one kind, the kinds adding up to the statement; classification checked against an
 * independent SQL classification and against the detailed invoice report. Dev DB, rolled back.
 */
class SupplierOverviewMovementsTest extends TestCase
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

    public function test_kinds_add_up_to_the_statement_for_every_account(): void
    {
        foreach (Supplier::orderBy('id')->get() as $account) {
            $id = $account->id;
            $m = AccountMovements::forAccount($id);
            $row = SupplierDirectory::row($account);   // = the account statement (SupplierOverviewTest)
            $rows = AccountStatement::where('supp_client_id', $id)->where('is_storage', '!=', 1)->count();

            $this->assertSame($rows, $m['rows'], "$id rows");
            $this->assertSame($rows, array_sum(array_column($m['kinds'], 'count')), "$id kinds cover every row once");
            $this->assertSame($this->cents($row->total_debit), $this->cents($m['debit']), "$id debit");
            $this->assertSame($this->cents($row->total_credit), $this->cents($m['credit']), "$id credit");
            $this->assertSame($this->cents($m['debit']), array_sum(array_map(fn ($k) => $this->cents($k->debit), $m['kinds'])), "$id kinds debit");
            $this->assertSame($this->cents($m['credit']), array_sum(array_map(fn ($k) => $this->cents($k->credit), $m['kinds'])), "$id kinds credit");
            $this->assertSame(array_values(array_intersect(array_keys(AccountMovements::KINDS), array_keys($m['kinds']))), array_keys($m['kinds']), "$id order");
        }
    }

    /**
     * The same rules written independently in SQL (valid while no row carries a JSON
     * marker -- true on dev; marker rows are covered by the next test).
     */
    public function test_classification_matches_an_independent_sql_classification(): void
    {
        $markers = DB::table('account_statements')->where('is_storage', '!=', 1)->where('description', 'like', '{%')->count();
        $this->assertSame(0, $markers, 'dev ledger has no marker rows (else this SQL check is incomplete)');

        $classified = DB::table('account_statements')->where('is_storage', '!=', 1)->selectRaw("
            supp_client_id AS id,
            CASE
              WHEN es_id = 'FLY-OPEN-BALANCE' OR transaction_type = 3 THEN 'opening'
              WHEN transaction_type = 1 AND es_id LIKE 'FLY-RD%' THEN 'refund'
              WHEN transaction_type = 1 AND es_id LIKE 'FLY-RS%' THEN 'reissue'
              WHEN transaction_type = 1 THEN 'sale'
              WHEN transaction_type = 2 AND invoice_type = 9 THEN 'payment'
              WHEN transaction_type = 2 AND invoice_type = 10 THEN 'receipt'
              WHEN transaction_type = 4 THEN 'invoice_payment'
              ELSE 'other' END AS k,
            debit_balance, credit_balance");
        $sql = DB::query()->fromSub($classified, 't')
            ->selectRaw('id, k, COUNT(*) n, SUM(debit_balance) d, SUM(credit_balance) c')
            ->groupBy('id', 'k')->get()->groupBy('id');

        $kindsSeen = [];
        foreach (Supplier::pluck('id') as $id) {
            $expected = collect($sql->get($id, []))->keyBy('k');
            $m = AccountMovements::forAccount($id);
            $this->assertEqualsCanonicalizing($expected->keys()->all(), array_keys($m['kinds']), "$id kinds");
            foreach ($m['kinds'] as $key => $k) {
                $e = $expected->get($key);
                $this->assertSame((int) $e->n, $k->count, "$id $key count");
                $this->assertSame($this->cents($e->d), $this->cents($k->debit), "$id $key debit");
                $this->assertSame($this->cents($e->c), $this->cents($k->credit), "$id $key credit");
                $kindsSeen[$key] = true;
            }
        }
        fwrite(STDERR, "\n[info] kinds present on dev: " . implode(', ', array_keys($kindsSeen)) . "\n");
        foreach (['sale', 'reissue', 'refund', 'payment', 'receipt', 'invoice_payment', 'opening'] as $k) {
            $this->assertArrayHasKey($k, $kindsSeen, "$k exercised by real data");
        }
    }

    public function test_marker_rows_and_unknown_rows_are_classified_by_the_existing_rules(): void
    {
        $name = 'TEST-MOVES-' . uniqid();
        $this->post(route('site.suppliers_save'), ['name' => $name, 'phone_1' => '01000000000', 'limit_balance' => '0',
            'debit_opening_balance' => '0', 'opening_credit_balance' => '0', 'status' => '1', 'type' => '1',
            'in_index' => '0', 'in_stat' => '0'])->assertRedirect();
        $id = Supplier::where('name', $name)->value('id');

        $base = (array) DB::table('account_statements')->where('transaction_type', 1)->orderByDesc('id')->first();
        unset($base['id']);
        $add = fn (array $over) => DB::table('account_statements')->insert(array_merge($base, ['supp_client_id' => $id, 'is_storage' => 0,
            'trans_storage' => 0, 'is_supp_account' => 0, 'debit_balance' => 0, 'credit_balance' => 0, 'description' => null], $over));
        $add(['es_id' => 'FLY-A1', 'description' => '{"kind":"edit"}', 'debit_balance' => 10]);          // edit on an invoice number
        $add(['es_id' => 'FLY-A1', 'description' => '{"kind":"refund"}', 'credit_balance' => 20]);       // marker beats the prefix
        $add(['es_id' => 'FLY-A1', 'description' => '{"kind":"reissue"}', 'debit_balance' => 30]);
        $add(['es_id' => 'FLY-A1', 'description' => '{"kind":"sale"}', 'credit_balance' => 40]);
        $add(['es_id' => 'FLY-BD1', 'transaction_type' => 2, 'invoice_type' => 7, 'debit_balance' => 5]);  // unknown voucher type
        $add(['es_id' => 'X-1', 'transaction_type' => 9, 'credit_balance' => 6]);                           // unknown transaction type

        $m = AccountMovements::forAccount($id);
        $this->assertEquals(10, $m['kinds']['edit']->debit);
        $this->assertEquals(20, $m['kinds']['refund']->credit);
        $this->assertEquals(30, $m['kinds']['reissue']->debit);
        $this->assertEquals(40, $m['kinds']['sale']->credit);
        $this->assertSame(2, $m['kinds']['other']->count, 'unknown rows are kept, as «أخرى»');
        $this->assertSame(1, $m['kinds']['opening']->count, 'the zero opening row of a new account');
        $this->assertSame(7, $m['rows']);
    }

    /** Ticket kinds agree with the detailed invoice report (passenger prices) on the busiest accounts. */
    public function test_ticket_kinds_match_the_detailed_invoice_report(): void
    {
        $edits = DB::table('account_statements')->where('description', 'like', '%"kind":"edit"%')->count();
        $this->assertSame(0, $edits, 'no edit rows on dev (edits are not in the report totals below)');

        $admin = User::where('account_type', 2)->where('status', 1)->firstOrFail();
        $ids = DB::table('account_statements')->where('is_storage', '!=', 1)->where('transaction_type', 1)
            ->groupBy('supp_client_id')->orderByRaw('COUNT(*) DESC')->limit(12)->pluck('supp_client_id')->push(4)->unique();
        foreach ($ids as $id) {
            $k = AccountMovements::forAccount((int) $id)['kinds'];
            $v = fn ($kind, $side) => isset($k[$kind]) ? $this->cents($k[$kind]->$side) : 0;
            $sup = (new InvoiceFullReport(['supplier_id' => $id], $admin))->summary();
            $cus = (new InvoiceFullReport(['customer_id' => $id], $admin))->summary();
            $this->assertSame($this->cents($sup['purchase']), $v('sale', 'credit') + $v('reissue', 'credit'), "$id purchases");
            $this->assertSame($this->cents($sup['supplier_return']), $v('refund', 'debit'), "$id supplier refunds");
            $this->assertSame($this->cents($cus['sale']), $v('sale', 'debit') + $v('reissue', 'debit'), "$id sales");
            $this->assertSame($this->cents($cus['client_refund']), $v('refund', 'credit'), "$id client refunds");
        }
    }

    public function test_the_page_shows_the_breakdown_and_its_total_equals_the_cards(): void
    {
        foreach ([4, 16, 338] as $id) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $res = $this->get(route('site.suppliers_overview', $id))->assertOk();
            $this->assertLessThanOrEqual(8, count(DB::getQueryLog()));
            $html = $res->getContent();
            $m = $res->viewData('movements');
            $row = $res->viewData('row');

            foreach ($m['kinds'] as $key => $k) {
                $cell = fn ($v) => $v ? preg_quote(number_format($v, 2), '/') : '<span class="text-muted">0\.00<\/span>';
                $this->assertMatchesRegularExpression('/<tr data-kind="' . $key . '">.*?' . preg_quote($k->label, '/') . '.*?<td class="text-center">'
                    . number_format($k->count) . '<\/td>\s*<td>\s*' . $cell($k->debit) . '\s*<\/td>\s*<td>\s*' . $cell($k->credit) . '\s*<\/td>/su', $html, "$id $key row");
            }
            $this->assertMatchesRegularExpression('/<tr data-kind="total">.*?<td>' . preg_quote(number_format($row->total_debit, 2), '/')
                . '<\/td>\s*<td>' . preg_quote(number_format($row->total_credit, 2), '/') . '<\/td>/s', $html, "$id total = cards");
        }
    }

    public function test_an_account_without_movements_says_so(): void
    {
        $name = 'TEST-EMPTY-' . uniqid();
        $this->post(route('site.suppliers_save'), ['name' => $name, 'phone_1' => '01000000000', 'limit_balance' => '0',
            'debit_opening_balance' => '0', 'opening_credit_balance' => '0', 'status' => '1', 'type' => '1',
            'in_index' => '0', 'in_stat' => '0'])->assertRedirect();
        $acc = Supplier::where('name', $name)->firstOrFail();
        AccountStatement::where('supp_client_id', $acc->id)->delete();   // (rolled back)

        $html = $this->get(route('site.suppliers_overview', $acc->id))->assertOk()->getContent();
        $this->assertStringContainsString('لا توجد حركات في كشف الحساب.', $html);
        $this->assertStringNotContainsString('id="ovMovements"', $html);
    }
}
