<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{AccountStatement, Invoice, Supplier, TicketUser, User, Visa};
use App\Services\{CounterPayments, InvoicePassengerLedger};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

/**
 * Visa Invoice re-issue versus flight re-issue: a visa invoice (section 2) opens the Visa
 * re-issue form (no travel date / route / PNR; visa type, passport and application number
 * carried over) and is saved through the existing invoices_reissue_save() -- same FLY-RS
 * invoice, ledger rows and re-issue marker. The flight re-issue is unchanged. Dev DB, rolled back.
 */
class VisaReissueTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private int $maxVisaId;
    private Supplier $customer;
    private Supplier $vendor;

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
    }

    /** visas is MyISAM on dev (no rollback): remove the visa types this test created. */
    protected function tearDown(): void
    {
        Visa::where('id', '>', $this->maxVisaId)->where('visa_name', 'like', 'TEST-VISA-%')->delete();
        parent::tearDown();
    }

    private function makeVisa(int $status = 1): Visa
    {
        return Visa::create(['visa_name' => 'TEST-VISA-' . uniqid(), 'visa_price' => 0, 'visa_ext_price' => 0, 'status' => $status]);
    }

    private function last(): Invoice
    {
        return Invoice::orderByDesc('id')->firstOrFail();
    }

    private function visaInvoice(Visa $visa, array $over = []): Invoice
    {
        $this->post(route('site.invoices_visa_save'), array_merge([
            'visa_id' => $visa->id, 'invoice_date' => '2026-09-20',
            'invoice_beneficiaries' => $this->customer->id, 'vendor_id' => $this->vendor->id,
            'applicants' => [['name' => 'TEST APPLICANT ONE', 'passport' => 'A1234567', 'visa_number' => 'APP-001', 'cost' => '2000', 'sale' => '2500']],
            'myPoster' => UploadedFile::fake()->create('visa.pdf', 10, 'application/pdf'),
        ], $over))->assertRedirect(route('site.invoices'));
        return $this->last();
    }

    private function flightInvoice(): Invoice
    {
        $this->post(route('site.invoices_save'), [
            'invoice_date' => '2026-09-20', 'invoice_travel_date' => '2026-10-05', 'return_date' => null,
            'from_location' => 'CAI', 'to_location' => 'DXB', 'invoice_airline' => 'TEST-AIR',
            'invoice_beneficiaries' => $this->customer->id, 'vendor_id' => $this->vendor->id,
            'invoice_section' => 1, 'invoice_comments' => null, 'invoice_currency' => 'جنية مصري', 'invoice_draft' => null,
            'added_user' => $this->admin->id,
            'ticket_info' => [['name' => 'TEST PAX', 'client_type' => 2, 'net_price' => 1000, 'bought_price' => 1200,
                'book_id' => 'PNR1', 'tikcet_id' => 'TKT1', 'client_phone' => null]],
        ])->assertRedirect(route('site.invoices'));
        return $this->last();
    }

    /** Everything the original invoice owns, to prove a re-issue never touches it. */
    private function snapshot(Invoice $inv): array
    {
        return [
            (array) DB::table('invoices')->where('id', $inv->id)->first(),
            DB::table('ticket_users')->where('ticket_system_id', $inv->ticket_system_id)->orderBy('id')->get()->toArray(),
            DB::table('ticket_vendors')->where('ticket_system_id', $inv->ticket_system_id)->get()->toArray(),
            DB::table('account_statements')->where('es_id', $inv->es_id)->orderBy('id')->get()->toArray(),
        ];
    }

    private function visaPayload(Invoice $inv, string $visaName, array $over = []): array
    {
        return array_merge([
            'id' => $inv->id, 'visa_name' => $visaName,
            'invoice_beneficiaries' => $this->customer->id, 'vendor_id' => $this->vendor->id,
            'applicants' => [
                ['name' => 'TEST APPLICANT ONE', 'passport' => 'A1234567', 'visa_number' => 'APP-001', 'cost' => '2200', 'sale' => '2700'],
                ['name' => 'TEST APPLICANT NEW', 'passport' => 'N7654321', 'visa_number' => '', 'cost' => '1000', 'sale' => '1300'],
            ],
            'invoice_comments' => 'visa re-issue',
        ], $over);
    }

    // ------------------------------------------------------------------ visa re-issue

    public function test_visa_reissue_opens_the_visa_form_with_the_visa_data_carried_over(): void
    {
        $visa = $this->makeVisa();
        $inv = $this->visaInvoice($visa);
        $res = $this->get(route('site.invoices_reissue_create', $inv->es_id))->assertOk()
            ->assertSee('اعادة اصدار فاتورة تأشيرة')
            ->assertSee('action="' . route('site.invoices_reissue_visa_save') . '"', false)
            ->assertSee('value="' . $visa->visa_name . '" selected', false)
            ->assertSee('value="TEST APPLICANT ONE"', false)->assertSee('value="A1234567"', false)->assertSee('value="APP-001"', false);
        foreach (['name="invoice_travel_date"', 'name="return_date"', 'name="from_location"', 'name="to_location"', 'name="invoice_airline"',
                  'name="invoice_section"', 'name="invoice_currency"', '[book_id]', '[tikcet_id]', '[client_type]', 'خط الطيران', 'تاريخ السفر'] as $flightField) {
            $res->assertDontSee($flightField, false);
        }
    }

    public function test_visa_reissue_is_a_section_2_reissue_with_passports_and_the_existing_ledger(): void
    {
        $visa = $this->makeVisa();
        $other = $this->makeVisa();
        $inv = $this->visaInvoice($visa);
        $before = $this->snapshot($inv);

        $this->post(route('site.invoices_reissue_visa_save'), $this->visaPayload($inv, $other->visa_name))
            ->assertRedirect(route('site.invoices'))->assertSessionHasNoErrors();
        $rs = $this->last();

        $this->assertSame('FLY-RS' . $rs->id, $rs->es_id);
        $this->assertSame([Visa::SECTION, $other->visa_name, '', '', '', null, date('Y-m-d'), (string) $this->customer->id, 'visa re-issue', (string) $this->admin->id],
            [(int) $rs->invoice_section, $rs->invoice_airline, (string) $rs->invoice_travel_date, (string) $rs->from_location, (string) $rs->to_location,
             $rs->return_date, $rs->invoice_date, (string) $rs->invoice_beneficiaries, $rs->invoice_comments, (string) $rs->invoice_create_by]);
        $this->assertSame($inv->invoice_ticket_file, $rs->invoice_ticket_file, 'no new attachment: the original one is kept (existing re-issue rule)');

        $pax = TicketUser::where('ticket_system_id', $rs->ticket_system_id)->orderBy('id')->get();
        $this->assertSame([['TEST APPLICANT ONE', 'A1234567', 'APP-001', '', '3', '2200', '2700'], ['TEST APPLICANT NEW', 'N7654321', '', '', '3', '1000', '1300']],
            $pax->map(fn ($u) => [$u->client_name, $u->client_passport_id, (string) $u->client_ticket_id, (string) $u->client_booking_id,
                (string) $u->client_type, (string) $u->client_net_pice, (string) $u->client_bought_price])->all());
        $this->assertSame((string) $this->vendor->id, (string) DB::table('ticket_vendors')->where('ticket_system_id', $rs->ticket_system_id)->value('vendor_id'));

        $rows = AccountStatement::where('es_id', $rs->es_id)->where('transaction_type', 1)->get()->keyBy('supp_client_id');
        $this->assertCount(2, $rows);
        $this->assertEquals([0.0, 3200.0, -3200.0], [(float) $rows[$this->vendor->id]->debit_balance, (float) $rows[$this->vendor->id]->credit_balance, (float) $rows[$this->vendor->id]->ledger_net_effect]);
        $this->assertEquals([4000.0, 0.0, 4000.0], [(float) $rows[$this->customer->id]->debit_balance, (float) $rows[$this->customer->id]->credit_balance, (float) $rows[$this->customer->id]->ledger_net_effect]);
        foreach ($rows as $row) {
            $this->assertSame([Visa::SECTION, date('Y-m-d'), 'reissue'], [(int) $row->invoice_type, (string) $row->invoice_date, InvoicePassengerLedger::marker($row)['kind'] ?? null]);
        }

        $this->assertEquals($before, $this->snapshot($inv), 'the original visa invoice is untouched');
    }

    public function test_counter_visa_reissue_keeps_the_counter_customer(): void
    {
        $counterId = CounterPayments::counterIds()[0];
        $inv = $this->visaInvoice($this->makeVisa(), ['counter' => '1']);
        $this->get(route('site.invoices_reissue_create', $inv->es_id))->assertOk()
            ->assertSee('name="invoice_beneficiaries" value="' . $counterId . '"', false);
        $this->post(route('site.invoices_reissue_visa_save'), $this->visaPayload($inv, $inv->invoice_airline))->assertRedirect(route('site.invoices'));
        $rs = $this->last();
        $this->assertSame([(string) $counterId, Visa::SECTION], [(string) $rs->invoice_beneficiaries, (int) $rs->invoice_section]);
        $this->assertTrue(AccountStatement::where('es_id', $rs->es_id)->where('supp_client_id', $counterId)->where('debit_balance', 4000)->exists());
    }

    public function test_invalid_visa_reissues_are_rejected_and_nothing_is_saved(): void
    {
        $visa = $this->makeVisa();
        $disabled = $this->makeVisa(0);
        $inv = $this->visaInvoice($visa);
        $maxInvoice = Invoice::max('id');
        $maxLedger = AccountStatement::max('id');
        $p = $this->visaPayload($inv, $visa->visa_name);
        $cases = [
            'disabled visa type' => ['visa_name' => $disabled->visa_name],
            'unknown visa type' => ['visa_name' => 'NOT-A-VISA'],
            'no applicants' => ['applicants' => []],
            'no passport' => ['applicants' => [array_merge($p['applicants'][0], ['passport' => ''])]],
            'no cost' => ['applicants' => [array_merge($p['applicants'][0], ['cost' => ''])]],
            'negative sale' => ['applicants' => [array_merge($p['applicants'][0], ['sale' => '-1'])]],
            'bad attachment' => ['myPoster' => UploadedFile::fake()->create('x.exe', 5, 'application/octet-stream')],
        ];
        foreach ($cases as $label => $over) {
            $this->from(route('site.invoices_reissue_create', $inv->es_id))
                ->post(route('site.invoices_reissue_visa_save'), array_merge($p, $over))
                ->assertRedirect(route('site.invoices_reissue_create', $inv->es_id))->assertSessionHasErrors();
            $this->assertSame([$maxInvoice, $maxLedger], [Invoice::max('id'), AccountStatement::max('id')], $label);
        }
    }

    public function test_flight_reissue_route_refuses_a_visa_invoice_and_the_visa_route_a_flight_invoice(): void
    {
        $visaInv = $this->visaInvoice($this->makeVisa());
        $flight = $this->flightInvoice();
        $maxInvoice = Invoice::max('id');

        $this->post(route('site.invoices_reissue_save'), ['id' => $visaInv->id, 'invoice_section' => Visa::SECTION, 'vendor_id' => $this->vendor->id,
            'invoice_beneficiaries' => $this->customer->id, 'ticket_info' => []])->assertSessionHasErrors('msg');
        $this->post(route('site.invoices_reissue_visa_save'), $this->visaPayload($flight, 'X'))->assertNotFound();
        $this->assertSame($maxInvoice, Invoice::max('id'));
    }

    public function test_flight_reissue_cannot_become_a_visa_invoice(): void
    {
        $flight = $this->flightInvoice();
        $before = $this->snapshot($flight);
        $maxInvoice = Invoice::max('id');
        $maxLedger = AccountStatement::max('id');

        $this->post(route('site.invoices_reissue_save'), [
            'id' => $flight->id, 'vendor_id' => $this->vendor->id, 'invoice_beneficiaries' => $this->customer->id,
            'invoice_section' => Visa::SECTION, 'invoice_airline' => 'TEST-AIR', 'invoice_travel_date' => '2026-11-01', 'return_date' => null,
            'from_location' => 'CAI', 'to_location' => 'JED', 'invoice_comments' => null, 'invoice_currency' => 'جنية مصري', 'invoice_draft' => null,
            'ticket_info' => [['name' => 'TEST PAX', 'client_type' => 2, 'net_price' => 1100, 'bought_price' => 1400,
                'book_id' => 'PNR2', 'tikcet_id' => 'TKT2', 'client_phone' => null]],
        ])->assertSessionHasErrors('msg');

        $this->assertSame([$maxInvoice, $maxLedger], [Invoice::max('id'), AccountStatement::max('id')], 'nothing saved');
        $this->assertEquals($before, $this->snapshot($flight), 'the original flight invoice is untouched');
    }

    // ------------------------------------------------------------------ flight re-issue (unchanged)

    public function test_flight_reissue_is_unchanged(): void
    {
        $flight = $this->flightInvoice();
        $before = $this->snapshot($flight);

        $this->get(route('site.invoices_reissue_create', $flight->es_id))->assertOk()
            ->assertSee('action="' . route('site.invoices_reissue_save') . '"', false)
            ->assertSee('name="invoice_travel_date"', false)->assertSee('name="from_location"', false)
            ->assertSee('ticket_info[0][book_id]', false)->assertSee('name="invoice_section"', false)
            ->assertSee('>فواتير الطيران</option>', false)->assertDontSee('>فواتير تأشيرات</option>', false)
            ->assertDontSee('applicants[0]', false);

        $this->post(route('site.invoices_reissue_save'), [
            'id' => $flight->id, 'vendor_id' => $this->vendor->id, 'invoice_beneficiaries' => $this->customer->id,
            'invoice_section' => 1, 'invoice_airline' => 'TEST-AIR', 'invoice_travel_date' => '2026-11-01', 'return_date' => null,
            'from_location' => 'CAI', 'to_location' => 'JED', 'invoice_comments' => null, 'invoice_currency' => 'جنية مصري', 'invoice_draft' => null,
            'ticket_info' => [['name' => 'TEST PAX', 'client_type' => 2, 'net_price' => 1100, 'bought_price' => 1400,
                'book_id' => 'PNR2', 'tikcet_id' => 'TKT2', 'client_phone' => null, 'passport_id' => 'SHOULD-NOT-SAVE']],
        ])->assertRedirect(route('site.invoices'));
        $rs = $this->last();

        $this->assertSame(['FLY-RS' . $rs->id, 1, 'TEST-AIR', '2026-11-01', 'CAI', 'JED', date('Y-m-d')],
            [$rs->es_id, (int) $rs->invoice_section, $rs->invoice_airline, $rs->invoice_travel_date, $rs->from_location, $rs->to_location, $rs->invoice_date]);
        $pax = TicketUser::where('ticket_system_id', $rs->ticket_system_id)->firstOrFail();
        $this->assertSame(['TEST PAX', '2', 'PNR2', 'TKT2', null], [$pax->client_name, (string) $pax->client_type, $pax->client_booking_id, $pax->client_ticket_id, $pax->client_passport_id]);
        $rows = AccountStatement::where('es_id', $rs->es_id)->get()->keyBy('supp_client_id');
        $this->assertEquals([1100.0, 1400.0], [(float) $rows[$this->vendor->id]->credit_balance, (float) $rows[$this->customer->id]->debit_balance]);
        $this->assertSame(['1', 'reissue'], [(string) $rows[$this->vendor->id]->invoice_type, InvoicePassengerLedger::marker($rows[$this->vendor->id])['kind'] ?? null]);
        $this->assertEquals($before, $this->snapshot($flight), 'the original flight invoice is untouched');
    }
}
