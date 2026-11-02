<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Admin: everything. Accounts: masters, billing. Supervisor (Operator): attendance and read-only views.
        // PI: only the PI portal (routes/web.php, role:pi), never these.
        Gate::define('manage-masters', fn (User $u) => $u->hasRole('admin', 'accounts'));
        Gate::define('billing', fn (User $u) => $u->hasRole('admin', 'accounts'));
        Gate::define('approve-invoice', fn (User $u) => $u->hasRole('admin', 'accounts'));
        Gate::define('unlock-attendance', fn (User $u) => $u->hasRole('admin'));
        Gate::define('import', fn (User $u) => $u->hasRole('admin'));
        Gate::define('view-bank-details', fn (User $u) => $u->hasRole('admin', 'accounts'));
        Gate::define('manage-users', fn (User $u) => $u->hasRole('admin'));
    }
}
