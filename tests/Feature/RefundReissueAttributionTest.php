<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{Invoice, Supplier, User};
use App\Services\{CounterPayments, EmployeeCommissionReport};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{DB, Hash};
use Tests\TestCase;

/**
 * Employees step D: a re-issue / refund follows its ORIGINAL invoice, whatever screen or URL
 * starts it. Normal original -> normal operation, 100% for the employee who made it. Shared
 * original -> shared operation with the original's two accounts and weights (7/3 stays 70/30;
 * never a fixed 5/5, never the performer). The wrong endpoint is redirected (forms) or refused
 * (saves). Existing records are not touched. Dev DB, rolled back.
 */
class RefundReissueAttributionTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $performer;
    private Supplier $customer;
    private Supplier $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->admin = User::where('account_type', 2)->where('status', 1)->orderBy('id')->firstOrFail();
        $this->performer = $this->emp();
        [$this->customer, $this->vendor] = Supplier::where('status', 1)->where('acc_type', '!=', 3)
            ->whereNotIn('id', CounterPayments::counterIds())->orderBy('id')->limit(2)->get()->all();
    }

    private function emp(): User
    {
        return User::create(['name' => 'TEST EMP ' . uniqid(), 'email' => 'emp-' . uniqid() . '@test.local', 'password' => Hash::make('secret-123'),
            'user_id' => 'FX_TEST', 'status' => '1', 'account_type' => 1, 'commission' => '10'])->fresh();
    }

    private function flight(array $over = []): array
    {
        return array_merge([
            'invoice_date' => '2031-03-05', 'invoice_travel_date' => '2031-04-05', 'return_date' => null,
            'from_location' => 'CAI', 'to_location' => 'DXB', 'invoice_airline' => 'TEST-AIR',
            'invoice_beneficiaries' => $this->customer->id, 'vendor_id' => $this->vendor->id, 'vendor_cost' => 1000,
            'invoice_section' => 1, 'invoice_comments' => null, 'invoice_currency' => 'جنية مصري', 'invoice_draft' => null,
            'ticket_info' => [['name' => 'TEST PAX', 'client_type' => 3, 'net_price' => 1000, 'bought_price' => 1200,
                'book_id' => 'PNR1', 'tikcet_id' => 'TKT1', 'client_phone' => null]],
        ], $over);
    }

    private function last(): Invoice
    {
        return Invoice::orderByDesc('id')->firstOrFail();
    }

    /** A normal original sold by a fresh employee (entered by the admin for them). */
    private function normalOriginal(): Invoice
    {
        $this->actingAs($this->admin)->post(route('site.invoices_save'), $this->flight(['added_user' => $this->emp()->id]))->assertSessionHasNoErrors();
        return tap($this->last(), fn ($i) => $this->assertSame(0, (int) $i->invoice_shared));
    }

    /** A shared original (account 1 / weight 1, account 2 / weight 2), entered by the admin. */
    private function sharedOriginal(User $a, $r1, User $b, $r2): Invoice
    {
        $this->actingAs($this->admin)->post(route('site.shared_invoices_save'), $this->flight(['invoice_account_1' => $a->id, 'invoice_account_1_comm' => $r1,
            'invoice_account_2' => $b->id, 'invoice_account_2_comm' => $r2]))->assertSessionHasNoErrors();
        return tap($this->last(), fn ($i) => $this->assertSame(1, (int) $i->invoice_shared));
    }

    /** Re-issue of $orig through $route by the performer (profit 1,000); the new invoice. */
    private function reissue(Invoice $orig, string $route, array $over = []): Invoice
    {
        $before = $this->last()->id;
        $this->actingAs($this->performer)->post(route($route), $this->flight(['id' => $orig->id, 'ticket_info' => [['name' => 'TEST PAX', 'client_type' => 3,
            'net_price' => 1000, 'bought_price' => 2000, 'book_id' => 'PNR2', 'tikcet_id' => 'TKT2', 'client_phone' => null]]] + $over))->assertSessionHasNoErrors();
        $new = $this->last();
        $this->assertNotSame($before, $new->id, 'a re-issue was created');
        $this->assertStringStartsWith('FLY-RS', $new->es_id);
        return $new;
    }

    /** Full refund of $orig through $route by the performer: supplier returns 900, client gets 700 (refund net 200). */
    private function refund(Invoice $orig, string $route): Invoice
    {
        $before = $this->last()->id;
        $this->actingAs($this->performer)->post(route($route), ['id' => $orig->id, 'refund_mode' => 'full', 'bought_price_total' => '900', 'net_pice_total' => '700'])
            ->assertSessionHasNoErrors();
        $new = $this->last();
        $this->assertNotSame($before, $new->id, 'a refund was created');
        $this->assertStringStartsWith('FLY-RD', $new->es_id);
        return $new;
    }

    /** Today's report (re-issues / refunds are dated today), keyed by employee id. */
    private function report(): array
    {
        $today = date('Y-m-d');
        return collect((new EmployeeCommissionReport($this->admin, $today, $today))->results())->keyBy('employee_id')->all();
    }

    private function sharedFields(Invoice $i): array
    {
        return [(int) $i->invoice_shared, (string) $i->invoice_account_1, (string) $i->invoice_account_1_comm, (string) $i->invoice_account_2, (string) $i->invoice_account_2_comm];
    }

    private function assertProfit(float $expected, array $report, User $u, string $key = 'profit'): void
    {
        $this->assertArrayHasKey($u->id, $report, $u->name);
        $this->assertEqualsWithDelta($expected, $report[$u->id][$key], 0.001, $u->name);
    }

    // ------------------------------------------------------------------ A / B: normal original

    public function test_a_normal_original_refund_is_normal_and_100_percent_for_the_performer(): void
    {
        $new = $this->refund($this->normalOriginal(), 'site.invoices_refund_save');
        $this->assertSame([0, (string) $this->performer->id], [(int) $new->invoice_shared, (string) $new->invoice_create_by]);
        $r = $this->report();
        $this->assertProfit(200, $r, $this->performer, 'refund_net');
        $this->assertCount(1, array_filter($r, fn ($row) => str_starts_with($row['employee'], 'TEST EMP')), 'nobody else');
    }

    public function test_b_normal_original_reissue_is_normal_and_100_percent_for_the_performer(): void
    {
        $new = $this->reissue($this->normalOriginal(), 'site.invoices_reissue_save');
        $this->assertSame([0, (string) $this->performer->id], [(int) $new->invoice_shared, (string) $new->invoice_create_by]);
        $this->assertProfit(1000, $this->report(), $this->performer);
    }

    // ------------------------------------------------------------------ C-F, K, L: shared original

    public function test_c_shared_7_3_refund_is_split_70_30(): void
    {
        [$a, $b] = [$this->emp(), $this->emp()];
        $new = $this->refund($this->sharedOriginal($a, 7, $b, 3), 'site.shared_invoices_refund_save');
        $this->assertSame([1, (string) $a->id, '7', (string) $b->id, '3'], $this->sharedFields($new));
        $r = $this->report();
        $this->assertProfit(140, $r, $a, 'refund_net');
        $this->assertProfit(60, $r, $b, 'refund_net');
        $this->assertArrayNotHasKey($this->performer->id, $r);
    }

    public function test_d_shared_7_3_reissue_is_split_70_30(): void
    {
        [$a, $b] = [$this->emp(), $this->emp()];
        $new = $this->reissue($this->sharedOriginal($a, 7, $b, 3), 'site.shared_invoices_reissue_save');
        $this->assertSame([1, (string) $a->id, '7', (string) $b->id, '3'], $this->sharedFields($new));
        $r = $this->report();
        $this->assertProfit(700, $r, $a);
        $this->assertProfit(300, $r, $b);
        $this->assertArrayNotHasKey($this->performer->id, $r);
    }

    public function test_e_shared_5_5_refund_is_split_50_50(): void
    {
        [$a, $b] = [$this->emp(), $this->emp()];
        $new = $this->refund($this->sharedOriginal($a, 5, $b, 5), 'site.shared_invoices_refund_save');
        $this->assertSame([1, (string) $a->id, '5', (string) $b->id, '5'], $this->sharedFields($new));
        $r = $this->report();
        $this->assertProfit(100, $r, $a, 'refund_net');
        $this->assertProfit(100, $r, $b, 'refund_net');
    }

    public function test_f_shared_5_5_reissue_is_split_50_50(): void
    {
        [$a, $b] = [$this->emp(), $this->emp()];
        $this->reissue($this->sharedOriginal($a, 5, $b, 5), 'site.shared_invoices_reissue_save');
        $r = $this->report();
        $this->assertProfit(500, $r, $a);
        $this->assertProfit(500, $r, $b);
    }

    public function test_k_the_original_accounts_and_weights_are_copied_exactly(): void
    {
        [$a, $b, $x] = [$this->emp(), $this->emp(), $this->emp()];
        $orig = $this->sharedOriginal($a, '66.5', $b, '33.5');
        // the form's (now read-only) accounts are ignored even if posted
        $reissue = $this->reissue($orig, 'site.shared_invoices_reissue_save', ['invoice_account_1' => $x->id, 'invoice_account_2' => $this->performer->id]);
        $refund = $this->refund($orig, 'site.shared_invoices_refund_save');
        foreach ([$reissue, $refund] as $new) {
            $this->assertSame($this->sharedFields($orig), $this->sharedFields($new));
        }
        // a re-issue of the re-issue keeps them as well
        $this->assertSame($this->sharedFields($orig), $this->sharedFields($this->reissue($reissue, 'site.shared_invoices_reissue_save')));
        $this->assertArrayNotHasKey($x->id, $this->report());
    }

    public function test_l_the_performer_does_not_override_the_shared_split(): void
    {
        [$a, $b] = [$this->emp(), $this->emp()];
        $orig = $this->sharedOriginal($a, 7, $b, 3);
        $new = $this->reissue($orig, 'site.shared_invoices_reissue_save');
        $this->assertSame((string) $this->performer->id, (string) $new->invoice_create_by, 'recorded as made by the performer');
        $r = $this->report();
        $this->assertArrayNotHasKey($this->performer->id, $r, 'but no share for the performer');
        $this->assertEqualsWithDelta(1000, $r[$a->id]['profit'] + $r[$b->id]['profit'], 0.001, 'the whole profit goes to the two accounts');
    }

    // ------------------------------------------------------------------ G / H: invoice details page

    public function test_g_details_page_of_a_shared_invoice_leads_to_a_shared_operation(): void
    {
        [$a, $b] = [$this->emp(), $this->emp()];
        $orig = $this->sharedOriginal($a, 7, $b, 3);
        $this->actingAs($this->performer)->get(route('site.invoice_info', $orig->id))->assertOk()
            ->assertSee(route('site.shared_invoices_reissue_create', $orig->es_id), false)->assertSee(route('site.shared_invoices_refund', $orig->es_id), false)
            ->assertDontSee(route('site.invoices_refund', $orig->es_id), false);
        $this->get(route('site.shared_invoices_refund', $orig->es_id))->assertOk()->assertSee('نسبة المشاركة (من الفاتورة الأصلية): 7');
        $this->assertSame([1, (string) $a->id, '7', (string) $b->id, '3'], $this->sharedFields($this->refund($orig, 'site.shared_invoices_refund_save')));
    }

    public function test_h_details_page_of_a_normal_invoice_leads_to_a_normal_operation(): void
    {
        $orig = $this->normalOriginal();
        $this->actingAs($this->performer)->get(route('site.invoice_info', $orig->id))->assertOk()
            ->assertSee(route('site.invoices_reissue_create', $orig->es_id), false)->assertSee(route('site.invoices_refund', $orig->es_id), false)
            ->assertDontSee(route('site.shared_invoices_refund', $orig->es_id), false);
        $this->get(route('site.invoices_reissue_create', $orig->es_id))->assertOk();
        $new = $this->reissue($orig, 'site.invoices_reissue_save');
        $this->assertSame([0, (string) $this->performer->id], [(int) $new->invoice_shared, (string) $new->invoice_create_by]);
    }

    // ------------------------------------------------------------------ I / J: the wrong endpoint

    public function test_i_shared_endpoints_refuse_a_normal_invoice(): void
    {
        $orig = $this->normalOriginal();
        $count = Invoice::count();
        $this->actingAs($this->performer);
        // forms: sent to the normal form
        $this->get(route('site.shared_invoices_reissue_create', $orig->es_id))->assertRedirect(route('site.invoices_reissue_create', $orig->es_id));
        $this->get(route('site.shared_invoices_refund', $orig->es_id))->assertRedirect(route('site.invoices_refund', $orig->es_id));
        // saves: refused, nothing created
        $this->post(route('site.shared_invoices_reissue_save'), $this->flight(['id' => $orig->id]))->assertSessionHasErrors('invoice');
        $this->post(route('site.shared_invoices_refund_save'), ['id' => $orig->id, 'refund_mode' => 'full', 'bought_price_total' => '900', 'net_pice_total' => '700'])
            ->assertSessionHasErrors('invoice');
        $this->assertSame($count, Invoice::count());
    }

    public function test_j_normal_endpoints_refuse_a_shared_invoice(): void
    {
        $orig = $this->sharedOriginal($this->emp(), 7, $this->emp(), 3);
        $count = Invoice::count();
        $this->actingAs($this->performer);
        $this->get(route('site.invoices_reissue_create', $orig->es_id))->assertRedirect(route('site.shared_invoices_reissue_create', $orig->es_id));
        $this->get(route('site.invoices_refund', $orig->es_id))->assertRedirect(route('site.shared_invoices_refund', $orig->es_id));
        $this->post(route('site.invoices_reissue_save'), $this->flight(['id' => $orig->id]))->assertSessionHasErrors('invoice');
        $this->post(route('site.invoices_refund_save'), ['id' => $orig->id, 'refund_mode' => 'full', 'bought_price_total' => '900', 'net_pice_total' => '700'])
            ->assertSessionHasErrors('invoice');
        $this->assertSame($count, Invoice::count());
    }

    // ------------------------------------------------------------------ M: history

    public function test_m_existing_refund_and_reissue_records_are_not_changed(): void
    {
        $snapshot = fn () => md5(json_encode(DB::table('invoices')->where(fn ($q) => $q->where('es_id', 'like', 'FLY-RD%')->orWhere('es_id', 'like', 'FLY-RS%'))
            ->orderBy('id')->get(['id', 'es_id', 'invoice_shared', 'invoice_account_1', 'invoice_account_1_comm', 'invoice_account_2', 'invoice_account_2_comm', 'invoice_create_by', 'invoice_date'])));
        $existing = DB::table('invoices')->where('es_id', 'like', 'FLY-R%')->max('id');
        $before = $snapshot();

        [$a, $b] = [$this->emp(), $this->emp()];
        $this->reissue($this->sharedOriginal($a, 7, $b, 3), 'site.shared_invoices_reissue_save');
        $this->refund($this->normalOriginal(), 'site.invoices_refund_save');

        $after = md5(json_encode(DB::table('invoices')->where('id', '<=', $existing)->where(fn ($q) => $q->where('es_id', 'like', 'FLY-RD%')->orWhere('es_id', 'like', 'FLY-RS%'))
            ->orderBy('id')->get(['id', 'es_id', 'invoice_shared', 'invoice_account_1', 'invoice_account_1_comm', 'invoice_account_2', 'invoice_account_2_comm', 'invoice_create_by', 'invoice_date'])));
        $this->assertSame($before, $after, 'the refunds / re-issues that existed are unchanged');
    }
}
