<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{AccountStatement, Supplier, User};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Accounts-balances report («كشف حساب موردين», screen + print): totals come from one
 * grouped query (AccountStatement::balancesByAccount) instead of one query per
 * account. Proves, on the dev database, that every account's totals are exactly
 * what the old per-account sums gave and what its account statement shows.
 * Read-only; wrapped in a transaction anyway.
 */
class SupplierBalancesReportTest extends TestCase
{
    use DatabaseTransactions;

    private const FROM = '2026-03-01';
    private const TO = '2026-06-30';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->actingAs(User::where('account_type', 2)->where('status', 1)->firstOrFail());
    }

    /** The removed per-account calculation, verbatim: one query per account, PHP sums. */
    private function oldTotals(int $id, $from = null, $to = null): array
    {
        $q = AccountStatement::where('supp_client_id', $id)->where('is_storage', '!=', 1);
        if ($from !== null) {
            $q = $q->whereBetween('crt_date', [$from, $to]);
        }
        $rows = $q->get();
        return [$rows->sum('credit_balance'), $rows->sum('debit_balance')];
    }

    private function newTotals($totals, int $id): array
    {
        return [$totals->get($id)->total_credit ?? 0, $totals->get($id)->total_debit ?? 0];
    }

    public function test_grouped_totals_equal_the_old_per_account_sums_for_every_account(): void
    {
        $ids = Supplier::pluck('id')->all();
        foreach ([[null, null], [self::FROM, self::TO]] as [$from, $to]) {
            $totals = AccountStatement::balancesByAccount($ids, $from, $to);
            foreach ($ids as $id) {
                [$oc, $od] = $this->oldTotals($id, $from, $to);
                [$nc, $nd] = $this->newTotals($totals, $id);
                $ctx = "account $id " . ($from ? 'dated' : 'all');
                // same PHP values (so the same text, data-order and zero check in the views)
                $this->assertSame((string) $oc, (string) $nc, "$ctx credit");
                $this->assertSame((string) $od, (string) $nd, "$ctx debit");
                $this->assertSame((string) ($od - $oc), (string) ($nd - $nc), "$ctx balance");
                $this->assertSame($od - $oc == 0, $nd - $nc == 0, "$ctx zero");
            }
        }
    }

    public function test_every_account_balance_matches_its_account_statement(): void
    {
        $ids = Supplier::pluck('id')->all();
        foreach ([[null, null], [self::FROM, self::TO]] as [$from, $to]) {
            $totals = AccountStatement::balancesByAccount($ids, $from, $to);
            foreach ($ids as $id) {
                $res = $this->post(route('site.accounts_statement_search'),
                    ['invoice_beneficiaries' => $id] + ($from ? ['date_from' => $from, 'date_to' => $to] : []));
                $res->assertOk();
                [$nc, $nd] = $this->newTotals($totals, $id);
                $ctx = "account $id " . ($from ? 'dated' : 'all');
                $this->assertSame(number_format($res->viewData('total_credit_balance'), 2), number_format($nc, 2), "$ctx credit");
                $this->assertSame(number_format($res->viewData('total_debit_balance'), 2), number_format($nd, 2), "$ctx debit");
                // statement closing balance = carried-forward opening + period movement
                $closing = $res->viewData('opening_balance_for_period') + $res->viewData('total_debit_balance') - $res->viewData('total_credit_balance');
                if ($from === null) {
                    $this->assertSame(number_format($closing, 2), number_format($nd - $nc, 2), "$ctx closing");
                }
            }
        }
    }

    /** Rendered rows of a balances report: account name => [credit, debit, balance] as displayed. */
    private function reportRows(string $html): array
    {
        preg_match_all('/<tr>\s*<td[^>]*>\d+<\/td>\s*<td[^>]*>([^<]*)<\/td>\s*<td[^>]*>\s*([^<]*?)\s*<\/td>\s*<td[^>]*>\s*([^<]*?)\s*<\/td>\s*<td[^>]*>\s*([^<]*?)\s*<\/td>/u', $html, $m, PREG_SET_ORDER);
        $rows = [];
        foreach ($m as $r) {
            $rows[] = [html_entity_decode($r[1], ENT_QUOTES), $r[2], $r[3], $r[4]];
        }
        return $rows;
    }

    public function test_dated_screen_report_matches_dated_print_and_account_statements(): void
    {
        foreach ([0, 1, 2] as $rt) {
            $dates = ['date_from' => self::FROM, 'date_to' => self::TO];
            $screen = $this->post(route('site.accounts_statement_suppliers_view'), ['report_type' => $rt] + $dates);
            $screen->assertOk();
            $this->assertSame(1, $screen->viewData('sts'), "screen rt$rt applies the dates");
            $print = $this->get(route('site.accounts_statement_suppliers_print', ['sup_stauts' => $rt, 'from' => self::FROM, 'to' => self::TO]));
            $print->assertOk();

            // zero rows hidden on both: the same accounts, in the same order, with the same numbers
            $screenRows = $this->reportRows($screen->getContent());
            $this->assertSame($this->reportRows($print->getContent()), $screenRows, "screen vs print rt$rt");

            // and each row equals that account's statement for the same period
            $expected = [];
            foreach ($screen->viewData('suppliers') as $s) {
                $st = $this->post(route('site.accounts_statement_search'), ['invoice_beneficiaries' => $s->id] + $dates);
                $c = $st->viewData('total_credit_balance');
                $d = $st->viewData('total_debit_balance');
                if ($d - $c != 0) {
                    $expected[] = [$s->name, number_format($c, 2), number_format($d, 2), number_format($d - $c, 2)];
                }
            }
            $this->assertSame($expected, $screenRows, "screen vs statements rt$rt");
            if ($rt !== 2) {
                $this->assertNotEmpty($screenRows, "rt$rt has dated rows");
            }

            // dated totals really differ from all-time totals (the filter is not a no-op)
            $undated = $this->post(route('site.accounts_statement_suppliers_view'), ['report_type' => $rt]);
            $this->assertSame(0, $undated->viewData('sts'));
            if ($rt !== 2) {
                $this->assertNotSame($this->reportRows($undated->getContent()), $screenRows, "rt$rt dated vs all-time");
            }
        }

        // only one date given: no filter, on screen and in print alike
        $one = $this->post(route('site.accounts_statement_suppliers_view'), ['report_type' => 0, 'date_from' => self::FROM]);
        $this->assertSame(0, $one->viewData('sts'));
    }

    /** Every row the report renders shows the old per-account numbers; query count no longer grows with accounts. */
    public function test_screen_and_print_rows_show_the_old_numbers_with_constant_queries(): void
    {
        foreach ([0, 1, 2] as $rt) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $screen = $this->post(route('site.accounts_statement_suppliers_view'), ['report_type' => $rt, 'check_get_zero' => 1]);
            $screen->assertOk();
            $this->assertLessThanOrEqual(3, count(DB::getQueryLog()), "screen rt$rt queries");

            DB::flushQueryLog();
            $print = $this->get(route('site.accounts_statement_suppliers_print', ['sup_stauts' => $rt, 'from' => self::FROM, 'to' => self::TO]));
            $print->assertOk();
            $this->assertLessThanOrEqual(3, count(DB::getQueryLog()), "print rt$rt queries");

            $screenHtml = $screen->getContent();
            $printHtml = $print->getContent();
            foreach ($screen->viewData('suppliers') as $s) {
                $name = preg_quote(e($s->name), '/');

                // screen: posted without dates, zero rows shown
                [$c, $d] = $this->oldTotals($s->id);
                $cells = array_map(fn ($v) => '\s*' . preg_quote(number_format($v, 2), '/') . '\s*', [$c, $d, $d - $c]);
                $this->assertMatchesRegularExpression('/>' . $name . '<\/td>\s*<td[^>]*>' . implode('<\/td>\s*<td[^>]*>', $cells) . '<\/td>/u',
                    $screenHtml, "screen rt$rt account {$s->id}");

                // print: dated, zero-balance rows hidden
                [$c, $d] = $this->oldTotals($s->id, self::FROM, self::TO);
                $cells = array_map(fn ($v) => preg_quote(number_format($v, 2), '/'), [$c, $d, $d - $c]);
                $row = '/>' . $name . '<\/td>\s*<td[^>]*>' . implode('<\/td>\s*<td[^>]*>', $cells) . '\s*<\/td>/u';
                if ($d - $c == 0) {
                    $this->assertDoesNotMatchRegularExpression($row, $printHtml, "print rt$rt account {$s->id} (zero)");
                } else {
                    $this->assertMatchesRegularExpression($row, $printHtml, "print rt$rt account {$s->id}");
                }
            }
        }
    }
}
