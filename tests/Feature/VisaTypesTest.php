<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{AccountStatement, Invoice, Supplier, TicketUser, User, Visa};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

/**
 * Visa types («التأشيرات», Visa step 1): visas.status, admin-only management, a used
 * visa type cannot be deleted or renamed (only disabled), and store() saving the
 * passport number for visa invoices only (flight invoices unchanged). Dev DB, rolled back.
 */
class VisaTypesTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $employee;
    private int $maxVisaId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->admin = User::where('account_type', 2)->where('status', 1)->firstOrFail();
        $this->employee = User::where('account_type', 1)->where('status', 1)->firstOrFail();
        $this->actingAs($this->admin);
        $this->maxVisaId = (int) Visa::max('id');
    }

    /**
     * The visas table is MyISAM on dev (no transactions), so the rollback does not undo
     * visa rows: remove the ones this test created (every other table is InnoDB).
     */
    protected function tearDown(): void
    {
        Visa::where('id', '>', $this->maxVisaId)->where('visa_name', 'like', 'TEST-VISA-%')->delete();
        parent::tearDown();
    }

    private function makeVisa(array $over = []): Visa
    {
        $name = 'TEST-VISA-' . uniqid();
        $this->post(route('site.visas_save'), array_merge(['visa_name' => $name, 'visa_price' => '3000', 'visa_ext_price' => '2500', 'status' => '1'], $over))
            ->assertRedirect(route('site.visas'));
        return Visa::where('visa_name', $over['visa_name'] ?? $name)->firstOrFail();
    }

    /** A visa invoice (section 2) using $visa, cloned from an existing invoice row (rolled back). */
    private function useVisa(Visa $visa): void
    {
        $row = (array) DB::table('invoices')->orderByDesc('id')->first();
        unset($row['id']);
        DB::table('invoices')->insert(array_merge($row, ['es_id' => 'FLY-TEST-VISA', 'ticket_system_id' => 'TSTVISA' . uniqid(),
            'invoice_section' => Visa::SECTION, 'invoice_airline' => $visa->visa_name]));
    }

    public function test_no_test_visa_rows_are_left_behind(): void
    {
        // runs first: nothing from an earlier run (the visas table does not roll back)
        $this->assertSame(0, Visa::where('visa_name', 'like', 'TEST-VISA-%')->count());
    }

    public function test_migration_added_status_not_null_default_1_and_kept_existing_rows(): void
    {
        $this->assertTrue(Schema::hasColumn('visas', 'status'));
        $col = DB::selectOne("select column_type t, is_nullable n, column_default d from information_schema.columns where table_schema=? and table_name='visas' and column_name='status'", [DB::getDatabaseName()]);
        $this->assertSame('tinyint(4)', $col->t);
        $this->assertSame('NO', $col->n);
        $this->assertSame('1', trim((string) $col->d, "'"));
        $this->assertSame(0, Visa::where('status', '!=', 1)->whereIn('id', [3])->count(), 'the existing visa type (#3 «امارات») is active');
        $this->assertSame('امارات', Visa::find(3)->visa_name);
    }

    public function test_employees_cannot_open_or_change_visa_types(): void
    {
        $visa = $this->makeVisa();
        $this->actingAs($this->employee);
        $this->get(route('site.visas'))->assertForbidden();
        $this->get(route('site.visas_create'))->assertForbidden();
        $this->get(route('site.visas_edit', $visa->id))->assertForbidden();
        $this->post(route('site.visas_save'), ['visa_name' => 'X', 'visa_price' => 1, 'visa_ext_price' => 1, 'status' => 1])->assertForbidden();
        $this->post(route('site.visas_update'), ['id' => $visa->id, 'visa_name' => 'HACKED', 'visa_price' => 1, 'visa_ext_price' => 1, 'status' => 0])->assertForbidden();
        $this->post(route('site.visas_status', $visa->id))->assertForbidden();
        $this->post(route('site.visas_delete', $visa->id))->assertForbidden();
        $this->assertEquals([$visa->visa_name, 1], [$visa->fresh()->visa_name, (int) $visa->fresh()->status]);
        $this->assertFalse(Visa::where('visa_name', 'X')->exists());
    }

    public function test_admin_list_shows_status_usage_and_controls(): void
    {
        $unused = $this->makeVisa();
        $used = $this->makeVisa(['status' => '0']);
        $this->useVisa($used);
        $this->useVisa($used);

        $html = $this->get(route('site.visas'))->assertOk()->getContent();
        $row = fn (Visa $v) => substr($html, strpos($html, '<tr data-visa="' . $v->id . '">'), 3000);
        $this->assertStringContainsString('مفعلة', $row($unused));
        $this->assertStringContainsString('معطلة', $row($used));
        $this->assertMatchesRegularExpression('/data-order="2">2</', $row($used));
        $this->assertStringContainsString('id="delete-form-' . $unused->id . '"', $html);
        $this->assertStringNotContainsString('id="delete-form-' . $used->id . '"', $html);
        $this->assertStringContainsString('مستخدمة في فواتير: لا تُحذف', $row($used));
        $this->assertStringContainsString(route('site.visas_status', $used->id), $html);
        $this->assertStringContainsString('title="تفعيل"', $row($used));
        $this->assertStringContainsString('title="تعطيل"', $row($unused));
    }

    public function test_create_requires_a_valid_status(): void
    {
        $name = 'TEST-VISA-' . uniqid();
        $this->post(route('site.visas_save'), ['visa_name' => $name, 'visa_price' => '1', 'visa_ext_price' => '1'])->assertSessionHasErrors('status');
        $this->post(route('site.visas_save'), ['visa_name' => $name, 'visa_price' => '1', 'visa_ext_price' => '1', 'status' => '7'])->assertSessionHasErrors('status');
        $this->assertFalse(Visa::where('visa_name', $name)->exists());
        $this->assertSame(0, (int) $this->makeVisa(['status' => '0'])->status);
    }

    public function test_enable_disable_toggle(): void
    {
        $visa = $this->makeVisa();
        $logs = DB::table('logs')->count();
        $this->post(route('site.visas_status', $visa->id))->assertSessionHasErrors('msg');
        $this->assertSame(0, (int) $visa->fresh()->status);
        $this->post(route('site.visas_status', $visa->id));
        $this->assertSame(1, (int) $visa->fresh()->status);
        $this->assertSame($logs + 2, DB::table('logs')->count());
        $this->post(route('site.visas_status', 999999999))->assertNotFound();
    }

    public function test_used_visa_type_cannot_be_deleted_only_disabled(): void
    {
        $visa = $this->makeVisa();
        $this->useVisa($visa);
        $this->post(route('site.visas_delete', $visa->id))->assertSessionHasErrors('delete');
        $this->assertStringContainsString('يمكنك تعطيلها', session('errors')->first('delete'));
        $this->assertNotNull($visa->fresh());
        $this->post(route('site.visas_status', $visa->id));
        $this->assertSame(0, (int) $visa->fresh()->status, 'disabling a used type is allowed');
        $this->assertSame(1, DB::table('invoices')->where('invoice_airline', $visa->visa_name)->count(), 'its invoice is untouched');

        $unused = $this->makeVisa();
        $this->post(route('site.visas_delete', $unused->id))->assertSessionHasErrors('msg');
        $this->assertNull($unused->fresh(), 'an unused type can be deleted');
    }

    public function test_used_visa_type_cannot_be_renamed_but_other_fields_can_change(): void
    {
        $visa = $this->makeVisa();
        $this->useVisa($visa);
        $base = ['id' => $visa->id, 'visa_name' => $visa->visa_name, 'visa_price' => '3000', 'visa_ext_price' => '2500', 'status' => '1'];

        $this->post(route('site.visas_update'), array_merge($base, ['visa_name' => 'RENAMED']))->assertSessionHasErrors('visa_name');
        $this->assertSame($visa->visa_name, $visa->fresh()->visa_name);
        $this->assertStringContainsString('readonly', $this->get(route('site.visas_edit', $visa->id))->getContent());

        // visa types are names only: a posted price is ignored, the stored price columns stay as they are
        $storedPrices = [$visa->fresh()->visa_price, $visa->fresh()->visa_ext_price];
        $this->post(route('site.visas_update'), array_merge($base, ['visa_price' => '3200', 'status' => '0']))->assertSessionDoesntHaveErrors(['visa_name', 'status']);
        $this->assertEquals([$storedPrices[0], $storedPrices[1], 0], [$visa->fresh()->visa_price, $visa->fresh()->visa_ext_price, (int) $visa->fresh()->status]);

        $free = $this->makeVisa();
        $this->post(route('site.visas_update'), ['id' => $free->id, 'visa_name' => $free->visa_name . '-2', 'visa_price' => '1', 'visa_ext_price' => '1', 'status' => '1']);
        $this->assertSame($free->visa_name . '-2', $free->fresh()->visa_name, 'an unused type can be renamed');
    }

    public function test_historical_airline_visa_records_and_invoices_are_untouched(): void
    {
        foreach ([18, 19, 31] as $id) {
            $this->assertNotNull(DB::table('airlines')->where('id', $id)->first(), "airline #$id kept");
        }
        $this->assertSame(18, DB::table('invoices')->where('invoice_section', 1)
            ->whereIn('invoice_airline', DB::table('airlines')->whereIn('id', [18, 31])->select('airline_name'))->count());
        $this->assertSame('2', (string) DB::table('invoices')->where('es_id', 'FLY-A12743')->value('invoice_section'));
    }

    // ---------------------------------------------------------------- store(): passport number

    private function invoicePayload(int $section, array $pax): array
    {
        $vendor = Supplier::where('acc_type', 2)->where('status', 1)->orderBy('id')->firstOrFail();
        $customer = Supplier::where('acc_type', 2)->where('status', 1)->where('id', '!=', $vendor->id)->orderBy('id')->firstOrFail();
        return [
            'vendor_id' => $vendor->id, 'vendor_cost' => 100, 'invoice_beneficiaries' => $customer->id,
            'invoice_date' => date('Y-m-d'), 'invoice_travel_date' => date('Y-m-d'), 'return_date' => null,
            'invoice_airline' => 'TEST-AIRLINE', 'from_location' => 'CAI', 'to_location' => 'DXB',
            'invoice_section' => $section, 'invoice_comments' => null, 'invoice_currency' => 'EGP', 'invoice_draft' => null,
            'added_user' => $this->admin->id,
            'ticket_info' => [array_merge(['name' => 'TEST PAX', 'client_type' => '1', 'net_price' => '100', 'bought_price' => '120',
                'book_id' => 'B1', 'tikcet_id' => 'T1', 'client_phone' => null], $pax)],
        ];
    }

    private function lastInvoice(): Invoice
    {
        return Invoice::orderByDesc('id')->firstOrFail();
    }

    public function test_store_saves_the_passport_number_for_visa_invoices_only(): void
    {
        // visa invoices (section 2) come only from the Visa Invoice form (storeVisa -- VisaInvoiceTest
        // checks the passport there): the flight route refuses section 2 and saves nothing
        $before = Invoice::max('id');
        $this->post(route('site.invoices_save'), $this->invoicePayload(Visa::SECTION, ['passport_id' => 'A1234567']))->assertSessionHasErrors('msg');
        $this->assertSame($before, Invoice::max('id'));

        // flight invoice: even if a passport number were sent, nothing is saved -- as before
        $this->post(route('site.invoices_save'), $this->invoicePayload(1, ['passport_id' => 'SHOULD-NOT-SAVE']))->assertRedirect(route('site.invoices'));
        $flight = $this->lastInvoice();
        $this->assertNull(TicketUser::where('ticket_system_id', $flight->ticket_system_id)->value('client_passport_id'));
    }

    public function test_flight_invoice_rows_are_exactly_as_before(): void
    {
        // the flight form sends no passport_id: same rows as the unchanged code produced
        $this->post(route('site.invoices_save'), $this->invoicePayload(1, []))->assertRedirect(route('site.invoices'));
        $inv = $this->lastInvoice();
        $pax = TicketUser::where('ticket_system_id', $inv->ticket_system_id)->firstOrFail();
        $this->assertEquals(['TEST PAX', '1', '100', '120', 'B1', 'T1', null], [$pax->client_name, $pax->client_type, $pax->client_net_pice,
            $pax->client_bought_price, $pax->client_booking_id, $pax->client_ticket_id, $pax->client_passport_id]);
        $this->assertSame('FLY-A' . $inv->id, $inv->es_id);
        $this->assertSame('1', (string) $inv->invoice_section);
        $this->assertSame('no', $inv->invoice_ticket_file, 'no attachment -> "no", as before');

        $rows = AccountStatement::where('es_id', $inv->es_id)->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertEquals([0.0, 100.0, 1, '1'], [(float) $rows[0]->debit_balance, (float) $rows[0]->credit_balance, (int) $rows[0]->transaction_type, (string) $rows[0]->invoice_type], 'supplier credit');
        $this->assertEquals([120.0, 0.0, 1, '1'], [(float) $rows[1]->debit_balance, (float) $rows[1]->credit_balance, (int) $rows[1]->transaction_type, (string) $rows[1]->invoice_type], 'customer debit');
    }
}
