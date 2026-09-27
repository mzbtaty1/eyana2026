<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Permission foundation (App\Support\Permissions): an admin has every ability, an
        // employee the abilities listed for them (today: Permissions::EMPLOYEE_DEFAULT).
        foreach (array_keys(Permissions::ALL) as $ability) {
            Gate::define($ability, fn (User $user) => $user->hasPermission($ability));
        }

        // «سند دفع» save: the treasury (admin), or -- for any employee -- only the Counter
        // Customer refund payout of an invoice («رد مبلغ للعميل» on the invoice payment screen),
        // which BondsController::save limits to that invoice and to the amount due.
        Gate::define('bonds.save', fn (User $user) => $user->hasPermission(Permissions::FINANCE_MANAGE)
            || ((int) request()->input('invoice_id') > 0 && (string) request()->input('type_slctd') === '1'));
    }
}
