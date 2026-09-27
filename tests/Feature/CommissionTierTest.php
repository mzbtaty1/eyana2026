<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Requests\CommissionTierTableRequest;
use App\Models\{CommissionTier, CommissionTierTable, User};
use App\Support\Permissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Employees step B: commission tier tables («شرائح العمولات») -- admin-only configuration
 * and tier validation (one continuous ordered range). No commission calculation here.
 * Dev DB; the new tables are InnoDB, so every test record is rolled back.
 */
class CommissionTierTest extends TestCase
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

    private function standard(): array
    {
        return [
            ['from' => '1', 'to' => '10000', 'rate' => '10'],
            ['from' => '10001', 'to' => '50000', 'rate' => '20'],
            ['from' => '50001', 'to' => '100000', 'rate' => '25'],
        ];
    }

    private function payload(array $over = []): array
    {
        return array_merge(['name' => 'TEST TIERS ' . uniqid(), 'status' => '1', 'tiers' => $this->standard()], $over);
    }

    private function create(array $over = []): CommissionTierTable
    {
        $p = $this->payload($over);
        $this->actingAs($this->admin)->post(route('site.commission_tiers_store'), $p)->assertSessionHasNoErrors();
        return CommissionTierTable::where('name', $p['name'])->firstOrFail();
    }

    /** Store must refuse $tiers with an error on 'tiers' (or $field) and save nothing. */
    private function assertRejected(array $tiers, string $field = 'tiers'): void
    {
        $tables = CommissionTierTable::count();
        $rows = CommissionTier::count();
        $this->actingAs($this->admin)->from(route('site.commission_tiers_create'))
            ->post(route('site.commission_tiers_store'), $this->payload(['tiers' => $tiers]))
            ->assertRedirect(route('site.commission_tiers_create'))
            ->assertSessionHasErrors($field);
        $this->assertSame([$tables, $rows], [CommissionTierTable::count(), CommissionTier::count()], 'nothing saved');
    }

    // ------------------------------------------------------------------ access

    public function test_admin_can_view_the_commission_tier_settings(): void
    {
        $table = $this->create();
        $this->actingAs($this->admin);
        $this->get(route('site.commission_tiers'))->assertOk()->assertSee('جداول شرائح العمولات')->assertSee($table->name);
        $this->get(route('site.commission_tiers_create'))->assertOk()->assertSee('tiers[0][from]', false);
        $this->get(route('site.commission_tiers_show', $table->id))->assertOk()->assertSee('10,000')->assertSee('25%');
        $this->get(route('site.commission_tiers_edit', $table->id))->assertOk()->assertSee('value="50001"', false);
        $this->get(route('site.index'))->assertSee(route('site.commission_tiers'), false); // settings menu entry
        $this->assertTrue(Gate::forUser($this->admin)->allows(Permissions::COMMISSION_SETTINGS));
    }

    public function test_employee_cannot_access_or_change_commission_tiers(): void
    {
        $table = $this->create();
        $before = [$table->fresh()->toArray(), $table->tiers()->get()->toArray(), CommissionTierTable::count()];
        $this->assertFalse(Gate::forUser($this->employee)->allows(Permissions::COMMISSION_SETTINGS));

        $this->actingAs($this->employee);
        foreach ([route('site.commission_tiers'), route('site.commission_tiers_create'), route('site.commission_tiers_show', $table->id),
                  route('site.commission_tiers_edit', $table->id)] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post(route('site.commission_tiers_store'), $this->payload())->assertForbidden();
        $this->post(route('site.commission_tiers_update', $table->id), $this->payload(['name' => 'HACKED']))->assertForbidden();
        $this->post(route('site.commission_tiers_status', $table->id))->assertForbidden();
        $this->post(route('site.commission_tiers_delete', $table->id))->assertForbidden();
        $this->get(route('site.index'))->assertDontSee(route('site.commission_tiers'), false);

        $this->assertEquals($before, [$table->fresh()->toArray(), $table->tiers()->get()->toArray(), CommissionTierTable::count()]);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('site.commission_tiers'))->assertRedirect(route('login'));
        $this->post(route('site.commission_tiers_store'), $this->payload())->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------ create / edit / status / delete

    public function test_admin_can_create_a_tier_table_with_multiple_tiers_and_exact_boundaries(): void
    {
        $table = $this->create(['name' => 'TEST Standard Employee Commission ' . uniqid()]);
        $this->assertSame(1, $table->status);
        $this->assertSame([
            ['1.00', '10000.00', '10.00', 1],
            ['10001.00', '50000.00', '20.00', 2],
            ['50001.00', '100000.00', '25.00', 3],
        ], $table->tiers->map(fn ($t) => [$t->from_amount, $t->to_amount, $t->rate, $t->sort_order])->all());
    }

    public function test_open_ended_last_tier_decimals_and_unordered_input_are_stored_in_order(): void
    {
        $table = $this->create(['tiers' => [
            ['from' => '50000.01', 'to' => '', 'rate' => '25.5'],        // no upper limit (last)
            ['from' => '0', 'to' => '10000', 'rate' => '10'],
            ['from' => '10000.01', 'to' => '50000', 'rate' => '12.75'],
        ]]);
        $this->assertSame([
            ['0.00', '10000.00', '10.00', 1],
            ['10000.01', '50000.00', '12.75', 2],
            ['50000.01', null, '25.50', 3],
        ], $table->tiers->map(fn ($t) => [$t->from_amount, $t->to_amount, $t->rate, $t->sort_order])->all());
    }

    public function test_admin_can_edit_a_tier_table(): void
    {
        $table = $this->create();
        $this->post(route('site.commission_tiers_update', $table->id), [
            'name' => $table->name . ' EDITED', 'status' => '1',
            'tiers' => [
                ['from' => '1', 'to' => '20000', 'rate' => '12'],
                ['from' => '20001', 'to' => '', 'rate' => '18'],
            ],
        ])->assertRedirect(route('site.commission_tiers_show', $table->id))->assertSessionHasNoErrors();

        $table = $table->fresh('tiers');
        $this->assertSame($table->name, CommissionTierTable::find($table->id)->name);
        $this->assertStringEndsWith(' EDITED', $table->name);
        $this->assertSame([['1.00', '20000.00', '12.00'], ['20001.00', null, '18.00']],
            $table->tiers->map(fn ($t) => [$t->from_amount, $t->to_amount, $t->rate])->all());
        $this->assertSame(2, CommissionTier::where('table_id', $table->id)->count(), 'old tiers replaced');
    }

    public function test_admin_can_deactivate_and_reactivate_and_inactive_tables_stay(): void
    {
        $table = $this->create();
        $this->post(route('site.commission_tiers_status', $table->id))->assertRedirect(route('site.commission_tiers'));
        $this->assertSame(0, $table->fresh()->status);
        $this->assertSame(3, $table->tiers()->count(), 'an inactive table keeps its tiers');
        $this->get(route('site.commission_tiers'))->assertOk()->assertSee($table->name)->assertSee('معطل');

        $this->post(route('site.commission_tiers_status', $table->id));
        $this->assertSame(1, $table->fresh()->status);

        // also through the edit form
        $this->post(route('site.commission_tiers_update', $table->id), $this->payload(['name' => $table->name, 'status' => '0']));
        $this->assertSame(0, $table->fresh()->status);
        $this->assertNotNull(CommissionTierTable::find($table->id));
    }

    public function test_an_unused_table_can_be_deleted_with_its_tiers(): void
    {
        $table = $this->create();
        $this->assertSame(0, $table->usageCount());
        $this->post(route('site.commission_tiers_delete', $table->id))->assertRedirect(route('site.commission_tiers'));
        $this->assertNull(CommissionTierTable::find($table->id));
        $this->assertSame(0, CommissionTier::where('table_id', $table->id)->count());
    }

    public function test_duplicate_table_names_are_rejected(): void
    {
        $table = $this->create();
        $this->post(route('site.commission_tiers_store'), $this->payload(['name' => $table->name]))->assertSessionHasErrors('name');
        $other = $this->create();
        $this->post(route('site.commission_tiers_update', $other->id), $this->payload(['name' => $table->name]))->assertSessionHasErrors('name');
        // keeping its own name is fine
        $this->post(route('site.commission_tiers_update', $table->id), $this->payload(['name' => $table->name]))->assertSessionHasNoErrors();
    }

    // ------------------------------------------------------------------ tier validation

    public function test_overlapping_and_duplicate_ranges_are_rejected(): void
    {
        $this->assertRejected([['from' => '1', 'to' => '10000', 'rate' => '10'], ['from' => '5000', 'to' => '20000', 'rate' => '20']]);
        $this->assertRejected([['from' => '1', 'to' => '10000', 'rate' => '10'], ['from' => '10000', 'to' => '20000', 'rate' => '20']]); // shares 10,000
        $this->assertRejected([['from' => '1', 'to' => '10000', 'rate' => '10'], ['from' => '1', 'to' => '10000', 'rate' => '20']]);    // duplicate
    }

    public function test_gaps_are_rejected(): void
    {
        $this->assertRejected([['from' => '1', 'to' => '10000', 'rate' => '10'], ['from' => '20000', 'to' => '50000', 'rate' => '20']]);
        $this->assertRejected([['from' => '1', 'to' => '10000', 'rate' => '10'], ['from' => '10001.01', 'to' => '50000', 'rate' => '20']]);
    }

    public function test_negative_and_non_numeric_amounts_are_rejected(): void
    {
        $this->assertRejected([['from' => '-1', 'to' => '10000', 'rate' => '10']], 'tiers.0.from');
        $this->assertRejected([['from' => '1', 'to' => '-5', 'rate' => '10']], 'tiers.0.to');
        $this->assertRejected([['from' => 'abc', 'to' => '10000', 'rate' => '10']], 'tiers.0.from');
        $this->assertRejected([['from' => '1', 'to' => '10000.123', 'rate' => '10']], 'tiers.0.to');   // max 2 decimals
    }

    public function test_percentages_outside_0_to_100_are_rejected(): void
    {
        $this->assertRejected([['from' => '1', 'to' => '10000', 'rate' => '100.01']], 'tiers.0.rate');
        $this->assertRejected([['from' => '1', 'to' => '10000', 'rate' => '-1']], 'tiers.0.rate');
        $this->assertRejected([['from' => '1', 'to' => '10000', 'rate' => 'ten']], 'tiers.0.rate');
        $this->assertRejected([['from' => '1', 'to' => '10000', 'rate' => '']], 'tiers.0.rate');
        // the limits themselves are valid
        $this->create(['tiers' => [['from' => '1', 'to' => '10000', 'rate' => '0'], ['from' => '10001', 'to' => '', 'rate' => '100']]]);
    }

    public function test_lower_limit_above_upper_limit_is_rejected(): void
    {
        $this->assertRejected([['from' => '10000', 'to' => '1', 'rate' => '10']]);
    }

    public function test_more_than_one_open_ended_tier_or_a_non_final_open_tier_is_rejected(): void
    {
        $this->assertRejected([['from' => '1', 'to' => '', 'rate' => '10'], ['from' => '10001', 'to' => '', 'rate' => '20']]);
        $this->assertRejected([['from' => '1', 'to' => '', 'rate' => '10'], ['from' => '10001', 'to' => '50000', 'rate' => '20']]);
    }

    public function test_a_table_needs_at_least_one_tier(): void
    {
        $this->assertRejected([]);
    }

    public function test_structure_rules_directly(): void
    {
        $this->assertSame([], CommissionTierTableRequest::structureErrors($this->standard()));
        $this->assertSame([], CommissionTierTableRequest::structureErrors([
            ['from' => '1', 'to' => '10000', 'rate' => '10'], ['from' => '10000.01', 'to' => '', 'rate' => '20']]));
        $this->assertNotEmpty(CommissionTierTableRequest::structureErrors([
            ['from' => '1', 'to' => '10000', 'rate' => '10'], ['from' => '10001.01', 'to' => '', 'rate' => '20']]));
    }

    public function test_invalid_edits_leave_the_table_unchanged(): void
    {
        $table = $this->create();
        $before = [$table->fresh()->toArray(), $table->tiers()->get()->toArray()];
        $this->from(route('site.commission_tiers_edit', $table->id))
            ->post(route('site.commission_tiers_update', $table->id), $this->payload(['name' => $table->name, 'tiers' => [
                ['from' => '1', 'to' => '10000', 'rate' => '10'], ['from' => '5000', 'to' => '', 'rate' => '20']]]))
            ->assertRedirect(route('site.commission_tiers_edit', $table->id))->assertSessionHasErrors('tiers');
        $this->assertEquals($before, [$table->fresh()->toArray(), $table->tiers()->get()->toArray()]);
    }
}
