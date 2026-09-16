<?php

namespace App\Providers;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\OrganizationRequest;
use App\Models\User;
use App\Policies\InvitationPolicy;
use App\Policies\MemberPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\OrganizationRequestPolicy;
use App\Services\SecurityService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(12));

        Event::listen(Failed::class, function (Failed $event): void {
            $actor = isset($event->credentials['email'])
                ? User::where('email', $event->credentials['email'])->first()
                : null;

            app(SecurityService::class)->record($actor, 'failed_login', [
                'email' => $event->credentials['email'] ?? null,
                'ip' => request()->ip(),
            ]);
        });

        Event::listen(Lockout::class, function (Lockout $event): void {
            app(SecurityService::class)->record(null, 'account_locked', [
                'ip' => request()->ip(),
            ]);
        });

        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(OrganizationMember::class, MemberPolicy::class);
        Gate::policy(OrganizationInvitation::class, InvitationPolicy::class);
        Gate::policy(OrganizationRequest::class, OrganizationRequestPolicy::class);

        Route::bind('organization', fn (string $value) => Organization::where('slug', $value)
            ->whereIn('id', auth()->user()?->organizations()->wherePivot('status', 'active')->pluck('organizations.id') ?? [])
            ->firstOrFail());

        Route::bind('member', function (string $value): OrganizationMember {
            $orgParam = request()->route()?->parameter('organization');
            $orgId = $orgParam instanceof Organization
                ? $orgParam->getKey()
                : auth()->user()?->organizations()
                    ->wherePivot('status', 'active')
                    ->where('slug', (string) $orgParam)
                    ->value('organizations.id');

            return OrganizationMember::whereKey($value)
                ->when($orgId !== null, fn ($query) => $query->where('organization_id', $orgId))
                ->firstOrFail();
        });
    }
}
