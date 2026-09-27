<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{AccountStatement, Invoice, Supplier, User};
use App\Services\CounterPayments;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{Gate, Hash};
use Tests\TestCase;

/**
 * Employees step A: server-side authorization. Employee management, passwords, settings and
 * the finance pages are admin only; «حسابي» only ever edits the signed-in user; the old
 * employee report is the employee's own; an employee cannot forge an invoice's employee.
 * Dev DB, rolled back (users / invoices / ledger are InnoDB).
 */
class EmployeeSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $employee;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->admin = User::where('account_type', 2)->where('status', 1)->orderBy('id')->firstOrFail();
        [$this->employee, $this->other] = User::where('account_type', 1)->where('status', 1)->orderBy('id')->limit(2)->get()->all();
    }

    private function snapshot(User $u): array
    {
        return User::whereKey($u->id)->first()->only(['name', 'email', 'password', 'status', 'account_type', 'commission', 'user_id']);
    }

    // ------------------------------------------------------------------ permission foundation

    public function test_gates_follow_the_permission_foundation(): void
    {
        foreach (array_keys(Permissions::ALL) as $ability) {
            $this->assertTrue(Gate::forUser($this->admin)->allows($ability), "admin: $ability");
            $this->assertSame(in_array($ability, Permissions::EMPLOYEE_DEFAULT, true), Gate::forUser($this->employee)->allows($ability), "employee: $ability");
        }
        foreach (Permissions::ADMIN_ONLY as $ability) {
            $this->assertFalse(Gate::forUser($this->employee)->allows($ability));
        }
        // a stored list can never grant an admin-only ability; a blocked user has none
        $e = clone $this->employee;
        $e->setRawAttributes(array_merge($e->getAttributes(), ['permissions' => json_encode([Permissions::EMPLOYEES_MANAGE, Permissions::INVOICES_VIEW])]));
        $this->assertSame([Permissions::INVOICES_VIEW], $e->permissionList());
        $blocked = clone $this->employee;
        $blocked->status = '0';
        $this->assertFalse($blocked->hasPermission(Permissions::INVOICES_VIEW));
    }

    // ------------------------------------------------------------------ employee management

    public function test_employee_cannot_access_employee_management(): void
    {
        $this->actingAs($this->employee);
        foreach ([route('site.admins'), route('site.admins_create'), route('site.admins_edit', $this->other->id), route('site.admins_edit', $this->employee->id)] as $url) {
            $this->get($url)->assertForbidden();
        }
        $before = User::count();
        $this->post(route('site.admins_save'), ['name' => 'X', 'email' => 'x-' . uniqid() . '@test.local', 'commission' => '50',
            'status' => 1, 'account_type' => 2, 'password' => 'secret'])->assertForbidden();
        $this->assertSame($before, User::count());
    }

    public function test_employee_cannot_modify_another_employee_or_their_own_type_commission_status(): void
    {
        $this->actingAs($this->employee);
        $other = $this->snapshot($this->other);
        $self = $this->snapshot($this->employee);
        foreach ([$this->other, $this->employee] as $target) {
            $this->post(route('site.admins_save_update'), ['admin_id' => $target->id, 'name' => 'HACK', 'email' => 'hack-' . uniqid() . '@test.local',
                'commission' => '99', 'status' => 1, 'account_type' => 2, 'password' => 'hacked', 'permissions' => [Permissions::EMPLOYEES_MANAGE]])->assertForbidden();
        }
        $this->assertSame($other, $this->snapshot($this->other));
        $this->assertSame($self, $this->snapshot($this->employee));
    }

    public function test_employee_cannot_delete_users(): void
    {
        $this->actingAs($this->employee);
        $this->post(route('site.admins_remove', $this->other->id))->assertForbidden();
        $this->get('/admins/' . $this->other->id . '/remove')->assertStatus(405); // no GET delete any more
        $this->assertNotNull(User::find($this->other->id));
    }

    // ------------------------------------------------------------------ «حسابي»

    public function test_my_account_only_changes_the_signed_in_users_allowed_fields(): void
    {
        $this->actingAs($this->employee);
        $other = $this->snapshot($this->other);
        $self = $this->snapshot($this->employee);
        $email = 'me-' . uniqid() . '@test.local';

        $this->post(route('site.my_account_saves'), [
            'admin_id' => $this->other->id,                   // ignored: always the signed-in user
            'name' => 'MY NEW NAME', 'email' => $email,
            'account_type' => 2, 'commission' => '99', 'status' => 0, 'permissions' => [Permissions::EMPLOYEES_MANAGE],
        ])->assertRedirect(route('site.my_account'));

        $this->assertSame($other, $this->snapshot($this->other), 'another user is never touched');
        $now = $this->snapshot($this->employee);
        $this->assertSame(['MY NEW NAME', $email], [$now['name'], $now['email']]);
        $this->assertSame([$self['account_type'], $self['commission'], $self['status'], $self['password']],
            [$now['account_type'], $now['commission'], $now['status'], $now['password']], 'type / commission / status / password unchanged');
        $this->assertFalse(Gate::forUser(User::find($this->employee->id))->allows(Permissions::EMPLOYEES_MANAGE));
    }

    public function test_employee_can_change_their_own_password_in_my_account(): void
    {
        $this->actingAs($this->employee);
        $this->post(route('site.my_account_saves'), ['name' => $this->employee->name, 'email' => $this->employee->email, 'password' => 'new-pass-123'])
            ->assertRedirect(route('site.index'));
        $this->assertTrue(Hash::check('new-pass-123', User::find($this->employee->id)->password));
        $this->assertGuest(); // signed out after a password change, as before
    }

    public function test_my_account_rejects_another_users_email(): void
    {
        $this->actingAs($this->employee);
        $this->post(route('site.my_account_saves'), ['name' => 'X', 'email' => $this->other->email])->assertSessionHasErrors('email');
        $this->assertSame($this->employee->email, User::find($this->employee->id)->email);
    }

    // ------------------------------------------------------------------ old employee report

    public function test_employee_only_ever_sees_their_own_employee_report(): void
    {
        $this->actingAs($this->employee);
        $res = $this->post(route('site.employee_log_view'), ['user_id' => $this->other->id, 'date_from' => '2026-09-01', 'date_to' => '2026-09-30']);
        $res->assertOk();
        $this->assertSame((string) $this->employee->id, (string) $res->viewData('id'));
        $this->assertSame($this->employee->id, $res->viewData('user_info')->id);

        $print = $this->get(route('site.employee_log_print', ['user_id' => $this->other->id, 'date_from' => '2026-09-01', 'date_to' => '2026-09-30']));
        $print->assertOk();
        $this->assertSame((string) $this->employee->id, (string) $print->viewData('id'));

        // without user_id an employee does not get every employee's invoices either
        $all = $this->post(route('site.employee_log_view'), ['date_from' => '2026-09-01', 'date_to' => '2026-09-30']);
        $this->assertSame((string) $this->employee->id, (string) $all->viewData('id'));
        $this->assertTrue(collect($all->viewData('invoices'))->every(fn ($i) => (int) $i->invoice_create_by === $this->employee->id));
    }

    public function test_admin_can_view_any_employees_report(): void
    {
        $this->actingAs($this->admin);
        $res = $this->post(route('site.employee_log_view'), ['user_id' => $this->other->id, 'date_from' => '2026-09-01', 'date_to' => '2026-09-30'])->assertOk();
        $this->assertSame((string) $this->other->id, (string) $res->viewData('id'));
    }

    // ------------------------------------------------------------------ admin

    public function test_admin_can_manage_employees(): void
    {
        $this->actingAs($this->admin);
        $this->get(route('site.admins'))->assertOk();
        $this->get(route('site.admins_create'))->assertOk();
        $this->get(route('site.admins_edit', $this->other->id))->assertOk();

        $email = 'new-emp-' . uniqid() . '@test.local';
        $this->post(route('site.admins_save'), ['name' => 'TEST EMPLOYEE', 'email' => $email, 'commission' => '10',
            'status' => 1, 'account_type' => 1, 'password' => 'secret-123'])->assertRedirect(route('site.admins'));
        $new = User::where('email', $email)->firstOrFail();
        $this->assertSame([1, '1', '10'], [(int) $new->account_type, (string) $new->status, (string) $new->commission]);

        // edit another user (status / type / commission) and change their password: the admin stays signed in
        $this->post(route('site.admins_save_update'), ['admin_id' => $new->id, 'name' => 'TEST EMPLOYEE 2', 'email' => $email,
            'commission' => '15', 'status' => 0, 'account_type' => 1, 'password' => 'changed-456'])->assertRedirect(route('site.admins_edit', $new->id));
        $new = $new->fresh();
        $this->assertSame(['TEST EMPLOYEE 2', '15', '0'], [$new->name, (string) $new->commission, (string) $new->status]);
        $this->assertTrue(Hash::check('changed-456', $new->password));
        $this->assertAuthenticatedAs($this->admin);

        // delete (POST) a user -- not their own account
        $this->post(route('site.admins_remove', $new->id))->assertRedirect(route('site.admins'));
        $this->assertNull(User::find($new->id));
    }

    public function test_admin_cannot_lock_themselves_out(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('site.admins_remove', $this->admin->id))->assertRedirect(route('site.admins'))->assertSessionHasErrors('user');
        $this->assertNotNull(User::find($this->admin->id));

        $this->post(route('site.admins_save_update'), ['admin_id' => $this->admin->id, 'name' => $this->admin->name, 'email' => $this->admin->email,
            'commission' => (string) $this->admin->commission, 'status' => 0, 'account_type' => 1])->assertRedirect();
        $this->assertSame([2, '1'], [(int) $this->admin->fresh()->account_type, (string) $this->admin->fresh()->status]);
    }

    public function test_admin_user_form_validation(): void
    {
        $this->actingAs($this->admin);
        $before = User::count();
        $this->post(route('site.admins_save'), ['name' => '', 'email' => $this->other->email, 'commission' => '', 'status' => 5, 'account_type' => 9, 'password' => ''])
            ->assertSessionHasErrors(['name', 'email', 'commission', 'status', 'account_type', 'password']);
        $this->assertSame($before, User::count());
    }

    // ------------------------------------------------------------------ settings / finance pages

    public function test_admin_only_pages_are_forbidden_to_employees(): void
    {
        $urls = ['/passwords', '/passwords/create', '/alerts', '/alerts/create', '/airlines', '/collectors', '/marketing-prices',
            '/accounts-statement', '/accounts-statement/transaction/all', '/storages', '/sub-storages', '/banks', '/bonds', '/bonds/add',
            '/bonds/daily-report', '/expenses', '/customers', '/api/supp_info/1', '/testpage', '/make_artisan'];
        $this->actingAs($this->employee);
        foreach ($urls as $url) {
            $this->get($url)->assertForbidden();
        }
        foreach (['/passwords/save', '/alerts/save', '/airlines/save', '/banks/save', '/storages/save', '/expenses/save', '/customers/save', '/bonds/save_update'] as $url) {
            $this->post($url, [])->assertForbidden();
        }
        foreach (['/passwords', '/alerts', '/airlines', '/banks', '/bonds', '/expenses', '/customers', '/testpage'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_system_tools_need_a_signed_in_admin(): void
    {
        foreach (['/make_artisan', '/testpage', '/test'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_bond_save_is_admin_only_except_the_counter_customer_payout(): void
    {
        $this->actingAs($this->employee);
        $bonds = \App\Models\Bond::count();
        $this->post(route('site.bonds_save'), ['type_slctd' => 2, 'amount' => 100])->assertForbidden();
        $this->post(route('site.bonds_save'), ['type_slctd' => 1, 'amount' => 100])->assertForbidden();
        // the invoice payment screen's refund payout reaches the controller (which checks the invoice and the amount)
        $res = $this->post(route('site.bonds_save'), ['type_slctd' => 1, 'invoice_id' => 999999999, 'supp_id' => 1, 'amount' => 1]);
        $this->assertNotSame(403, $res->status());
        $this->assertSame($bonds, \App\Models\Bond::count());
    }

    // ------------------------------------------------------------------ invoice ownership

    private function flightPayload(array $over = []): array
    {
        [$customer, $vendor] = Supplier::where('status', 1)->where('acc_type', '!=', 3)->whereNotIn('id', CounterPayments::counterIds())->orderBy('id')->limit(2)->get()->all();
        return array_merge([
            'invoice_date' => '2026-09-27', 'invoice_travel_date' => '2026-10-05', 'return_date' => null,
            'from_location' => 'CAI', 'to_location' => 'DXB', 'invoice_airline' => 'TEST-AIR',
            'invoice_beneficiaries' => $customer->id, 'vendor_id' => $vendor->id,
            'invoice_section' => 1, 'invoice_comments' => null, 'invoice_currency' => 'جنية مصري', 'invoice_draft' => null,
            'ticket_info' => [['name' => 'TEST PAX', 'client_type' => 3, 'net_price' => 1000, 'bought_price' => 1200,
                'book_id' => 'PNR1', 'tikcet_id' => 'TKT1', 'client_phone' => null]],
        ], $over);
    }

    private function last(): Invoice
    {
        return Invoice::orderByDesc('id')->firstOrFail();
    }

    public function test_employee_cannot_forge_invoice_ownership(): void
    {
        $this->actingAs($this->employee);
        $this->post(route('site.invoices_save'), $this->flightPayload(['added_user' => $this->other->id]))->assertRedirect(route('site.invoices'));
        $inv = $this->last();
        $this->assertSame((string) $this->employee->id, (string) $inv->invoice_create_by);
        $this->assertSame([(string) $this->employee->id], AccountStatement::where('es_id', $inv->es_id)->pluck('added_by')->map(fn ($v) => (string) $v)->unique()->values()->all());

        // edit: the forged id is ignored too (an employee's edit records the employee, as before)
        $pax = \App\Models\TicketUser::where('ticket_system_id', $inv->ticket_system_id)->firstOrFail();
        $this->post(route('site.invoices_save_update'), array_merge($this->flightPayload(['added_user' => $this->admin->id]), ['id' => $inv->id,
            'ticket_info' => [['id' => $pax->id, 'name' => 'TEST PAX', 'client_type' => 3, 'net_price' => 1000, 'bought_price' => 1200,
                'book_id' => 'PNR1', 'tikcet_id' => 'TKT1', 'client_phone' => null]]]))->assertRedirect(route('site.invoices_edit', $inv->id));
        $this->assertSame((string) $this->employee->id, (string) $inv->fresh()->invoice_create_by);
    }

    public function test_admin_can_still_assign_the_invoice_employee(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('site.invoices_save'), $this->flightPayload(['added_user' => $this->employee->id]))->assertRedirect(route('site.invoices'));
        $this->assertSame((string) $this->employee->id, (string) $this->last()->invoice_create_by);

        // an id that is no user falls back to the admin
        $this->post(route('site.invoices_save'), $this->flightPayload(['added_user' => 999999999]))->assertRedirect(route('site.invoices'));
        $this->assertSame((string) $this->admin->id, (string) $this->last()->invoice_create_by);
    }

    // ------------------------------------------------------------------ /profits and the legacy invoice management

    /** A flight invoice recorded for $owner (created by the admin, who may assign the employee). */
    private function invoiceFor(User $owner): Invoice
    {
        $this->actingAs($this->admin)->post(route('site.invoices_save'), $this->flightPayload(['added_user' => $owner->id]))->assertRedirect(route('site.invoices'));
        $inv = $this->last();
        $this->assertSame((string) $owner->id, (string) $inv->invoice_create_by);
        return $inv;
    }

    public function test_profits_page_only_shows_the_employees_own_invoices(): void
    {
        $theirs = $this->invoiceFor($this->other);
        $mine = $this->invoiceFor($this->employee);

        $res = $this->actingAs($this->employee)->get(route('site.profits'))->assertOk();
        $invoices = collect($res->viewData('invoices'));
        $this->assertTrue($invoices->contains('id', $mine->id));
        $this->assertFalse($invoices->contains('id', $theirs->id));
        $this->assertTrue($invoices->every(fn ($i) => (int) $i->invoice_create_by === $this->employee->id && (int) $i->invoice_shared === 0));
        $res->assertDontSee($theirs->es_id);
    }

    public function test_legacy_invoice_data_is_employee_scoped(): void
    {
        $theirs = $this->invoiceFor($this->other);
        $mine = $this->invoiceFor($this->employee);
        $data = fn (array $q) => $this->getJson('/invoices/data?' . http_build_query(array_merge(['draw' => 1, 'start' => 0, 'length' => 10, 'period_filter' => 'all'], $q)))->assertOk()->json();

        $esIds = fn (array $json) => collect($json['data'])->pluck('es_id')->all();

        $this->actingAs($this->employee)->get(route('invoices.management'))->assertOk();
        $all = $data([]);
        $this->assertSame(Invoice::where('invoice_create_by', $this->employee->id)->count(), $all['recordsTotal']);
        $this->assertSame($all['recordsTotal'], $all['recordsFiltered']);
        $this->assertContains($mine->es_id, $esIds($all));          // newest first: the employee's new invoice
        $this->assertNotContains($theirs->es_id, $esIds($all));
        foreach (['section_filter' => '1', 'status_filter' => '0', 'period_filter' => '1year'] as $k => $v) {
            $this->assertNotContains($theirs->es_id, $esIds($data([$k => $v])), $k);
        }
        // the live search (one call -- this legacy endpoint's search scans every invoice) cannot reach another employee's invoice
        $this->assertSame([], $esIds($data(['search' => ['value' => $theirs->es_id]])), 'another employee\'s invoice cannot be searched');

        // the admin still gets every invoice
        $this->actingAs($this->admin);
        $admin = $data([]);
        $this->assertSame(Invoice::count(), $admin['recordsTotal']);
        $this->assertContains($theirs->es_id, $esIds($admin));
        $this->assertContains($mine->es_id, $esIds($admin));
    }

    public function test_legacy_confirm_is_admin_only(): void
    {
        $inv = $this->invoiceFor($this->employee);
        $status = (int) $inv->fresh()->invoice_status;
        $this->actingAs($this->employee)->postJson(route('invoices.toggle-confirm'), ['invoice_id' => $inv->id, 'status' => 1 - $status])->assertForbidden();
        $this->assertSame($status, (int) $inv->fresh()->invoice_status);

        $this->actingAs($this->admin)->postJson(route('invoices.toggle-confirm'), ['invoice_id' => $inv->id, 'status' => 1 - $status])->assertOk();
        $this->assertSame(1 - $status, (int) $inv->fresh()->invoice_status);
    }

    public function test_legacy_delete_only_the_employees_own_invoices(): void
    {
        $theirs = $this->invoiceFor($this->other);
        $mine = $this->invoiceFor($this->employee);

        $this->actingAs($this->employee);
        foreach (['/invoices/' . $theirs->id . '/remove', '/invoices/' . $theirs->es_id . '/delete'] as $url) {
            $this->deleteJson($url)->assertForbidden();
        }
        $this->assertNotNull(Invoice::find($theirs->id));
        $this->assertTrue(AccountStatement::where('es_id', $theirs->es_id)->exists());

        // own invoice: allowed, as before
        $this->deleteJson('/invoices/' . $mine->id . '/remove')->assertOk();
        $this->assertNull(Invoice::find($mine->id));

        // the admin can delete any invoice, as before
        $this->actingAs($this->admin)->deleteJson('/invoices/' . $theirs->id . '/remove')->assertOk();
        $this->assertNull(Invoice::find($theirs->id));
    }

    // ------------------------------------------------------------------ main list: confirm / delete

    public function test_main_invoice_confirmation_is_admin_only(): void
    {
        $theirs = $this->invoiceFor($this->other);
        $mine = $this->invoiceFor($this->employee);

        $this->actingAs($this->employee);
        foreach ([$theirs, $mine] as $inv) {
            $this->get(route('site.invoices_approve', $inv->id))->assertForbidden();
            $this->assertSame(0, (int) $inv->fresh()->invoice_status);
        }

        $this->actingAs($this->admin)->get(route('site.invoices_approve', $theirs->id))->assertOk()->assertJson(['status_code' => 200]);
        $this->assertSame(1, (int) $theirs->fresh()->invoice_status);
    }

    public function test_employee_cannot_delete_another_employees_invoice(): void
    {
        $theirs = $this->invoiceFor($this->other);
        $this->actingAs($this->employee);

        $this->get(route('site.invoices_remove', $theirs->es_id))->assertForbidden();
        $this->post(route('site.invoices_remove_send', $theirs->es_id))->assertForbidden();
        $this->get('/invoices/' . $theirs->es_id . '/remove/send')->assertStatus(405); // no GET delete any more
        $this->assertNotNull(Invoice::find($theirs->id));
        $this->assertSame(2, AccountStatement::where('es_id', $theirs->es_id)->count());
    }

    public function test_employee_can_delete_their_own_invoice(): void
    {
        $mine = $this->invoiceFor($this->employee);
        $this->actingAs($this->employee);

        $this->get(route('site.invoices_remove', $mine->es_id))->assertOk();
        $this->post(route('site.invoices_remove_send', $mine->es_id))->assertRedirect(route('site.invoices'));
        $this->assertNull(Invoice::find($mine->id));
        $this->assertSame(0, AccountStatement::where('es_id', $mine->es_id)->count());
    }

    public function test_admin_can_delete_any_invoice(): void
    {
        $theirs = $this->invoiceFor($this->other);
        $this->actingAs($this->admin);

        $this->get(route('site.invoices_remove', $theirs->es_id))->assertOk();
        $this->post(route('site.invoices_remove_send', $theirs->es_id))->assertRedirect(route('site.invoices'));
        $this->assertNull(Invoice::find($theirs->id));
    }

    public function test_existing_deletion_rules_still_apply_to_the_owner(): void
    {
        $counterId = CounterPayments::counterIds()[0];

        // a paid (non-counter) invoice is never deleted
        $paid = $this->invoiceFor($this->employee);
        Invoice::whereKey($paid->id)->update(['invoice_money_pay' => 100]);
        $this->actingAs($this->employee)->post(route('site.invoices_remove_send', $paid->es_id))
            ->assertRedirect(route('site.invoices_remove', $paid->es_id))->assertSessionHasErrors('msg');
        $this->assertNotNull(Invoice::find($paid->id));

        // a Counter Customer invoice whose paid amount has no matching receipts: refused, nothing changed
        $this->actingAs($this->admin)->post(route('site.invoices_save'), $this->flightPayload(['added_user' => $this->employee->id, 'invoice_beneficiaries' => $counterId]));
        $counter = $this->last();
        $this->assertTrue(\App\Services\CounterInvoiceDeletion::applies($counter));
        Invoice::whereKey($counter->id)->update(['invoice_money_pay' => 50]);
        $this->actingAs($this->employee)->post(route('site.invoices_remove_send', $counter->es_id))
            ->assertRedirect(route('site.invoices_remove', $counter->es_id))->assertSessionHasErrors('msg');
        $this->assertNotNull(Invoice::find($counter->id));
        $this->assertSame(2, AccountStatement::where('es_id', $counter->es_id)->count());

        // the same Counter Customer invoice without payments: deleted through the counter rules
        Invoice::whereKey($counter->id)->update(['invoice_money_pay' => 0]);
        $this->post(route('site.invoices_remove_send', $counter->es_id))->assertRedirect(route('site.invoices'));
        $this->assertNull(Invoice::find($counter->id));

        // and another employee still cannot reach these rules at all
        $other = $this->invoiceFor($this->other);
        Invoice::whereKey($other->id)->update(['invoice_beneficiaries' => $counterId]);
        $this->actingAs($this->employee)->post(route('site.invoices_remove_send', $other->es_id))->assertForbidden();
        $this->assertNotNull(Invoice::find($other->id));
    }

    public function test_dead_legacy_endpoints_are_admin_only(): void
    {
        $this->actingAs($this->employee);
        $this->get('/get-invoices-management')->assertForbidden();
        $this->get('/invoices/search')->assertForbidden();
    }

    public function test_employee_everyday_pages_still_work(): void
    {
        $this->actingAs($this->employee);
        foreach (['/', '/invoices', '/invoices/flight', '/invoices/visa', '/invoices/create', '/invoices/create/visa', '/invoices/create/counter',
                  '/invoices/employee-log', '/invoices/full-report', '/my-account', '/shared-invoices/create'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->getJson(route('site.invoices_list_data', ['draw' => 1, 'length' => 5]))->assertOk();
    }
}
