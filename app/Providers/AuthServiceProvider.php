<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Models\MaintenanceRequest as MR;
use App\Policies\MaintenanceRequestPolicy;
use App\Models\MaintenanceRequestType;
use App\Policies\MaintenanceRequestTypePolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
        MR::class => MaintenanceRequestPolicy::class,
        MaintenanceRequestType::class => MaintenanceRequestTypePolicy::class,
        \App\Models\Asset::class => \App\Policies\AssetPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // manage users — admin only (was: admin + supervisor + every worker role,
        // which let any IT worker open /admin/users and read the full staff list)
        Gate::define('manage-users', function (User $user): bool {
            return $user->isAdmin();
        });

        // repair dashboard
        Gate::define('view-repair-dashboard', function (User $user): bool {
            return $user->role === User::ROLE_ADMIN || $user->isSupervisor() || $user->isWorker();
        });

        // my jobs
        Gate::define('view-my-jobs', function (User $user): bool {
            return $user->role === User::ROLE_ADMIN || $user->isSupervisor() || $user->isWorker();
        });

        // tech only
        Gate::define('tech-only', function (User $user): bool {
            return $user->role === User::ROLE_ADMIN || $user->isSupervisor() || $user->isWorker();
        });

        // maintenance type manage
        Gate::define('maintenance-type-manage', function (User $user): bool {
            return $user->isAdmin() || $user->isSupervisor() || $user->isTechnician();
        });
    }
}
