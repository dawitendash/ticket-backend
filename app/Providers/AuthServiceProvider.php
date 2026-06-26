<?php

namespace App\Providers;

use App\Models\AttendanceLog;
use App\Models\Concert;
use App\Models\PaymentAccount;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserInformation;
use App\Policies\AttendanceLogPolicy;
use App\Policies\ConcertPolicy;
use App\Policies\DashboardPolicy;
use App\Policies\PaymentAccountPolicy;
use App\Policies\TicketPolicy;
use App\Policies\TicketTypePolicy;
use App\Policies\UserDevicePolicy;
use App\Policies\UserInformationPolicy;
use App\Policies\UserPolicy;
use App\Services\DashboardService;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
      protected $policies = [
        Concert::class => ConcertPolicy::class,
        TicketType::class => TicketTypePolicy::class,
        Ticket::class => TicketPolicy::class,
        AttendanceLog::class => AttendanceLogPolicy::class,
        User::class => UserPolicy::class,
        UserDevice::class => UserDevicePolicy::class,
        PaymentAccount::class => PaymentAccountPolicy::class,
        UserInformation::class => UserInformationPolicy::class,
        DashboardService::class => DashboardPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
          Gate::define('view-dashboard', function (User $user) {
            return $user->isAdmin() || $user->isScanner();
        });

        Gate::define('manage-settings', function (User $user) {
            return $user->isAdmin();
        });

        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        Passport::tokensCan([
            'admin' => 'Full admin access',
            'scanner' => 'Scan tickets',
            'user' => 'Regular user access',
        ]);
    }
}