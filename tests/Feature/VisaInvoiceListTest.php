<?php

namespace Tests\Feature;

use App\Exports\InvoiceFullReportExport;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{Invoice, Supplier, TicketUser, User, Visa};
use App\Services\{CounterPayments, InvoiceFullReport};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

/**
 * Visa step 3: flight / visa classification (invoice_section 1 / 2), the three invoice lists
 * (all / flight / visa -- one data endpoint) and the detailed report's flight / visa filters.
 * Invoices are created through the real routes (flight form, Visa form, Visa edit, refund,
 * re-issue). Dev DB, rolled back.
 */
class VisaInvoiceListTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private int $maxVisaId;
    private Supplier $customer;
    private Supplier $vendor;
    private string $tag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        Storage::fake('public');
        $this->admin = User::where('account_type', 2)->where('status', 1)->firstOrFail();
        $this->actingAs($this->admin);
        $this->maxVisaId = (int) Visa::max('id');
        [$this->customer, $this->vendor] = Supplier::where('status', 1)->where('acc_type', '!=', 3)
            ->whereNotIn('id', CounterPayments::counterIds())->orderBy('id')->limit(2)->get()->all();
        $this->tag = 'S3T' . strtoupper(substr(uniqid(), -6)); // unique passenger-name token of this test
    }

    /** visas is MyISAM on dev (no rollback): remove the visa types this test created. */
    protected function tearDown(): void
    {
        Visa::where('id', '>', $this->maxVisaId)->where('visa_name', 'like', 'TEST-VISA-%')->delete();
        parent::tearDown();
    }

    private function makeVisa(): Visa
    {
        return Visa::create(['visa_name' => 'TEST-VISA-' . uniqid(), 'visa_price' => 0, 'visa_ext_price' => 0, 'status' => 1]);
    }

    private function last(): Invoice
    {
        return Invoice::orderByDesc('id')->firstOrFail();
    }

    private function flight(string $airline = 'TEST-AIR'): Invoice
    {
        $this->post(route('site.invoices_save'), [
            'invoice_date' => '2026-09-27', 'invoice_travel_date' => '2026-10-05', 'return_date' => null,
            'from_location' => 'CAI', 'to_location' => 'DXB', 'invoice_airline' => $airline,
            'invoice_beneficiaries' => $this->customer->id, 'vendor_id' => $this->vendor->id,
            'invoice_section' => 1, 'invoice_comments' => null, 'invoice_currency' => 'جنية مصري', 'invoice_draft' => null,
            'added_user' => $this->admin->id,
            'ticket_info' => [['name' => $this->tag . ' FLIGHT PAX', 'client_type' => 3, 'net_price' => 1000, 'bought_price' => 1200,
                'book_id' => 'PNR' . $this->tag, 'tikcet_id' => 'TKT' . $this->tag, 'client_phone' => null]],
        ])->assertRedirect(route('site.invoices'));
        return $this->last();
    }

    private function visa(Visa $visa, array $over = []): Invoice
    {
        $this->post(route('site.invoices_visa_save'), array_merge([
            'visa_id' => $visa->id, 'invoice_date' => '2026-09-27',
            'invoice_beneficiaries' => $this->customer->id, 'vendor_id' => $this->vendor->id,
            'applicants' => [['name' => $this->tag . ' VISA APPLICANT', 'passport' => 'PP' . $this->tag, 'visa_number' => 'APP' . $this->tag, 'cost' => '2000', 'sale' => '2500']],
        ], $over))->assertRedirect(route('site.invoices'));
        return $this->last();
    }

    /** Invoice-list rows (the list's own data endpoint) of this test, keyed by es_id. */
    private function listRows(?string $kind): \Illuminate\Support\Collection
    {
        $json = $this->getJson(route('site.invoices_list_data', array_filter(['draw' => 1, 'start' => 0, 'length' => 100,
            'kind' => $kind, 'search' => ['value' => $this->tag]])))->assertOk()->json();
        return collect($json['data'])->keyBy('es_id');
    }

    private function report(array $filters): \Illuminate\Support\Collection
    {
        return (new InvoiceFullReport($filters + ['search' => $this->tag], $this->admin))->rows();
    }

    // ------------------------------------------------------------------ lists

    public function test_list_pages_and_navigation(): void
    {
        $this->get(route('site.invoices_flight'))->assertOk()->assertSee('فواتير الطيران')
            ->assertSee('d.kind = "flight"', false)->assertSee(route('site.invoices_create'), false);
        $this->get(route('site.invoices_visa'))->assertOk()->assertSee('فواتير التأشيرات')
            ->assertSee('d.kind = "visa"', false)->assertSee(route('site.invoices_create_visa'), false);
        $all = $this->get(route('site.invoices'))->assertOk()->assertSee('d.kind = null', false);
        // sidebar + tabs: flight, visa, all
        foreach (['site.invoices_flight', 'site.invoices_visa', 'site.invoices'] as $r) {
            $all->assertSee('href="' . route($r) . '"', false);
        }
    }

    public function test_flight_invoice_only_in_flight_and_all_visa_only_in_visa_and_all(): void
    {
        $f = $this->flight();
        $visaType = $this->makeVisa();
        $v = $this->visa($visaType);

        $this->assertEqualsCanonicalizing([$f->es_id, $v->es_id], $this->listRows(null)->keys()->all());
        $this->assertSame([$f->es_id], $this->listRows('flight')->keys()->all());
        $this->assertSame([$v->es_id], $this->listRows('visa')->keys()->all());

        $fr = $this->listRows(null)[$f->es_id];
        $this->assertSame(['flight', '✈️ طيران', 'TEST-AIR', '2026-10-05', 'CAI / DXB', 'PNR' . $this->tag],
            [$fr['kind'], $fr['kind_label'], $fr['carrier'], $fr['invoice_travel_date'], $fr['locations'], $fr['passengers'][0]['booking']]);

        $vr = $this->listRows(null)[$v->es_id];
        $this->assertSame(['visa', '🛂 تأشيرة', $visaType->visa_name, '', '', 'PP' . $this->tag, 'APP' . $this->tag],
            [$vr['kind'], $vr['kind_label'], $vr['carrier'], $vr['invoice_travel_date'], $vr['locations'],
             $vr['passengers'][0]['passport'], $vr['passengers'][0]['ticket']]);

        // the kind limits the whole list (recordsTotal too), not only this test's rows
        $total = fn ($kind) => $this->getJson(route('site.invoices_list_data', ['draw' => 1, 'length' => 1, 'kind' => $kind]))->json('recordsTotal');
        $this->assertSame(Invoice::where('invoice_section', 1)->count(), $total('flight'));
        $this->assertSame(Invoice::where('invoice_section', Visa::SECTION)->count(), $total('visa'));
        $this->assertSame(Invoice::count(), $total(null));
    }

    public function test_counter_visa_invoice_stays_counter_and_is_listed_as_visa(): void
    {
        $v = $this->visa($this->makeVisa(), ['counter' => '1']);
        $counterId = CounterPayments::counterIds()[0];
        $this->assertSame((string) $counterId, (string) $v->invoice_beneficiaries);

        $row = $this->listRows('visa')[$v->es_id];
        $this->assertSame('visa', $row['kind']);
        $this->assertSame(Supplier::find($counterId)->name, $row['beneficiary']);
        $this->assertNotNull($row['counter'], 'counter payment status shown as for any Counter Customer invoice');
        $this->assertArrayNotHasKey($v->es_id, $this->listRows('flight')->all());

        $rep = $this->report(['section' => Visa::SECTION, 'type' => 'counter']);
        $this->assertSame([$v->es_id], $rep->pluck('es_id')->unique()->values()->all());
    }

    public function test_visa_edit_refund_and_reissue_keep_the_visa_classification(): void
    {
        $visaType = $this->makeVisa();
        $v = $this->visa($visaType);
        $pax = TicketUser::where('ticket_system_id', $v->ticket_system_id)->firstOrFail();

        // edit (Visa edit form)
        $this->post(route('site.invoices_visa_save_update'), [
            'id' => $v->id, 'visa_name' => $visaType->visa_name, 'invoice_beneficiaries' => $this->customer->id, 'vendor_id' => $this->vendor->id,
            'applicants' => [['id' => $pax->id, 'name' => $this->tag . ' VISA APPLICANT', 'passport' => 'PP' . $this->tag, 'visa_number' => 'APP' . $this->tag, 'cost' => '2100', 'sale' => '2600']],
        ])->assertRedirect(route('site.invoices_edit', $v->id))->assertSessionHasNoErrors();

        // refund (existing refund workflow)
        $this->post(route('site.invoices_refund_save'), ['id' => $v->id, 'refund_mode' => 'full', 'bought_price_total' => '1800', 'net_pice_total' => '1500'])
            ->assertSessionHasNoErrors();
        $refund = $this->last();
        $this->assertStringStartsWith('FLY-RD', $refund->es_id);

        // re-issue (Visa re-issue form -> the existing re-issue workflow)
        $this->post(route('site.invoices_reissue_visa_save'), [
            'id' => $v->id, 'visa_name' => $visaType->visa_name, 'vendor_id' => $this->vendor->id, 'invoice_beneficiaries' => $this->customer->id,
            'applicants' => [['name' => $this->tag . ' VISA REISSUE', 'passport' => 'PP' . $this->tag, 'visa_number' => 'APP2' . $this->tag, 'cost' => '2300', 'sale' => '2800']],
        ])->assertRedirect(route('site.invoices'))->assertSessionHasNoErrors();
        $reissue = $this->last();

        foreach ([$refund, $reissue] as $op) {
            $this->assertSame('2', (string) $op->invoice_section, $op->es_id . ' keeps section 2');
        }
        $visaList = $this->listRows('visa');
        $this->assertEqualsCanonicalizing([$v->es_id, $refund->es_id, $reissue->es_id], $visaList->keys()->all());
        $this->assertSame([], $this->listRows('flight')->keys()->all());
        $this->assertSame('sale', $visaList[$v->es_id]['op']);
        $this->assertGreaterThan(0, $visaList[$v->es_id]['edits'], 'shown as edited (the existing marker count, one per account)');
        $this->assertSame('refund', $visaList[$refund->es_id]['op']);
        $this->assertSame('reissue', $visaList[$reissue->es_id]['op']);
        foreach ($visaList as $row) {
            $this->assertSame(['visa', '', ''], [$row['kind'], $row['invoice_travel_date'], $row['locations']]);
        }

        $rep = $this->report(['section' => Visa::SECTION]);
        $this->assertEqualsCanonicalizing(['sale', 'refund', 'reissue'], $rep->pluck('op')->unique()->values()->all());
        $this->assertSame([], $this->report(['section' => 1])->all());
    }

    // ------------------------------------------------------------------ detailed report

    public function test_report_flight_visa_filter_and_visa_rows(): void
    {
        $f = $this->flight();
        $visaType = $this->makeVisa();
        $v = $this->visa($visaType);

        $this->assertEqualsCanonicalizing([$f->es_id, $v->es_id], $this->report([])->pluck('es_id')->all());
        $this->assertSame([$f->es_id], $this->report(['section' => 1])->pluck('es_id')->all());
        $this->assertSame([$v->es_id], $this->report(['section' => 2])->pluck('es_id')->all());

        $vr = $this->report(['section' => 2])->first();
        $this->assertSame(['visa', '🛂 تأشيرة', $visaType->visa_name, '', '', '', 'APP' . $this->tag, 'PP' . $this->tag, 2000.0, 2500.0, 500.0],
            [$vr['kind'], $vr['kind_label'], $vr['airline'], $vr['travel_date'], $vr['route'], $vr['pnr'], $vr['ticket'], $vr['passport'],
             $vr['purchase'], $vr['sale'], $vr['profit']]);
        $fr = $this->report(['section' => 1])->first();
        $this->assertSame(['flight', '✈️ طيران', 'TEST-AIR', '2026-10-05', 'CAI - DXB', 'PNR' . $this->tag, 'TKT' . $this->tag, ''],
            [$fr['kind'], $fr['kind_label'], $fr['airline'], $fr['travel_date'], $fr['route'], $fr['pnr'], $fr['ticket'], $fr['passport']]);
    }

    public function test_report_flight_only_filters_do_not_apply_to_visa_invoices(): void
    {
        $f = $this->flight();
        $visaType = $this->makeVisa();
        $v = $this->visa($visaType);
        // an airline with the visa type's name: the airline filter still never returns the visa invoice
        $sameName = $this->flight($visaType->visa_name);

        $travel = ['travel_from' => '2026-10-05', 'travel_to' => '2026-10-05'];
        $this->assertEqualsCanonicalizing([$f->es_id, $sameName->es_id], $this->report($travel)->pluck('es_id')->all());
        // visa section: travel date / airline filters are dropped (a visa invoice has none)
        $r = new InvoiceFullReport($travel + ['airline' => 'TEST-AIR', 'section' => 2, 'search' => $this->tag], $this->admin);
        $this->assertSame([null, null, null], [$r->f['travel_from'], $r->f['travel_to'], $r->f['airline']]);
        $this->assertSame([$v->es_id], $r->rows()->pluck('es_id')->all());

        $this->assertSame([$sameName->es_id], $this->report(['airline' => $visaType->visa_name])->pluck('es_id')->all());
        $this->assertSame([$v->es_id], $this->report(['visa_type' => $visaType->visa_name])->pluck('es_id')->all());
        $this->assertNull((new InvoiceFullReport(['visa_type' => 'X', 'section' => 1], $this->admin))->f['visa_type'], 'flight section drops the visa type');

        // PNR / ticket / passport search
        $this->assertEqualsCanonicalizing([$f->es_id, $sameName->es_id], $this->report(['q' => 'PNR' . $this->tag])->pluck('es_id')->all());
        $this->assertSame([$v->es_id], $this->report(['q' => 'PP' . $this->tag])->pluck('es_id')->all());
        $this->assertSame([$v->es_id], $this->report(['q' => 'APP' . $this->tag])->pluck('es_id')->all());

        // grouping by airline keeps a visa type apart from an airline of the same name
        $labels = collect((new InvoiceFullReport(['search' => $this->tag], $this->admin))->groups('airline'))->pluck('label')->all();
        $this->assertContains('🛂 ' . $visaType->visa_name, $labels);
        $this->assertContains($visaType->visa_name, $labels);
    }

    public function test_report_screens_excel_and_print(): void
    {
        $v = $this->visa($this->makeVisa());
        $page = $this->get(route('site.invoices_full_report'))->assertOk()
            ->assertSee('name="visa_type"', false)->assertSee('✈️ فواتير الطيران')->assertSee('🛂 فواتير التأشيرات')->assertSee('طيران / تأشيرة');
        $data = $this->getJson(route('site.invoices_full_report_data', ['draw' => 1, 'start' => 0, 'length' => 10, 'section' => 2, 'search' => ['value' => $this->tag]]))
            ->assertOk()->json('data');
        $this->assertSame([$v->es_id, 'visa', '🛂 تأشيرة', 'PP' . $this->tag], [$data[0]['es_id'], $data[0]['kind'], $data[0]['kind_label'], $data[0]['passport']]);
        $this->get(route('site.invoices_full_report_print', ['section' => 2, 'search' => $this->tag]))->assertOk()->assertSee('🛂 تأشيرة')->assertSee('PP' . $this->tag);

        // Excel: every detail row (and the totals row) has one cell per column
        $report = new InvoiceFullReport(['section' => 2, 'search' => $this->tag], $this->admin);
        $rows = (new InvoiceFullReportExport($report))->sheets()[1]->array(); // «التفاصيل»: header, rows, totals
        $cols = count(InvoiceFullReportExport::DETAIL_COLUMNS);
        foreach ($rows as $row) {
            $this->assertCount($cols, $row);
        }
        $this->assertSame('الإجمالي', end($rows)[0]);
        $this->assertEquals(2000, end($rows)[array_search('purchase', array_keys(InvoiceFullReportExport::DETAIL_COLUMNS))]);
    }

    // ------------------------------------------------------------------ performance

    public function test_no_n_plus_one_in_list_and_report(): void
    {
        $count = function (callable $fn) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $fn();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();
            return $n;
        };
        // a fixed set of batched queries per page (the Counter Customer payment batch -- 4 queries --
        // runs once when the page holds a counter invoice), whatever the number of rows
        foreach ([null, 'flight', 'visa'] as $kind) {
            $mid = $count(fn () => $this->getJson(route('site.invoices_list_data', ['draw' => 1, 'length' => 100, 'kind' => $kind]))->assertOk());
            $big = $count(fn () => $this->getJson(route('site.invoices_list_data', ['draw' => 1, 'length' => 250, 'kind' => $kind]))->assertOk());
            $this->assertSame($mid, $big, "list ($kind): same number of queries for 100 and 250 rows");
            $this->assertLessThanOrEqual(13, $big);
        }
        // report: fixed batched queries (the refund query runs once when the result holds refunds)
        $year = $count(fn () => (new InvoiceFullReport(['date_from' => '2026-01-01'], $this->admin))->rows());
        $all = $count(fn () => (new InvoiceFullReport([], $this->admin))->rows());
        $this->assertSame($year, $all, 'report: same number of queries for one year and for every invoice');
        $this->assertLessThanOrEqual(7, $all);
        foreach ([1, 2] as $section) {
            $this->assertLessThanOrEqual(7, $count(fn () => (new InvoiceFullReport(['section' => $section], $this->admin))->rows()));
        }
    }
}
