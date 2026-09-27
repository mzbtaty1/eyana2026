<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{AccountStatement, Invoice, Supplier, TicketUser, TicketVendor, User, Visa};
use App\Services\CounterPayments;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Visa Invoice (Visa step 2): the invoice type chooser and the separate Visa Invoice form,
 * saved as invoice_section 2 through the same store() / ledger rows as flight invoices.
 * Visa types are names only (their prices are never used). Dev DB, rolled back.
 */
class VisaInvoiceTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $employee;
    private int $maxVisaId;
    private Supplier $customer;
    private Supplier $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        Storage::fake('public');
        $this->admin = User::where('account_type', 2)->where('status', 1)->firstOrFail();
        $this->employee = User::where('account_type', 1)->where('status', 1)->firstOrFail();
        $this->actingAs($this->admin);
        $this->maxVisaId = (int) Visa::max('id');
        $active = Supplier::where('status', 1)->where('acc_type', '!=', 3)->whereNotIn('id', CounterPayments::counterIds());
        [$this->customer, $this->vendor] = $active->orderBy('id')->limit(2)->get()->all();
    }

    /** visas is MyISAM on dev (no rollback): remove the visa types this test created. */
    protected function tearDown(): void
    {
        Visa::where('id', '>', $this->maxVisaId)->where('visa_name', 'like', 'TEST-VISA-%')->delete();
        parent::tearDown();
    }

    private function makeVisa(int $status = 1): Visa
    {
        // prices deliberately different from what the invoice posts: they must never be used
        return Visa::create(['visa_name' => 'TEST-VISA-' . uniqid(), 'visa_price' => '9999', 'visa_ext_price' => '8888', 'status' => $status]);
    }

    private function payload(Visa $visa, array $over = []): array
    {
        return array_merge([
            'visa_id' => $visa->id,
            'invoice_date' => '2026-09-27',
            'invoice_beneficiaries' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'applicants' => [
                ['name' => 'TEST APPLICANT ONE', 'passport' => 'A1234567', 'visa_number' => 'APP-001', 'cost' => '2000', 'sale' => '2500'],
                ['name' => 'TEST APPLICANT TWO', 'passport' => 'B7654321', 'visa_number' => '', 'cost' => '1500.50', 'sale' => '1800'],
            ],
            'invoice_comments' => 'TEST visa invoice',
        ], $over);
    }

    private function lastInvoice(): Invoice
    {
        return Invoice::orderByDesc('id')->firstOrFail();
    }

    public function test_visa_form_shows_visa_fields_only_and_active_types(): void
    {
        $active = $this->makeVisa();
        $disabled = $this->makeVisa(0);

        $res = $this->get(route('site.invoices_create_visa'))->assertOk();
        $res->assertSee('فاتورة تأشيرة')->assertSee('✈️ فاتورة طيران')->assertSee('🛂 فاتورة تأشيرة')
            ->assertSee(route('site.invoices_create'), false)
            ->assertSee('name="visa_id"', false)->assertSee('name="invoice_beneficiaries"', false)
            ->assertSee('name="vendor_id"', false)->assertSee('name="invoice_date"', false)
            ->assertSee('applicants[0][name]', false)->assertSee('applicants[0][passport]', false)
            ->assertSee('applicants[0][visa_number]', false)->assertSee('applicants[0][cost]', false)
            ->assertSee('applicants[0][sale]', false)->assertSee('name="invoice_comments"', false)
            ->assertSee('name="myPoster"', false)
            ->assertSee($active->visa_name)->assertDontSee($disabled->visa_name);

        // (the shared layout's own JS mentions #invoice_group_id, so match form fields by name=)
        foreach (['name="invoice_airline"', 'name="invoice_travel_date"', 'name="return_date"', 'name="from_location"', 'name="to_location"',
                  'name="invoice_group_id"', '[book_id]', '[tikcet_id]', '[client_type]', 'name="invoice_section"', 'name="invoice_currency"',
                  'خط الطيران', 'تاريخ السفر', 'رقم الحجز', 'رقم التكت'] as $flightField) {
            $res->assertDontSee($flightField, false);
        }
        // the visa type's stored prices are not offered anywhere on the form
        $res->assertDontSee('9999')->assertDontSee('8888');
    }

    public function test_counter_visa_form_keeps_the_counter_customer_fixed(): void
    {
        $counter = Supplier::findOrFail(CounterPayments::counterIds()[0]);
        $this->get(route('site.invoices_create_counter_visa'))->assertOk()
            ->assertSee('عميل كونتر')
            ->assertSee('name="counter" value="1"', false)
            ->assertSee('name="invoice_beneficiaries" value="' . $counter->id . '"', false)
            ->assertDontSee('<select class="form-control" name="invoice_beneficiaries"', false)
            ->assertSee(route('site.invoices_create_counter'), false); // chooser keeps counter mode
    }

    public function test_flight_form_is_unchanged_apart_from_the_chooser(): void
    {
        $this->get(route('site.invoices_create'))->assertOk()
            ->assertSee('✈️ فاتورة طيران')->assertSee(route('site.invoices_create_visa'), false)
            ->assertSee('action="' . route('site.invoices_save') . '"', false)
            ->assertSee('name="invoice_airline"', false)->assertSee('name="invoice_travel_date"', false)
            ->assertSee('name="from_location"', false)->assertSee('ticket_info[0][book_id]', false)
            ->assertSee('name="invoice_section"', false)->assertDontSee('applicants[0]', false);
        $this->get(route('site.invoices_create_counter'))->assertOk()
            ->assertSee(route('site.invoices_create_counter_visa'), false)->assertSee('name="invoice_airline"', false);
    }

    public function test_store_visa_saves_section_2_with_the_existing_invoice_and_ledger_rows(): void
    {
        $visa = $this->makeVisa();
        $before = Invoice::max('id');

        $this->post(route('site.invoices_visa_save'), $this->payload($visa, [
            'myPoster' => UploadedFile::fake()->create('visa.pdf', 20, 'application/pdf'),
        ]))->assertRedirect(route('site.invoices'))->assertSessionHasNoErrors();

        $inv = $this->lastInvoice();
        $this->assertGreaterThan($before, $inv->id);
        $this->assertSame('FLY-A' . $inv->id, $inv->es_id);
        $this->assertSame(Visa::SECTION, (int) $inv->invoice_section);
        $this->assertSame($visa->visa_name, $inv->invoice_airline);
        $this->assertSame('', (string) $inv->invoice_travel_date);
        $this->assertSame('', (string) $inv->from_location);
        $this->assertSame('', (string) $inv->to_location);
        $this->assertNull($inv->return_date);
        $this->assertSame((string) $this->customer->id, (string) $inv->invoice_beneficiaries);
        $this->assertSame('TEST visa invoice', $inv->invoice_comments);
        $this->assertSame((string) $this->admin->id, (string) $inv->invoice_create_by);
        $this->assertStringStartsWith('storage/pdf_files/', $inv->invoice_ticket_file);

        $users = TicketUser::where('ticket_system_id', $inv->ticket_system_id)->orderBy('id')->get();
        $this->assertCount(2, $users);
        $this->assertSame(['TEST APPLICANT ONE', 'A1234567', 'APP-001', '2000', '2500', '3', ''],
            [$users[0]->client_name, $users[0]->client_passport_id, $users[0]->client_ticket_id, (string) $users[0]->client_net_pice,
             (string) $users[0]->client_bought_price, (string) $users[0]->client_type, (string) $users[0]->client_booking_id]);
        $this->assertSame(['B7654321', '', '1500.50', '1800'],
            [$users[1]->client_passport_id, (string) $users[1]->client_ticket_id, (string) $users[1]->client_net_pice, (string) $users[1]->client_bought_price]);

        $this->assertSame((string) $this->vendor->id, (string) TicketVendor::where('ticket_system_id', $inv->ticket_system_id)->value('vendor_id'));

        $rows = AccountStatement::where('es_id', $inv->es_id)->get()->keyBy('supp_client_id');
        $this->assertCount(2, $rows);
        $vendorRow = $rows[$this->vendor->id];
        $customerRow = $rows[$this->customer->id];
        $this->assertEquals(3500.50, (float) $vendorRow->credit_balance);   // total cost
        $this->assertEquals(-3500.50, (float) $vendorRow->ledger_net_effect);
        $this->assertEquals(0, (float) $vendorRow->debit_balance);
        $this->assertEquals(4300, (float) $customerRow->debit_balance);     // total sale
        $this->assertEquals(4300, (float) $customerRow->ledger_net_effect);
        $this->assertEquals(0, (float) $customerRow->credit_balance);
        foreach ([$vendorRow, $customerRow] as $row) {
            $this->assertSame(Visa::SECTION, (int) $row->invoice_type);
            $this->assertSame(1, (int) $row->transaction_type);
        }
    }

    public function test_attachment_is_optional(): void
    {
        $visa = $this->makeVisa();
        $this->post(route('site.invoices_visa_save'), $this->payload($visa))->assertRedirect(route('site.invoices'));
        $this->assertSame('no', $this->lastInvoice()->invoice_ticket_file);
    }

    public function test_counter_visa_invoice_always_uses_the_counter_customer(): void
    {
        $visa = $this->makeVisa();
        $this->post(route('site.invoices_visa_save'), $this->payload($visa, ['counter' => '1']))
            ->assertRedirect(route('site.invoices'));
        $inv = $this->lastInvoice();
        $this->assertSame((string) CounterPayments::counterIds()[0], (string) $inv->invoice_beneficiaries);
        $this->assertSame(Visa::SECTION, (int) $inv->invoice_section);
        $this->assertTrue(AccountStatement::where('es_id', $inv->es_id)->where('supp_client_id', CounterPayments::counterIds()[0])->where('debit_balance', 4300)->exists());
    }

    public function test_employee_invoice_is_always_their_own(): void
    {
        $visa = $this->makeVisa();
        $this->actingAs($this->employee)
            ->post(route('site.invoices_visa_save'), $this->payload($visa, ['added_user' => $this->admin->id]))
            ->assertRedirect(route('site.invoices'));
        $this->assertSame((string) $this->employee->id, (string) $this->lastInvoice()->invoice_create_by);
    }

    public function test_invalid_visa_invoices_are_rejected_and_nothing_is_saved(): void
    {
        $visa = $this->makeVisa();
        $disabled = $this->makeVisa(0);
        $before = Invoice::max('id');
        $ledgerBefore = AccountStatement::max('id');
        $cases = [
            'disabled visa type' => [$disabled, []],
            'no visa type' => [$visa, ['visa_id' => '']],
            'no applicants' => [$visa, ['applicants' => []]],
            'no passport' => [$visa, ['applicants' => [['name' => 'X', 'passport' => '', 'cost' => '1', 'sale' => '2']]]],
            'no name' => [$visa, ['applicants' => [['name' => '', 'passport' => 'P1', 'cost' => '1', 'sale' => '2']]]],
            'negative cost' => [$visa, ['applicants' => [['name' => 'X', 'passport' => 'P1', 'cost' => '-5', 'sale' => '2']]]],
            'missing sale' => [$visa, ['applicants' => [['name' => 'X', 'passport' => 'P1', 'cost' => '1']]]],
            'no supplier' => [$visa, ['vendor_id' => '']],
            'bad attachment' => [$visa, ['myPoster' => UploadedFile::fake()->create('x.exe', 5, 'application/octet-stream')]],
        ];
        foreach ($cases as $label => [$v, $over]) {
            $this->from(route('site.invoices_create_visa'))
                ->post(route('site.invoices_visa_save'), $this->payload($v, $over))
                ->assertRedirect(route('site.invoices_create_visa'))
                ->assertSessionHasErrors();
            $this->assertSame($before, Invoice::max('id'), $label);
        }
        $this->assertSame($ledgerBefore, AccountStatement::max('id'));
    }

    public function test_flight_store_still_saves_section_1_without_passport(): void
    {
        $this->post(route('site.invoices_save'), [
            'invoice_date' => '2026-09-27', 'invoice_travel_date' => '2026-10-01', 'return_date' => null,
            'from_location' => 'CAI', 'to_location' => 'DXB', 'invoice_airline' => 'TEST AIR',
            'invoice_beneficiaries' => $this->customer->id, 'vendor_id' => $this->vendor->id,
            'invoice_section' => 1, 'invoice_comments' => null, 'invoice_currency' => 'جنية مصري', 'invoice_draft' => null,
            'added_user' => $this->admin->id,
            'ticket_info' => [['name' => 'TEST PAX', 'client_type' => 3, 'net_price' => 1000, 'bought_price' => 1200,
                'book_id' => 'PNR1', 'tikcet_id' => 'TKT1', 'client_phone' => null, 'passport_id' => 'SHOULD-NOT-SAVE']],
        ])->assertRedirect(route('site.invoices'));
        $inv = $this->lastInvoice();
        $this->assertSame(1, (int) $inv->invoice_section);
        $this->assertSame('2026-10-01', $inv->invoice_travel_date);
        $pax = TicketUser::where('ticket_system_id', $inv->ticket_system_id)->firstOrFail();
        $this->assertNull($pax->client_passport_id);
        $this->assertSame(['PNR1', 'TKT1'], [$pax->client_booking_id, $pax->client_ticket_id]);
    }

    // ---------------------------------------------------------------- Step 2 gap fixes

    private function createVisaInvoice(Visa $visa, array $over = []): Invoice
    {
        $this->post(route('site.invoices_visa_save'), $this->payload($visa, $over))->assertRedirect(route('site.invoices'));
        return $this->lastInvoice();
    }

    /** Visa edit payload for $inv's two applicants (created by payload()). */
    private function editPayload(Invoice $inv, string $visaName, array $over = []): array
    {
        $users = TicketUser::where('ticket_system_id', $inv->ticket_system_id)->orderBy('id')->get();
        return array_merge([
            'id' => $inv->id, 'visa_name' => $visaName,
            'invoice_beneficiaries' => $this->customer->id, 'vendor_id' => $this->vendor->id,
            'applicants' => [
                ['id' => $users[0]->id, 'name' => 'EDITED ONE', 'passport' => 'Z9999999', 'visa_number' => 'VISA-77', 'cost' => '2100', 'sale' => '2600'],
                ['id' => $users[1]->id, 'name' => 'TEST APPLICANT TWO', 'passport' => 'B7654321', 'visa_number' => '', 'cost' => '1500.50', 'sale' => '1800'],
            ],
            'invoice_comments' => 'edited',
        ], $over);
    }

    public function test_visa_invoice_edit_opens_the_visa_form(): void
    {
        $inv = $this->createVisaInvoice($this->makeVisa());
        $res = $this->get(route('site.invoices_edit', $inv->id))->assertOk()
            ->assertSee('تعديل فاتورة تأشيرة')
            ->assertSee('action="' . route('site.invoices_visa_save_update') . '"', false)
            ->assertSee('value="A1234567"', false)->assertSee('value="APP-001"', false)
            ->assertSee('value="2000"', false)->assertSee('value="2500"', false);
        foreach (['name="invoice_airline"', 'name="invoice_travel_date"', 'name="return_date"', 'name="from_location"', 'name="to_location"',
                  'name="invoice_section"', 'name="invoice_currency"', '[book_id]', '[tikcet_id]', '[client_type]', 'تعديل راكب واحد فقط', 'خط الطيران', 'تاريخ السفر'] as $flightField) {
            $res->assertDontSee($flightField, false);
        }
        // the flight single-passenger edit page sends visa invoices to the Visa edit form
        $this->get(route('site.invoices_edit_passenger', $inv->id))->assertRedirect(route('site.invoices_edit', $inv->id));
    }

    public function test_visa_invoice_update_uses_the_existing_edit_ledger(): void
    {
        $visa = $this->makeVisa();
        $other = $this->makeVisa();
        $inv = $this->createVisaInvoice($visa);
        $firstRows = AccountStatement::where('es_id', $inv->es_id)->orderBy('id')->get()->toArray();
        $users = TicketUser::where('ticket_system_id', $inv->ticket_system_id)->orderBy('id')->get();

        $this->post(route('site.invoices_visa_save_update'), $this->editPayload($inv, $other->visa_name))
            ->assertRedirect(route('site.invoices_edit', $inv->id))->assertSessionHasNoErrors();

        $fresh = $inv->fresh();
        $this->assertSame([Visa::SECTION, $other->visa_name, '', '', '', 'edited', 'no', $inv->invoice_date, (string) $this->admin->id],
            [(int) $fresh->invoice_section, $fresh->invoice_airline, (string) $fresh->invoice_travel_date, (string) $fresh->from_location,
             (string) $fresh->to_location, $fresh->invoice_comments, $fresh->invoice_ticket_file, $fresh->invoice_date, (string) $fresh->invoice_create_by]);
        $u0 = $users[0]->fresh();
        $this->assertSame(['EDITED ONE', 'Z9999999', 'VISA-77', '3', ''],
            [$u0->client_name, $u0->client_passport_id, $u0->client_ticket_id, (string) $u0->client_type, (string) $u0->client_booking_id]);
        $this->assertSame('B7654321', $users[1]->fresh()->client_passport_id);
        $this->assertSame(2, TicketUser::where('ticket_system_id', $inv->ticket_system_id)->count());

        // the original ledger rows are kept; the edit adds adjustment rows, so each account nets to the new totals
        // (beginEdit() records the passenger split in their description -- as for flight edits -- the amounts stay)
        $money = fn ($rows) => array_map(fn ($r) => array_intersect_key($r, array_flip(['id', 'supp_client_id', 'debit_balance', 'credit_balance', 'ledger_net_effect', 'invoice_date', 'crt_date'])), $rows);
        $this->assertEquals($money($firstRows), $money(AccountStatement::whereIn('id', array_column($firstRows, 'id'))->orderBy('id')->get()->toArray()));
        $this->assertGreaterThan(2, AccountStatement::where('es_id', $inv->es_id)->count());
        $net = fn ($id) => round((float) AccountStatement::where('es_id', $inv->es_id)->where('supp_client_id', $id)->sum('ledger_net_effect'), 2);
        $this->assertEquals(4400.0, $net($this->customer->id));   // sale 2600 + 1800
        $this->assertEquals(-3600.5, $net($this->vendor->id));    // cost 2100 + 1500.50
    }

    public function test_invalid_visa_edits_are_rejected_and_nothing_changes(): void
    {
        $visa = $this->makeVisa();
        $disabled = $this->makeVisa(0);
        $inv = $this->createVisaInvoice($visa);
        $ledger = AccountStatement::max('id');
        $users = TicketUser::where('ticket_system_id', $inv->ticket_system_id)->orderBy('id')->get()->toArray();
        $p = $this->editPayload($inv, $visa->visa_name);
        $foreignId = TicketUser::where('ticket_system_id', '!=', $inv->ticket_system_id)->value('id');
        $cases = [
            'disabled visa type' => ['visa_name' => $disabled->visa_name],
            'unknown visa type' => ['visa_name' => 'NOT-A-VISA'],
            'applicant removed' => ['applicants' => [$p['applicants'][0]]],
            'foreign applicant' => ['applicants' => [array_merge($p['applicants'][0], ['id' => $foreignId]), $p['applicants'][1]]],
            'no passport' => ['applicants' => [array_merge($p['applicants'][0], ['passport' => '']), $p['applicants'][1]]],
            'negative cost' => ['applicants' => [array_merge($p['applicants'][0], ['cost' => '-1']), $p['applicants'][1]]],
        ];
        foreach ($cases as $label => $over) {
            $this->from(route('site.invoices_edit', $inv->id))
                ->post(route('site.invoices_visa_save_update'), array_merge($p, $over))
                ->assertRedirect(route('site.invoices_edit', $inv->id))->assertSessionHasErrors();
            $this->assertSame($ledger, AccountStatement::max('id'), $label);
        }
        $this->assertEquals($users, TicketUser::where('ticket_system_id', $inv->ticket_system_id)->orderBy('id')->get()->toArray());
        $this->assertSame($visa->visa_name, $inv->fresh()->invoice_airline);
    }

    public function test_counter_visa_invoice_edit_keeps_the_counter_customer(): void
    {
        $counterId = CounterPayments::counterIds()[0];
        $inv = $this->createVisaInvoice($this->makeVisa(), ['counter' => '1']);
        $this->get(route('site.invoices_edit', $inv->id))->assertOk()
            ->assertSee('name="invoice_beneficiaries" value="' . $counterId . '"', false)
            ->assertDontSee('<select class="form-control" name="invoice_beneficiaries"', false);
        $this->post(route('site.invoices_visa_save_update'), $this->editPayload($inv, $inv->invoice_airline, ['invoice_beneficiaries' => $this->customer->id]))
            ->assertRedirect(route('site.invoices_edit', $inv->id));
        $this->assertSame((string) $counterId, (string) $inv->fresh()->invoice_beneficiaries);
    }

    public function test_flight_routes_cannot_create_edit_or_reclassify_visa_invoices(): void
    {
        $inv = $this->createVisaInvoice($this->makeVisa());
        $pax = TicketUser::where('ticket_system_id', $inv->ticket_system_id)->orderBy('id')->first();
        $ledger = AccountStatement::max('id');

        // flight whole-invoice edit and single-passenger edit refuse a visa invoice
        $this->post(route('site.invoices_save_update'), ['id' => $inv->id, 'invoice_section' => 1, 'vendor_id' => $this->vendor->id,
            'invoice_beneficiaries' => $this->customer->id, 'ticket_info' => [['id' => $pax->id, 'name' => 'X', 'client_type' => 3,
            'net_price' => 1, 'bought_price' => 1, 'book_id' => '', 'tikcet_id' => '', 'client_phone' => null]]])->assertSessionHasErrors('msg');
        $this->post(route('site.invoices_save_passenger'), ['id' => $inv->id, 'passenger_id' => $pax->id, 'name' => 'X',
            'client_type' => 3, 'net_price' => 1, 'bought_price' => 1])->assertSessionHasErrors('msg');
        $this->assertSame($ledger, AccountStatement::max('id'));
        $this->assertSame(['TEST APPLICANT ONE', '2'], [$pax->fresh()->client_name, (string) $inv->fresh()->invoice_section]);

        // the flight edit form cannot turn a flight invoice into a visa invoice
        $flight = Invoice::where('invoice_section', 1)->where('invoice_shared', 0)->orderByDesc('id')->firstOrFail();
        $this->post(route('site.invoices_save_update'), ['id' => $flight->id, 'invoice_section' => Visa::SECTION])->assertSessionHasErrors('msg');
        $this->assertSame('1', (string) $flight->fresh()->invoice_section);
        $this->assertSame($ledger, AccountStatement::max('id'));
    }

    public function test_flight_forms_no_longer_offer_the_visa_classification(): void
    {
        $this->get(route('site.invoices_create'))->assertOk()
            ->assertSee('name="invoice_section"', false)->assertSee('>فواتير الطيران</option>', false)
            ->assertDontSee('>فواتير تأشيرات</option>', false);
        $flight = Invoice::where('invoice_section', 1)->where('invoice_shared', 0)->orderByDesc('id')->firstOrFail();
        $this->get(route('site.invoices_edit', $flight->id))->assertOk()
            ->assertSee('name="invoice_airline"', false)->assertSee('name="invoice_section"', false)
            ->assertDontSee('>فواتير تأشيرات</option>', false);
    }

    public function test_visa_type_screens_have_no_prices_and_stored_prices_are_untouched(): void
    {
        $visa = $this->makeVisa(); // stored prices 9999 / 8888
        foreach ([route('site.visas'), route('site.visas_create'), route('site.visas_edit', $visa->id)] as $url) {
            $this->get($url)->assertOk()->assertDontSee('visa_price', false)->assertDontSee('visa_ext_price', false)
                ->assertDontSee('سعر التأشيرة')->assertDontSee('سعر التنفيذ')->assertDontSee('9999');
        }

        $name = 'TEST-VISA-' . uniqid();
        $this->post(route('site.visas_save'), ['visa_name' => $name, 'status' => '1'])->assertRedirect(route('site.visas'))->assertSessionHasNoErrors();
        $new = Visa::where('visa_name', $name)->firstOrFail();
        $this->assertEquals([0, 0, 1], [(float) $new->visa_price, (float) $new->visa_ext_price, (int) $new->status]);

        $this->post(route('site.visas_update'), ['id' => $visa->id, 'visa_name' => $visa->visa_name, 'status' => '0', 'visa_price' => '1'])
            ->assertSessionDoesntHaveErrors(['visa_name', 'status']);
        $this->assertEquals(['9999', '8888', 0], [$visa->fresh()->visa_price, $visa->fresh()->visa_ext_price, (int) $visa->fresh()->status]);
    }

    public function test_no_attachment_marker_is_not_shown_as_a_file(): void
    {
        foreach (['no', '', null] as $none) {
            $this->assertNull(Invoice::ticketFileOrNull($none));
        }
        $this->assertSame('storage/pdf_files/x.pdf', Invoice::ticketFileOrNull('storage/pdf_files/x.pdf'));

        $noFile = $this->createVisaInvoice($this->makeVisa());
        $withFile = $this->createVisaInvoice($this->makeVisa(), ['myPoster' => UploadedFile::fake()->create('v.pdf', 10, 'application/pdf')]);
        $this->assertSame('no', $noFile->invoice_ticket_file, 'the stored marker is unchanged');

        $rows = collect($this->getJson(route('site.invoices_list_data', ['draw' => 1, 'start' => 0, 'length' => 25]))->assertOk()->json('data'))->keyBy('es_id');
        $this->assertNull($rows[$noFile->es_id]['invoice_ticket_file']);
        $this->assertSame($withFile->invoice_ticket_file, $rows[$withFile->es_id]['invoice_ticket_file']);

        $this->get(route('site.invoices_edit', $noFile->id))->assertSee('لا يوجد مرفق')->assertDontSee('عرض المرفق');
        $this->get(route('site.invoices_edit', $withFile->id))->assertSee('عرض المرفق')->assertSee($withFile->invoice_ticket_file, false);
        $this->get(route('site.invoices_confirm', $noFile->id))->assertOk()->assertSee('لا يوجد ملف مرفق')->assertDontSee('لمعاينة الملف');
    }
}
