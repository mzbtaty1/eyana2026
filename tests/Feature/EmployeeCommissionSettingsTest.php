<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{CommissionTierTable, User};
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{DB, Hash};
use Tests\TestCase;

/**
 * Employees step C: each employee's commission settings -- a fixed % (users.commission) or
 * an assigned ACTIVE tier table -- set by the admin only. No commission calculation here.
 * Dev DB, rolled back (users and the tier tables are InnoDB).
 */
class EmployeeCommissionSettingsTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->admin = User::where('account_type', 2)->where('status', 1)->orderBy('id')->firstOrFail();
        $this->employee = User::where('account_type', 1)->where('status', 1)->orderBy('id')->firstOrFail();
    }

    private function table(int $status = 1): CommissionTierTable
    {
        $table = CommissionTierTable::create(['name' => 'TEST TIERS ' . uniqid(), 'status' => $status]);
        $table->tiers()->createMany([
            ['from_amount' => 1, 'to_amount' => 10000, 'rate' => 10, 'sort_order' => 1],
            ['from_amount' => 10001, 'to_amount' => null, 'rate' => 20, 'sort_order' => 2],
        ]);
        return $table;
    }

    /** A throwaway employee (rolled back) with the given columns. */
    private function worker(array $attrs = []): User
    {
        return User::create(array_merge(['name' => 'TEST WORKER ' . uniqid(), 'email' => 'worker-' . uniqid() . '@test.local',
            'password' => Hash::make('secret-123'), 'user_id' => 'FX_TEST', 'status' => '1', 'account_type' => 1, 'commission' => '10'], $attrs))->fresh();
    }

    private function createPayload(array $over = []): array
    {
        return array_merge(['name' => 'TEST COMMISSION ' . uniqid(), 'email' => 'commission-' . uniqid() . '@test.local', 'status' => 1,
            'account_type' => 1, 'password' => 'secret-123', 'commission_method' => 'fixed', 'commission' => '10'], $over);
    }

    private function updatePayload(User $u, array $over = []): array
    {
        return array_merge(['admin_id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'status' => 1, 'account_type' => 1], $over);
    }

    private function commissionOf(User $u): array
    {
        $u = User::whereKey($u->id)->first();
        return [$u->commission_method, $u->commission_tier_table_id, (string) $u->commission];
    }

    /** Admin create must fail on $field and save nothing. */
    private function assertCreateRejected(array $over, string $field): void
    {
        $before = User::count();
        $this->actingAs($this->admin)->from(route('site.admins_create'))
            ->post(route('site.admins_save'), $this->createPayload($over))
            ->assertRedirect(route('site.admins_create'))->assertSessionHasErrors($field);
        $this->assertSame($before, User::count(), 'no user created');
    }

    /** Admin edit must fail on $field and leave the commission settings as they were. */
    private function assertUpdateRejected(User $u, array $over, string $field): void
    {
        $before = $this->commissionOf($u);
        $this->actingAs($this->admin)->from(route('site.admins_edit', $u->id))
            ->post(route('site.admins_save_update'), $this->updatePayload($u, $over))
            ->assertRedirect(route('site.admins_edit', $u->id))->assertSessionHasErrors($field);
        $this->assertSame($before, $this->commissionOf($u));
    }

    // ------------------------------------------------------------------ existing data

    public function test_existing_users_default_to_fixed_with_no_tier_table(): void
    {
        // a row written without the new columns (as every pre-step-C user was) gets the defaults
        $id = DB::table('users')->insertGetId(['name' => 'TEST LEGACY', 'email' => 'legacy-' . uniqid() . '@test.local',
            'password' => 'x', 'user_id' => 'FX_TEST', 'status' => '1', 'account_type' => 1, 'commission' => '10%']);
        $row = DB::table('users')->find($id);
        $this->assertSame(['fixed', null, '10%'], [$row->commission_method, $row->commission_tier_table_id, $row->commission]);
        $this->assertFalse(User::find($id)->usesTieredCommission());
    }

    public function test_existing_commission_values_are_kept_as_stored(): void
    {
        // "10%" is read as 10 and shown as 10% (not 10%%); saving it unchanged keeps the stored text
        $legacy = $this->worker(['commission' => '10%']);
        $this->actingAs($this->admin);
        $this->get(route('site.admins'))->assertOk()->assertDontSee('10%%');
        $this->get(route('site.admins_edit', $legacy->id))->assertOk()->assertSee('name="commission" inputmode="decimal" class="form-control" style="text-align:right;" value="10"', false);

        foreach (['10', '10%', '10.0'] as $same) {
            $this->post(route('site.admins_save_update'), $this->updatePayload($legacy, ['commission_method' => 'fixed', 'commission' => $same]))->assertSessionHasNoErrors();
            $this->assertSame(['fixed', null, '10%'], $this->commissionOf($legacy), "unchanged ($same)");
        }
        // an older client that posts no commission_method keeps the method
        $this->post(route('site.admins_save_update'), $this->updatePayload($legacy, ['commission' => '10%']))->assertSessionHasNoErrors();
        $this->assertSame(['fixed', null, '10%'], $this->commissionOf($legacy));
        // a real change is stored as the plain number
        $this->post(route('site.admins_save_update'), $this->updatePayload($legacy, ['commission_method' => 'fixed', 'commission' => '12.5%']))->assertSessionHasNoErrors();
        $this->assertSame(['fixed', null, '12.5'], $this->commissionOf($legacy));
    }

    // ------------------------------------------------------------------ admin create

    public function test_admin_can_create_a_fixed_employee_without_a_tier_table(): void
    {
        $inactive = $this->table(0);
        $p = $this->createPayload(['commission' => '15', 'commission_tier_table_id' => $inactive->id]); // ignored when fixed
        $this->actingAs($this->admin)->post(route('site.admins_save'), $p)->assertSessionHasNoErrors()->assertRedirect(route('site.admins'));
        $this->assertSame(['fixed', null, '15'], $this->commissionOf(User::where('email', $p['email'])->firstOrFail()));

        $p = $this->createPayload(['commission' => '0']);
        unset($p['commission_method']); // an older client: fixed by default
        $this->post(route('site.admins_save'), $p)->assertSessionHasNoErrors();
        $this->assertSame(['fixed', null, '0'], $this->commissionOf(User::where('email', $p['email'])->firstOrFail()));
    }

    public function test_admin_can_create_a_tiered_employee(): void
    {
        $table = $this->table();
        $p = $this->createPayload(['commission_method' => 'tiered', 'commission' => 'not used', 'commission_tier_table_id' => $table->id]);
        $this->actingAs($this->admin)->post(route('site.admins_save'), $p)->assertSessionHasNoErrors()->assertRedirect(route('site.admins'));

        $user = User::where('email', $p['email'])->firstOrFail();
        $this->assertSame(['tiered', $table->id, '0'], $this->commissionOf($user));
        $this->assertTrue($user->usesTieredCommission());
        $this->assertSame($table->id, $user->commissionTierTable->id);
        $this->assertSame(1, $table->usageCount());
    }

    public function test_create_form_offers_both_methods_and_only_active_tables(): void
    {
        $active = $this->table();
        $inactive = $this->table(0);
        $this->actingAs($this->admin)->get(route('site.admins_create'))->assertOk()
            ->assertSee('اعدادات العمولة')->assertSee('value="fixed"', false)->assertSee('value="tiered"', false)
            ->assertSee('<option value="' . $active->id . '"', false)->assertSee($active->name)
            ->assertDontSee($inactive->name);
    }

    // ------------------------------------------------------------------ validation

    public function test_tiered_employee_requires_a_tier_table(): void
    {
        $this->assertCreateRejected(['commission_method' => 'tiered'], 'commission_tier_table_id');
        $this->assertCreateRejected(['commission_method' => 'tiered', 'commission_tier_table_id' => ''], 'commission_tier_table_id');
        $this->assertUpdateRejected($this->worker(), ['commission_method' => 'tiered', 'commission' => '10'], 'commission_tier_table_id');
    }

    public function test_inactive_tier_table_cannot_be_newly_assigned(): void
    {
        $inactive = $this->table(0);
        $this->assertCreateRejected(['commission_method' => 'tiered', 'commission_tier_table_id' => $inactive->id], 'commission_tier_table_id');
        $this->assertUpdateRejected($this->worker(), ['commission_method' => 'tiered', 'commission_tier_table_id' => $inactive->id], 'commission_tier_table_id');
    }

    public function test_non_existent_or_forged_tier_table_is_rejected(): void
    {
        $missing = (int) CommissionTierTable::max('id') + 1000;
        foreach ([$missing, 'abc', '1 OR 1=1', -1] as $forged) {
            $this->assertCreateRejected(['commission_method' => 'tiered', 'commission_tier_table_id' => $forged], 'commission_tier_table_id');
        }
        $this->assertUpdateRejected($this->worker(), ['commission_method' => 'tiered', 'commission_tier_table_id' => $missing], 'commission_tier_table_id');
        $this->assertCreateRejected(['commission_method' => 'percent'], 'commission_method'); // unknown method
    }

    public function test_invalid_commission_percentage_is_rejected(): void
    {
        foreach (['', 'abc', '10abc', '1e1', '0x1A', '10.123'] as $bad) {
            $this->assertCreateRejected(['commission' => $bad], 'commission');
        }
        $this->assertUpdateRejected($this->worker(), ['commission_method' => 'fixed', 'commission' => 'abc'], 'commission');
    }

    public function test_commission_above_100_is_rejected(): void
    {
        foreach (['101', '100.01', '150%', '1000'] as $bad) {
            $this->assertCreateRejected(['commission' => $bad], 'commission');
        }
        $this->assertUpdateRejected($this->worker(), ['commission_method' => 'fixed', 'commission' => '101'], 'commission');

        $p = $this->createPayload(['commission' => '100']); // the boundary is allowed
        $this->actingAs($this->admin)->post(route('site.admins_save'), $p)->assertSessionHasNoErrors();
        $this->assertSame('100', (string) User::where('email', $p['email'])->value('commission'));
    }

    public function test_negative_commission_is_rejected(): void
    {
        foreach (['-1', '-0.5', '-10%'] as $bad) {
            $this->assertCreateRejected(['commission' => $bad], 'commission');
        }
        $this->assertUpdateRejected($this->worker(), ['commission_method' => 'fixed', 'commission' => '-5'], 'commission');
    }

    // ------------------------------------------------------------------ employee self-service

    public function test_employee_cannot_change_their_own_commission_settings(): void
    {
        $table = $this->table();
        $before = $this->commissionOf($this->employee);
        $this->actingAs($this->employee);

        // «حسابي»: the form has no commission fields, and posted ones are ignored
        $this->get(route('site.my_account'))->assertOk()->assertDontSee('name="commission_method"', false)
            ->assertDontSee('name="commission"', false)->assertDontSee('name="commission_tier_table_id"', false);

        foreach ([['commission_method' => 'tiered', 'commission_tier_table_id' => $table->id],   // method + table
                  ['commission_method' => 'fixed', 'commission' => '99'],                        // percentage
                  ['commission_tier_table_id' => $table->id],                                     // table only
                  ['commission' => '99%', 'admin_id' => $this->admin->id]] as $forged) {         // forged target
            $this->post(route('site.my_account_saves'), ['name' => $this->employee->name, 'email' => $this->employee->email] + $forged)
                ->assertRedirect(route('site.my_account'));
            $this->assertSame($before, $this->commissionOf($this->employee));
        }
        $this->assertSame(0, $table->usageCount());
    }

    public function test_employee_cannot_forge_commission_fields_through_the_admin_endpoints(): void
    {
        $table = $this->table();
        $other = $this->worker();
        $self = $this->commissionOf($this->employee);
        $theirs = $this->commissionOf($other);
        $users = User::count();
        $this->actingAs($this->employee);

        foreach ([$this->employee, $other] as $target) {
            $this->get(route('site.admins_edit', $target->id))->assertForbidden();
            $this->post(route('site.admins_save_update'), $this->updatePayload($target, ['commission_method' => 'tiered',
                'commission_tier_table_id' => $table->id, 'commission' => '99']))->assertForbidden();
        }
        $this->post(route('site.admins_save'), $this->createPayload(['commission_method' => 'tiered', 'commission_tier_table_id' => $table->id]))->assertForbidden();

        $this->assertSame($self, $this->commissionOf($this->employee));
        $this->assertSame($theirs, $this->commissionOf($other));
        $this->assertSame($users, User::count());
    }

    // ------------------------------------------------------------------ admin edit

    public function test_admin_can_change_fixed_to_tiered_keeping_the_old_percentage(): void
    {
        $table = $this->table();
        $u = $this->worker(['commission' => '10%']);
        $this->actingAs($this->admin)->post(route('site.admins_save_update'), $this->updatePayload($u, ['commission_method' => 'tiered',
            'commission' => '55', 'commission_tier_table_id' => $table->id]))->assertSessionHasNoErrors()->assertRedirect(route('site.admins_edit', $u->id));

        $this->assertSame(['tiered', $table->id, '10%'], $this->commissionOf($u), 'the old % is kept, not overwritten');
        $this->get(route('site.admins_edit', $u->id))->assertOk()->assertSee('value="tiered" checked', false)
            ->assertSee('<option value="' . $table->id . '" selected', false);
        $this->get(route('site.admins'))->assertOk()->assertSee('شرائح')->assertSee($table->name);
    }

    public function test_admin_can_change_tiered_to_fixed(): void
    {
        $table = $this->table();
        $u = $this->worker(['commission' => '10', 'commission_method' => 'tiered', 'commission_tier_table_id' => $table->id]);
        $this->actingAs($this->admin);
        // the kept % is offered back when switching
        $this->get(route('site.admins_edit', $u->id))->assertSee('style="text-align:right;" value="10"', false);

        $this->post(route('site.admins_save_update'), $this->updatePayload($u, ['commission_method' => 'fixed', 'commission' => '15',
            'commission_tier_table_id' => $table->id]))->assertSessionHasNoErrors();
        $this->assertSame(['fixed', null, '15'], $this->commissionOf($u));
        $this->assertSame(0, $table->usageCount());
        $this->get(route('site.admins'))->assertSee('15%');
    }

    public function test_admin_can_change_the_assigned_tier_table(): void
    {
        [$a, $b] = [$this->table(), $this->table()];
        $u = $this->worker(['commission_method' => 'tiered', 'commission_tier_table_id' => $a->id]);
        $this->actingAs($this->admin)->post(route('site.admins_save_update'), $this->updatePayload($u, ['commission_method' => 'tiered',
            'commission_tier_table_id' => $b->id]))->assertSessionHasNoErrors();
        $this->assertSame(['tiered', $b->id, '10'], $this->commissionOf($u));
        $this->assertSame([0, 1], [$a->usageCount(), $b->usageCount()]);
    }

    public function test_deactivated_assigned_table_is_kept_but_must_be_replaced_on_save(): void
    {
        [$table, $active] = [$this->table(), $this->table()];
        $u = $this->worker(['commission_method' => 'tiered', 'commission_tier_table_id' => $table->id]);
        $this->actingAs($this->admin)->post(route('site.commission_tiers_status', $table->id))->assertSessionHasNoErrors();
        $this->assertFalse($table->fresh()->isActive());

        // no automatic change: the employee stays tiered on the (now inactive) table, shown as such
        $this->assertSame(['tiered', $table->id, '10'], $this->commissionOf($u));
        $this->get(route('site.admins'))->assertSee($table->name)->assertSee('معطل');
        $this->get(route('site.admins_edit', $u->id))->assertOk()->assertSee($table->name . ' (معطل)')
            ->assertSee('اختر جدولا فعالا قبل الحفظ')->assertDontSee('<option value="' . $table->id . '"', false);

        // saving again with the inactive table is refused; an active one is accepted
        $this->assertUpdateRejected($u, ['commission_method' => 'tiered', 'commission_tier_table_id' => $table->id], 'commission_tier_table_id');
        $this->actingAs($this->admin)->post(route('site.admins_save_update'), $this->updatePayload($u, ['commission_method' => 'tiered',
            'commission_tier_table_id' => $active->id]))->assertSessionHasNoErrors();
        $this->assertSame(['tiered', $active->id, '10'], $this->commissionOf($u));
    }

    // ------------------------------------------------------------------ tier table deletion (step B)

    public function test_assigned_tier_table_cannot_be_deleted(): void
    {
        $table = $this->table();
        $this->worker(['commission_method' => 'tiered', 'commission_tier_table_id' => $table->id]);
        $this->assertSame(1, $table->usageCount());

        $this->actingAs($this->admin)->post(route('site.commission_tiers_delete', $table->id))->assertSessionHasErrors('table');
        $this->assertNotNull(CommissionTierTable::find($table->id));
        $this->assertSame(2, $table->tiers()->count());

        // the foreign key refuses it as well
        $this->expectException(QueryException::class);
        DB::table('commission_tier_tables')->where('id', $table->id)->delete();
    }

    public function test_unused_tier_table_can_still_be_deleted(): void
    {
        $table = $this->table();
        $u = $this->worker(['commission_method' => 'tiered', 'commission_tier_table_id' => $table->id]);
        // once no employee uses it (switched back to fixed) it can be deleted again
        $this->actingAs($this->admin)->post(route('site.admins_save_update'), $this->updatePayload($u, ['commission_method' => 'fixed', 'commission' => '10']));
        $this->post(route('site.commission_tiers_delete', $table->id))->assertSessionHasNoErrors()->assertRedirect(route('site.commission_tiers'));
        $this->assertNull(CommissionTierTable::find($table->id));
    }
}
