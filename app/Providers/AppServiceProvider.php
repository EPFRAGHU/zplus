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
        Gate::define('reset-month', fn (User $u) => $u->hasRole('admin'));
        // Only the Super Admin (the owner) may delete a project, and only an empty one (no attendance, bills or staff). Owner, 17 Nov 2026.
        Gate::define('delete-project', fn (User $u) => $u->isSuperAdmin());
        // Super Admin deletes of unused records and of bills (except paid); the buttons show only with "Show delete options" on.
        Gate::define('delete-records', fn (User $u) => $u->isSuperAdmin());
        Gate::define('show-delete', fn (User $u) => $u->isSuperAdmin() && (bool) session('delete_mode'));   // delete a project's month: bill + attendance
        // Developer only (owner, 19 Nov 2026): Data Upload, GeM import, Users, Activity log. Super Admins approve and delete.
        Gate::define('import', fn (User $u) => $u->isDeveloper());   // Data Upload (each agency's own data)
        Gate::define('gem-import', fn (User $u) => $u->isDeveloper() && $u->worksForBothAgencies() && \App\Support\Agency\AgencyContext::id() === null);   // GeM workbook covers both agencies
        Gate::define('add-agency', fn (User $u) => $u->hasRole('admin', 'accounts') && $u->worksForBothAgencies() && \App\Support\Agency\AgencyContext::id() === null);
        Gate::define('view-bank-details', fn (User $u) => $u->hasRole('admin', 'accounts'));
        Gate::define('manage-users', fn (User $u) => $u->isDeveloper());
        Gate::define('merge-clients', fn (User $u) => $u->isDeveloper());   // one-off data clean-up (owner, 21 Nov 2026)
        Gate::define('view-activity', fn (User $u) => $u->isDeveloper());
    }
}
